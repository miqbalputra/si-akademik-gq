<?php

namespace App\Observers;

use App\Models\ClassEnrollment;
use App\Models\TahfidzWeeklyScore;
use App\Services\NotificationDispatcher;

class TahfidzWeeklyScoreObserver
{
    public function created(TahfidzWeeklyScore $score): void
    {
        $this->notify($score, 'diinput');
    }

    public function updated(TahfidzWeeklyScore $score): void
    {
        // Hanya bila field penting berubah (skip status-only changes).
        if (! $score->wasChanged(['score', 'surah_ayat', 'sabaq_amount', 'category', 'notes'])) {
            return;
        }
        $this->notify($score, 'diperbarui');
    }

    public function deleted(TahfidzWeeklyScore $score): void
    {
        $student = $score->student;
        $weekLabel = $score->week?->date_label ?? "pekan {$score->tahfidz_week_id}";

        $body = $student
            ? "Nilai tahfidz pekan {$weekLabel} santri {$student->name} dihapus oleh guru halaqah."
            : "Nilai tahfidz pekan {$weekLabel} dihapus.";

        $dispatcher = app(NotificationDispatcher::class);

        // Wali santri
        if ($student) {
            $dispatcher->dispatchToGuardiansOfStudent(
                $score->student_id,
                'Nilai tahfidz dihapus',
                $body,
                'tahfidz_weekly',
                route('wali.tahfidz'),
                'warning',
            );
        }

        // Wali kelas (resolve via active enrollment di periode tsb).
        $this->notifyHomeroom($score, $body, 'warning');
    }

    private function notify(TahfidzWeeklyScore $score, string $verb): void
    {
        $student = $score->student;
        if (! $student) {
            return;
        }

        $weekLabel = $score->week?->date_label ?? "pekan {$score->tahfidz_week_id}";
        $scoreVal = $score->score !== null ? (float) $score->score : null;
        $scoreText = $scoreVal !== null ? "nilai {$scoreVal}" : 'tanpa nilai';
        $category = $score->category ? " ({$score->category})" : '';

        $body = "Nilai tahfidz pekan {$weekLabel} santri {$student->name} {$verb}: {$scoreText}{$category}.";
        $severity = $scoreVal !== null && $scoreVal < 70 ? 'warning' : 'info';

        $dispatcher = app(NotificationDispatcher::class);

        // Wali santri (orang tua).
        $dispatcher->dispatchToGuardiansOfStudent(
            $score->student_id,
            'Nilai tahfidz pekanan baru',
            $body,
            'tahfidz_weekly',
            route('wali.tahfidz'),
            $severity,
        );

        // Wali kelas (homeroom teacher) santri tsb.
        $this->notifyHomeroom($score, $body, $severity);

        // Kabag tahfidz (broadcast by role).
        $dispatcher->dispatchToRole(
            'kabag_tahfidz',
            'Input nilai tahfidz pekanan',
            $body,
            'tahfidz_weekly',
            route('guru.tahfidz.show', $score->tahfidz_halaqah_id),
            'info',
        );
    }

    /**
     * Resolve wali kelas santri via active class enrollment di periode halaqah.
     */
    private function notifyHomeroom(TahfidzWeeklyScore $score, string $body, string $severity): void
    {
        $termId = $score->halaqah?->academic_term_id;
        if (! $termId) {
            return;
        }
        $classroomTermId = ClassEnrollment::query()
            ->where('student_id', $score->student_id)
            ->where('academic_term_id', $termId)
            ->where('status', 'active')
            ->value('classroom_term_id');
        if (! $classroomTermId) {
            return;
        }

        app(NotificationDispatcher::class)->dispatchToHomeroomTeacher(
            $classroomTermId,
            'Nilai tahfidz santri kelas Anda',
            $body,
            'tahfidz_weekly',
            route('guru.tasmi-wali.index'), // wali kelas lihat data santri di kelasnya
            $severity,
        );
    }
}