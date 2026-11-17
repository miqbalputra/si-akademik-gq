<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\TahfidzReportEntry;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzUasResult;
use Illuminate\Support\Collection;

class TahfidzReportSources
{
    public function load(ClassroomTerm $classroom, Collection $studentIds): array
    {
        $uas = TahfidzUasResult::where('academic_term_id', $classroom->academic_term_id)->whereIn('student_id', $studentIds)->orderBy('id')->get();
        $recaps = TahfidzSemesterRecap::where('academic_term_id', $classroom->academic_term_id)->whereIn('student_id', $studentIds)->orderBy('id')->get();
        $entries = TahfidzReportEntry::where('academic_term_id', $classroom->academic_term_id)->whereIn('student_id', $studentIds)
            ->orderByDesc('version')->get()->unique('student_id')->sortBy('student_id')->values();
        $payload = [$uas->toArray(), $recaps->toArray()];
        // Preserve the fingerprint format of historical ledgers without manual entries.
        if ($entries->isNotEmpty()) {
            $payload[] = $entries->toArray();
        }

        return ['uas' => $uas->groupBy('student_id'), 'recaps' => $recaps->groupBy('student_id'), 'entries' => $entries->keyBy('student_id'),
            'fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))];
    }

    public function resolve(array $sources, int $studentId): array
    {
        $entry = $sources['entries']->get($studentId);
        $uas = $sources['uas']->get($studentId, collect());
        $recaps = $sources['recaps']->get($studentId, collect());
        $result = $uas->count() === 1 ? $uas->first() : null;
        $recap = $recaps->count() === 1 ? $recaps->first() : null;
        $values = $entry ? [
            'tahfidz_juz' => $entry->juz_tested, 'tahfidz_uas' => $entry->uas_score,
            'tahfidz_sabaq' => $entry->sabaq_score, 'tahfidz_sabqi' => $entry->sabqi_score, 'tahfidz_manzil' => $entry->manzil_score,
        ] : [
            'tahfidz_juz' => $result?->juz_tested, 'tahfidz_uas' => $result?->final_score,
            'tahfidz_sabaq' => $recap?->sabaq_semester_score, 'tahfidz_sabqi' => $recap?->sabqi_score, 'tahfidz_manzil' => $recap?->manzil_average_score,
        ];
        $complete = collect($values)->every(fn ($v) => $v !== null && $v !== '');
        $ready = $entry ? $entry->status === 'reviewed' : ($result?->is_complete && in_array($result?->status, ['validated', 'locked', 'published'], true) && in_array($recap?->status, ['validated', 'locked', 'published'], true));

        return ['values' => $values, 'ready' => $complete && $ready, 'status' => $entry?->status ?? ($complete && $ready ? 'legacy_validated' : 'missing'),
            'entry' => $entry, 'uas_id' => $result?->id, 'recap_id' => $recap?->id];
    }
}
