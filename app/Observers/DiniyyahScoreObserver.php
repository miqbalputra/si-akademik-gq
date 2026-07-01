<?php

namespace App\Observers;

use App\Models\DiniyyahScore;
use App\Models\ScoreChangeLog;
use Illuminate\Support\Facades\Auth;

class DiniyyahScoreObserver
{
    public function created(DiniyyahScore $score): void
    {
        if ($score->score === null) {
            return;
        }

        $this->log($score, null, $score->score, 'created');
    }

    public function updated(DiniyyahScore $score): void
    {
        if (! $score->wasChanged('score')) {
            return;
        }

        $this->log($score, $score->getOriginal('score'), $score->score, 'updated');
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
}
