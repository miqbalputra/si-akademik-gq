<?php

namespace App\Services;

use App\Models\PanelUserPreference;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class GuruJournalReminderPreferenceService
{
    public const PANEL_KEY = 'guru-journal-reminder';

    public function snooze(User $user): CarbonInterface
    {
        $snoozedUntil = now('Asia/Jakarta')->addHours(3);
        $preference = PanelUserPreference::firstOrNew([
            'user_id' => $user->id,
            'panel_key' => self::PANEL_KEY,
        ]);
        $preferences = $preference->preferences ?? [];

        $preferences['snoozed_until'] = $snoozedUntil->toIso8601String();
        $preference->preferences = $preferences;
        $preference->save();

        return $snoozedUntil;
    }

    public function snoozedUntil(User $user): ?CarbonInterface
    {
        $preference = PanelUserPreference::query()
            ->where('user_id', $user->id)
            ->where('panel_key', self::PANEL_KEY)
            ->first(['preferences']);
        $value = $preference?->preferences['snoozed_until'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone('Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }

    public function clearSnooze(User $user): void
    {
        $preference = PanelUserPreference::query()
            ->where('user_id', $user->id)
            ->where('panel_key', self::PANEL_KEY)
            ->first();

        if (! $preference || ! array_key_exists('snoozed_until', $preference->preferences ?? [])) {
            return;
        }

        $preferences = $preference->preferences;
        unset($preferences['snoozed_until']);
        $preference->preferences = $preferences;
        $preference->save();
    }
}
