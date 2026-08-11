<?php

namespace App\Observers;

use App\Models\TahfidzUasScore;
use App\Services\NotificationDispatcher;

class TahfidzUasScoreObserver
{
    public function created(TahfidzUasScore $score): void
    {
        $this->notify($score, 'diinput');
    }

    public function updated(TahfidzUasScore $score): void
    {
        if (! $score->wasChanged('score')) {
            return;
        }
        $this->notify($score, 'diperbarui');
    }

    private function notify(TahfidzUasScore $score, string $verb): void
    {
        $student = $score->student;
        $dayLabel = $score->day?->label ?? "hari {$score->tahfidz_uas_day_id}";
        $categoryName = $score->category?->name ?? 'kategori';
        $scoreVal = $score->score !== null ? (float) $score->score : null;
        $scoreText = $scoreVal !== null ? "nilai {$scoreVal}" : 'tanpa nilai';

        $body = $student
            ? "Nilai UAS tahfidz santri {$student->name} hari {$dayLabel} kategori {$categoryName} {$verb}: {$scoreText}."
            : "Nilai UAS tahfidz hari {$dayLabel} kategori {$categoryName} {$verb}: {$scoreText}.";

        // Kabag tahfidz (broadcast).
        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_tahfidz',
            'Input nilai UAS tahfidz',
            $body,
            'tahfidz_uas',
            route('guru.tahfidz.uas', $score->tahfidz_halaqah_id ?? 0),
            'info',
        );
    }
}