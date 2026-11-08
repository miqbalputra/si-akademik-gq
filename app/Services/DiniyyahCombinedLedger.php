<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzUasResult;
use DomainException;
use Illuminate\Support\Collection;

class DiniyyahCombinedLedger
{
    private const FIELDS = [
        'tahfidz_juz' => ['Juz UAS Tahfidz', 'juz_tested', false],
        'tahfidz_uas' => ['UAS Tahfidz', 'final_score', true],
        'tahfidz_sabaq' => ['Sabaq', 'sabaq_semester_score', true],
        'tahfidz_sabqi' => ['Sabqi', 'sabqi_score', true],
        'tahfidz_manzil' => ['Manzil', 'manzil_average_score', true],
    ];

    /** Copy existing, validated Tahfidz data; never recalculate or edit the source. */
    public function append(DiniyyahLedgerSnapshot $snapshot): void
    {
        if (in_array($snapshot->status, ['locked', 'published'], true)) {
            throw new DomainException('Leger terkunci tidak boleh diubah.');
        }
        $snapshot->load('rows.classEnrollment');
        $sources = $this->sources($snapshot->classroomTerm, $snapshot->rows->pluck('classEnrollment.student_id'));
        $data = $snapshot->snapshot_data;
        $columns = collect($data['columns']);
        $tahfidzColumns = collect(self::FIELDS)->map(fn ($field, $key) => [
            'key' => $key, 'label' => $field[0], 'source_type' => 'tahfidz_snapshot', 'numeric' => $field[2],
        ]);
        $data['columns'] = $columns->reject(fn ($c) => $c['source_type'] === 'student_attendance_recap')
            ->concat($tahfidzColumns)->concat($columns->where('source_type', 'student_attendance_recap'))->values()->all();
        $data['layout_version'] = 'combined-v1';
        $data['tahfidz_fingerprint'] = $sources['fingerprint'];
        $data['diniyyah_fingerprint'] = $this->diniyyahFingerprint($snapshot);
        $data['tahfidz_sources'] = [];
        $ranked = collect();
        $completeRows = 0;
        foreach ($snapshot->rows as $row) {
            $studentId = $row->classEnrollment->student_id;
            $uas = $sources['uas']->get($studentId, collect());
            $recaps = $sources['recaps']->get($studentId, collect());
            $result = $uas->count() === 1 ? $uas->first() : null;
            $recap = $recaps->count() === 1 ? $recaps->first() : null;
            $validUas = $result && $result->is_complete && in_array($result->status, ['validated', 'locked', 'published'], true);
            $validRecap = $recap && in_array($recap->status, ['validated', 'locked', 'published'], true);
            $values = [];
            foreach (self::FIELDS as $key => [$label, $field, $numeric]) {
                $model = str_starts_with($key, 'tahfidz_s') || $key === 'tahfidz_manzil' ? $recap : $result;
                $valid = $model === $recap ? $validRecap : $validUas;
                $value = $valid ? $model?->$field : null;
                $values[$key] = $value;
                $row->cells()->create([
                    'column_key' => $key, 'label' => $label, 'source_type' => 'tahfidz_snapshot', 'source_id' => $model?->id,
                    'value_numeric' => $numeric && $value !== null ? $value : null, 'value_text' => $value === null ? null : (string) $value,
                    'sort_order' => 500 + array_search($key, array_keys(self::FIELDS)),
                ]);
            }
            $data['tahfidz_sources'][$studentId] = ['uas_id' => $result?->id, 'recap_id' => $recap?->id];
            $complete = $row->total_diniyyah_score !== null && collect($values)->every(fn ($v) => $v !== null && $v !== '');
            if (! $complete) {
                $data['issues'][] = ['level' => 'blocking', 'code' => 'tahfidz_incomplete', 'class_enrollment_id' => $row->class_enrollment_id,
                    'message' => $row->student_name.': Tahfidz belum lengkap/tervalidasi atau memiliki lebih dari satu sumber semester.'];
            } else {
                $completeRows++;
            }
            $total = $complete ? round((float) $row->total_diniyyah_score + array_sum(array_map(fn ($k) => (float) $values[$k], ['tahfidz_uas', 'tahfidz_sabaq', 'tahfidz_sabqi', 'tahfidz_manzil'])), 2) : null;
            $average = $complete ? round($total / ($data['summary']['score_columns'] + 4), 2) : null;
            foreach (['combined_total' => $total, 'combined_average' => $average] as $key => $value) {
                $row->cells()->create(['column_key' => $key, 'label' => $key, 'source_type' => 'combined_summary', 'value_numeric' => $value]);
            }
            if ($complete) {
                $ranked->push(['row' => $row, 'total' => $total]);
            }
        }
        $ranked->sortByDesc('total')->values()->each(fn ($item, $index) => $item['row']->cells()->create([
            'column_key' => 'combined_rank', 'label' => 'Peringkat gabungan', 'source_type' => 'combined_summary', 'value_numeric' => $index + 1,
        ]));
        $data['summary']['complete_rows'] = $completeRows;
        $data['summary']['incomplete_rows'] = $snapshot->rows->count() - $completeRows;
        $data['summary']['blocking_issues'] = collect($data['issues'])->where('level', 'blocking')->count();
        $data['summary']['tahfidz_columns'] = 5;
        $snapshot->update(['snapshot_data' => $data]);
    }

