<?php

namespace App\Services;

use App\Models\PanelNotification;
use App\Models\PanelUserPreference;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TasmiWaliReminderPreferenceService
{
    public const PANEL_KEY = 'guru-tasmi-wali-reminder';

    public function __construct(private readonly TasmiReportService $reports) {}

    public function dismiss(User $user): CarbonInterface
    {
        $dismissedAt = now('Asia/Jakarta');
        $preference = PanelUserPreference::firstOrNew([
            'user_id' => $user->id,
            'panel_key' => self::PANEL_KEY,
        ]);
        $preferences = $preference->preferences ?? [];
        $preferences['dismissed_at'] = $dismissedAt->toIso8601String();
        $preferences['dismissed_notification_id'] = PanelNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('notification_type', ['tasmi_created', 'tasmi_updated'])
            ->max('id');
        $preference->preferences = $preferences;
        $preference->save();

        return $dismissedAt;
    }

    /** @return array<string, mixed>|null */
    public function reminderFor(User $user): ?array
    {
        $teacher = $user->teacher;
        if (! $user->hasRole('guru') || ! $teacher) {
            return null;
        }

        $notifications = PanelNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->where('status', 'unread')
            ->whereIn('notification_type', ['tasmi_created', 'tasmi_updated'])
            ->latest('updated_at')
            ->get();

        if ($notifications->isEmpty()) {
            return null;
        }

        $notificationRecordIds = $notifications
            ->mapWithKeys(fn (PanelNotification $notification) => [$notification->id => $this->recordIdFromLink($notification->link_url)])
            ->filter();
        if ($notificationRecordIds->isEmpty()) {
            return null;
        }

        $records = $this->reports->forHomeroom($teacher)
            ->whereIn('tasmi_records.id', $notificationRecordIds->values()->unique())
            ->get()
            ->keyBy('id');
        if ($records->isEmpty()) {
            return null;
        }

        $items = $notifications->map(function (PanelNotification $notification) use ($notificationRecordIds, $records): ?array {
            $record = $records->get($notificationRecordIds->get($notification->id));
            if (! $record) {
                return null;
            }

            return [
                'notification_id' => $notification->id,
                'changed_at' => $notification->updated_at,
                'detail_url' => $notification->link_url,
                'student_name' => $record->student?->name ?? 'Santri',
                'classroom_name' => $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-',
                'date_label' => $record->exam_date?->locale('id')->translatedFormat('d M Y') ?? '-',
                'juz_label' => $record->juz_range_label,
                'predicate_label' => $record::predicateLabel($record->predicate) ?? '-',
            ];
        })->filter()->unique(fn (array $item) => $item['detail_url'])->values();

        if ($items->isEmpty()) {
            return null;
        }

        $dismissal = $this->dismissal($user);
        $dismissedAt = $dismissal['at'];
        $dismissedNotificationId = $dismissal['notification_id'];
        $newItems = $dismissedAt
            ? $items->filter(function (array $item) use ($dismissedAt, $dismissedNotificationId): bool {
                $changedAt = $item['changed_at'];

                return $changedAt?->greaterThan($dismissedAt)
                    || ($changedAt?->equalTo($dismissedAt) && $dismissedNotificationId !== null && $item['notification_id'] > $dismissedNotificationId);
            })->values()
            : $items;

        return [
            'count' => $items->count(),
            'items' => $items,
            'new_items' => $newItems,
            'should_show_modal' => $newItems->isNotEmpty(),
        ];
    }

    /** @return array{at: CarbonInterface|null, notification_id: int|null} */
    private function dismissal(User $user): array
    {
        $preference = PanelUserPreference::query()
            ->where('user_id', $user->id)
            ->where('panel_key', self::PANEL_KEY)
            ->first(['preferences']);
        $value = $preference?->preferences['dismissed_at'] ?? null;
        $notificationId = $preference?->preferences['dismissed_notification_id'] ?? null;
        $notificationId = is_numeric($notificationId) ? (int) $notificationId : null;

        if (! is_string($value) || $value === '') {
            return ['at' => null, 'notification_id' => $notificationId];
        }

        try {
            return [
                'at' => Carbon::parse($value)->setTimezone('Asia/Jakarta'),
                'notification_id' => $notificationId,
            ];
        } catch (\Throwable) {
            return ['at' => null, 'notification_id' => $notificationId];
        }
    }

    private function recordIdFromLink(?string $link): ?int
    {
        $path = parse_url((string) $link, PHP_URL_PATH) ?: '';
        if (! preg_match('#/guru/tasmi-wali/(\d+)$#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
