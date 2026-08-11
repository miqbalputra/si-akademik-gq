<?php

namespace App\Observers;

use App\Models\ScoreChangeLog;
use App\Models\TasmiRecord;
use Illuminate\Support\Facades\Auth;

class TasmiRecordObserver
{
    public function created(TasmiRecord $record): void
    {
        ScoreChangeLog::create([
            'score_table' => $record->getTable(),
            'score_id' => $record->id,
            'old_score' => null,
            'new_score' => $record->predicate,
            'changed_by' => $record->input_by ?: Auth::id(),
            'changed_at' => now(),
            'reason' => 'created_tasmi',
        ]);
    }

    public function updated(TasmiRecord $record): void
    {
        $changes = [];
        $trackedFields = ['predicate', 'juz_start', 'juz_end', 'exam_type', 'exam_date', 'hijri_date', 'examiner_teacher_id', 'student_id', 'notes'];

        foreach ($trackedFields as $field) {
            if ($record->wasChanged($field)) {
                $changes[$field] = [
                    'from' => $record->getOriginal($field),
                    'to' => $record->getAttribute($field),
                ];
            }
        }

        if (empty($changes)) {
            return;
        }

        // Catat perubahan utama di score_change_logs (kolom old/new dipakai
        // untuk predicate sesuai skema yang ada). Detail field lain dicatat
        // via activity log bawah.
        ScoreChangeLog::create([
            'score_table' => $record->getTable(),
            'score_id' => $record->id,
            'old_score' => $record->getOriginal('predicate'),
            'new_score' => $record->predicate,
            'changed_by' => $record->last_updated_by ?: Auth::id(),
            'changed_at' => now(),
            'reason' => 'updated_tasmi',
        ]);

        activity()
            ->performedOn($record)
            ->causedBy(Auth::user())
            ->withProperties(['changes' => $changes])
            ->log('updated_tasmi_record');
    }

    public function deleted(TasmiRecord $record): void
    {
        activity()
            ->performedOn($record)
            ->causedBy(Auth::user())
            ->withProperties([
                'record_id' => $record->id,
                'student_id' => $record->student_id,
                'exam_date' => $record->exam_date?->toDateString(),
                'predicate' => $record->predicate,
            ])
            ->log('deleted_tasmi_record');
    }
}