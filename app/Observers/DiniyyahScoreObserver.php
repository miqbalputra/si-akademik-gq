<?php

namespace App\Observers;

use App\Models\DiniyyahScore;
use App\Models\ScoreChangeLog;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;

class DiniyyahScoreObserver
{
    public function created(DiniyyahScore $score): void
    {
        if ($score->score === null) {
            return;
        }

        $this->log($score, null, $score->score, 'created');
        $this->notifyKabag($score, 'diinput');
    }

    public function updated(DiniyyahScore $score): void
    {
        if (! $score->wasChanged('score')) {
            return;
        }

        $this->log($score, $score->getOriginal('score'), $score->score, 'updated');
        $this->notifyKabag($score, 'diperbarui');
    }

    private function log(DiniyyahScore $score, mixed $oldScore, mixed $newScore, string $reason): void
    {
        ScoreChangeLog::create([
            'score_table' => $score->getTable(),
            'score_id' => $score->id,
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'changed_by' => $score->input_by ?: Auth::id(),
            'changed_at' => now(),
            'reason' => $reason,
        ]);
    }

    /**
     * Notifikasi ke kabag_diniyyah saat ada input nilai.
     * Batching otomatis: banyak cell input dalam 10 menit → 1 notif (link_url
     * sama = halaman edit assessment_set). Tidak spam per cell.
     */
    private function notifyKabag(DiniyyahScore $score, string $verb): void
    {
        $set = $score->assessmentSet ?? \App\Models\DiniyyahAssessmentSet::find($score->diniyyah_assessment_set_id);
        if (! $set) {
            return;
        }
        $mapel = $set->classSubject?->subject?->name ?? 'mapel';
        $kelas = $set->classSubject?->classroomTerm?->name ?? 'kelas';

        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_diniyyah',
            "Nilai {$mapel} {$verb}",
            "Nilai {$mapel} kelas {$kelas} {$verb} oleh guru pengampu.",
            'diniyyah_score_input',
            route('diniyyah.monitoring.index'),
            'info',
        );
    }
}
