<?php

namespace App\Services;

use App\Models\PanelUserPreference;
use App\Models\TasmiRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class TasmiWaliReminderPreferenceService
{
    public const PANEL_KEY = 'guru-tasmi-wali-reminder';

    public function __construct(private readonly TasmiReportService $reports) {}

    public function dismiss(User $user): CarbonInterface
    {
        $dismissedAt = now('Asia/Jakarta');
        $preference = PanelUserPreference::firstOrNew(['user_id' => $user->id, 'panel_key' => self::PANEL_KEY]);
        $preferences = $preference->preferences ?? [];
        $preferences['dismissed_at'] = $dismissedAt->toIso8601String();
        $preferences['review_signature'] = $user->teacher ? $this->signature($this->unapproved($user)) : null;
        $preference->preferences = $preferences;
        $preference->save();

        return $dismissedAt;
    }

    /** The reminder follows review state, even if notifications were read or archived. */
    public function reminderFor(User $user): ?array
    {
        if (! $user->hasRole('guru') || ! $user->teacher) {
            return null;
        }
        $query = $this->unapproved($user);
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }
        $items = (clone $query)->latest('tasmi_records.updated_at')->latest('tasmi_records.id')->limit(10)->get()
            ->map(fn (TasmiRecord $record) => [
                'detail_url' => route('guru.tasmi-wali.show', $record),
                'student_name' => $record->student?->name ?? 'Santri',
                'classroom_name' => $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-',
                'date_label' => $record->exam_date?->locale('id')->translatedFormat('d M Y') ?? '-',
                'juz_label' => $record->juz_range_label,
                'predicate_label' => $record::predicateLabel($record->predicate) ?? '-',
                'status_label' => $record->review_status_label,
            ]);
        $preference = PanelUserPreference::where('user_id', $user->id)->where('panel_key', self::PANEL_KEY)->first();

        return [
            'count' => $count, 'items' => $items, 'new_items' => $items,
            'should_show_modal' => ($preference?->preferences['review_signature'] ?? null) !== $this->signature($query),
        ];
    }

    public function correctionsFor(User $user): ?array
    {
        if (! $user->hasRole('guru') || ! $user->teacher) {
            return null;
        }
        $query = $this->reports->forExaminer($user->teacher)->where('review_status', TasmiRecord::REVIEW_CORRECTION);
        $count = (clone $query)->count();

        return $count ? ['count' => $count, 'records' => $query->latest('tasmi_records.reviewed_at')->limit(10)->get()] : null;
    }

    private function unapproved(User $user): Builder
    {
        return $this->reports->forHomeroom($user->teacher)->where(fn (Builder $q) => $q
            ->whereNull('tasmi_records.review_status')
            ->orWhereIn('tasmi_records.review_status', [TasmiRecord::REVIEW_PENDING, TasmiRecord::REVIEW_CORRECTION]));
    }

    private function signature(Builder $query): string
    {
        // Version distinguishes changes within the same second, unlike timestamps.
        return hash('sha256', (clone $query)->reorder()->orderBy('tasmi_records.id')->pluck('tasmi_records.review_version', 'tasmi_records.id')->toJson());
    }
}