    public function ensureCurrent(DiniyyahLedgerSnapshot $snapshot): void
    {
        if (($snapshot->snapshot_data['layout_version'] ?? '') !== 'combined-v1') {
            return;
        }
        $studentIds = $snapshot->rows()->with('classEnrollment')->get()->pluck('classEnrollment.student_id');
        if (($snapshot->snapshot_data['tahfidz_fingerprint'] ?? '') !== $this->sources($snapshot->classroomTerm, $studentIds)['fingerprint']) {
            throw new DomainException('Data Tahfidz berubah. Segarkan leger sebelum dikunci.');
        }
        if (($snapshot->snapshot_data['diniyyah_fingerprint'] ?? '') !== $this->diniyyahFingerprint($snapshot)) {
            throw new DomainException('Nilai Diniyyah berubah. Segarkan leger sebelum dikunci.');
        }
    }

    private function diniyyahFingerprint(DiniyyahLedgerSnapshot $snapshot): string
    {
        $sets = DiniyyahAssessmentSet::where('appears_on_ledger', true)
            ->whereHas('classSubject', fn ($q) => $q->where('classroom_term_id', $snapshot->classroom_term_id)->where('is_active', true)->where('appears_on_ledger', true))
            ->with(['components', 'scores', 'results'])->orderBy('id')->get();

        return hash('sha256', json_encode($sets->map(fn ($set) => [
            $set->id, $set->title, $set->tested_material, $set->assessment_method, $set->status, $set->kkm, $set->kkm_revision_id, $set->daily_weight, $set->exam_weight, $set->sort_order,
            $set->components->sortBy('id')->map(fn ($c) => [$c->id, $c->code, $c->component_group, $c->is_required])->values(),
            $set->scores->sortBy('id')->map(fn ($s) => [$s->id, $s->class_enrollment_id, $s->diniyyah_score_component_id, $s->score])->values(),
            $set->results->sortBy('id')->map(fn ($r) => [$r->id, $r->class_enrollment_id, $r->final_score, $r->is_complete, $r->kkm_revision_id])->values(),
        ])->all(), JSON_THROW_ON_ERROR));
    }

    private function sources(ClassroomTerm $classroom, Collection $studentIds): array
    {
        $uas = TahfidzUasResult::where('academic_term_id', $classroom->academic_term_id)->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get();
        $recaps = TahfidzSemesterRecap::where('academic_term_id', $classroom->academic_term_id)->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get();

        return ['uas' => $uas->groupBy('student_id'), 'recaps' => $recaps->groupBy('student_id'),
            'fingerprint' => hash('sha256', json_encode([$uas->toArray(), $recaps->toArray()], JSON_THROW_ON_ERROR))];
    }
}
