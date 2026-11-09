<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DiniyyahTeacherScoreEditor
{
    public function editable(DiniyyahAssessmentSet $set): bool
    {
        return in_array($set->status, ['active', 'needs_revision'], true)
            && ! DiniyyahLedgerSnapshot::where('classroom_term_id', $set->classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists()
            && ! ReportCard::where('classroom_term_id', $set->classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists();
    }

    private function authorize(DiniyyahAssessmentSet $set, User $user): void
    {
        $set->refresh();
        Gate::forUser($user)->authorize('inputScores', $set);
        abort_unless($this->editable($set), 403, 'Nilai tidak dapat diubah: sudah diajukan/divalidasi atau leger/rapor kelas terkunci.');
    }

    /** Only changed cells are written. The entire batch rolls back on a stale or invalid cell. */
    public function save(DiniyyahAssessmentSet $set, User $user, array $changes): array
    {
        return app(DiniyyahKkmResolver::class)->withClassLocks($set->classSubject->classroomTerm, function () use ($set, $user, $changes) {
            $this->authorize($set, $user);
            $components = $set->components()->get()->keyBy('id');
            $enrollments = ClassEnrollment::where('classroom_term_id', $set->classSubject->classroom_term_id)
                ->where('status', 'active')->forVisibleStudents()->get()->keyBy('id');
            $planned = [];
            foreach ($changes as $index => $change) {
                $component = $components->get($change['component_id']);
                if (! $component || ! $enrollments->has($change['enrollment_id']) || $component->code === 'keaktifan_presensi') {
                    throw ValidationException::withMessages(['changes.'.$index => 'Santri atau komponen nilai tidak dapat diedit pada penilaian ini.']);
                }
                $key = $change['enrollment_id'].'-'.$change['component_id'];
                if (isset($planned[$key])) {
                    throw ValidationException::withMessages(['changes.'.$index => 'Sel nilai dikirim lebih dari satu kali.']);
                }
                $existing = DiniyyahScore::withTrashed()->where('diniyyah_score_component_id', $component->id)
                    ->where('class_enrollment_id', $change['enrollment_id'])->first();
                $current = $this->value($existing?->score);
                $target = $this->value($change['score']);
                abort_if($existing?->trashed(), 409, 'Data nilai telah diarsipkan. Muat ulang halaman sebelum melanjutkan.');
                abort_if($current !== $target && $current !== $this->value($change['original']), 409,
                    'Nilai telah diubah dari tab atau pengguna lain. Salin isian yang belum tersimpan lalu muat ulang halaman.');
                $planned[$key] = compact('existing', 'current', 'target', 'change');
            }

            $recalculate = [];
            foreach ($planned as $item) {
                if ($item['current'] === $item['target']) {
                    continue;
                } // A retry must not create audit/notification duplicates.
                $change = $item['change'];
                $score = $item['existing'] ?? new DiniyyahScore([
                    'diniyyah_assessment_set_id' => $set->id,
                    'diniyyah_score_component_id' => $change['component_id'],
                    'class_enrollment_id' => $change['enrollment_id'],
                ]);
                $score->fill(['score' => $item['target'], 'input_by' => $user->id, 'input_at' => now(), 'status' => 'draft'])->save();
                $recalculate[$change['enrollment_id']] = true;
            }
            $set->setRelation('components', $components->values());
            foreach (array_keys($recalculate) as $enrollmentId) {
                app(DiniyyahScoreCalculator::class)->calculate($set, $enrollments[$enrollmentId]);
            }

            return [
                'saved' => array_values(array_map(fn ($item) => [
                    'enrollment_id' => (int) $item['change']['enrollment_id'],
                    'component_id' => (int) $item['change']['component_id'], 'score' => $item['target'],
                ], $planned)),
                'rows' => $set->results()->whereIn('class_enrollment_id', collect($changes)->pluck('enrollment_id'))->get()
                    ->map(fn ($r) => ['enrollment_id' => $r->class_enrollment_id, 'final_score' => $r->final_score, 'complete' => $r->is_complete])->all(),
                'progress' => $this->progress($set, $enrollments->keys()->all()),
            ];
        });
    }

    public function add(DiniyyahAssessmentSet $set, User $user, string $type, string $expectedCode): array
    {
        return app(DiniyyahKkmResolver::class)->withClassLocks($set->classSubject->classroomTerm, function () use ($set, $user, $type, $expectedCode) {
            $this->authorize($set, $user);
            abort_unless(in_array($set->assessment_method, ['weighted', 'practical'], true) && ! $set->classSubject->subject?->isSuluk(), 422, 'Penilaian ini tidak menggunakan ulangan harian atau tugas.');
            $builder = app(DiniyyahAssessmentComponentBuilder::class);
            $set->load('components');
            $component = $set->components->firstWhere('code', $expectedCode);
            $prefix = $type === 'ulangan' ? 'ulangan_harian_' : 'nilai_tugas_';
            if (! str_starts_with($expectedCode, $prefix) || (! $component && $expectedCode !== $builder->nextCode($set, $type))) {
                abort(409, 'Daftar kolom sudah berubah. Muat ulang halaman sebelum menambah kolom.');
            }
            if (! $component) {
                $number = (int) substr($expectedCode, strlen($prefix));
                $component = $set->components()->create([
                    'code' => $expectedCode, 'name' => ($type === 'ulangan' ? 'Ulangan Harian ' : 'Nilai Tugas ').$number,
                    'component_group' => 'daily', 'is_required' => false,
                    'sort_order' => ($set->components->max('sort_order') ?? 0) + 10,
                ]);
                activity('diniyyah')->performedOn($set)->causedBy($user)->event('component_added')
                    ->withProperties(['component_id' => $component->id, 'code' => $component->code])->log('Menambah kolom nilai opsional');
            }
            $set->load('components');

            return [
                'component' => ['id' => $component->id, 'name' => $component->inputLabel()],
                'order' => $builder->ordered($set)->pluck('id')->all(),
                'next' => ['ulangan' => $builder->nextCode($set, 'ulangan'), 'tugas' => $builder->nextCode($set, 'tugas')],
                'progress' => $this->progress($set),
            ];
        });
    }

    public function progress(DiniyyahAssessmentSet $set, ?array $ids = null): array
    {
        $ids ??= ClassEnrollment::where('classroom_term_id', $set->classSubject->classroom_term_id)->where('status', 'active')->forVisibleStudents()->pluck('id')->all();
        $total = count($ids);
        $results = $set->results()->whereIn('class_enrollment_id', $ids)->get();
        $complete = $results->where('is_complete', true)->count();

        return [
            'total_cells' => $total * $set->components()->count(),
            'filled_cells' => $set->scores()->whereIn('class_enrollment_id', $ids)->whereNotNull('score')->count(),
            'total_students' => $total, 'complete_students' => $complete,
            'percentage' => $total ? round($complete / $total * 100, 2) : 0,
        ] + $this->kkmFeedback($set, $results);
    }

    /** Feedback never changes saved scores or assumes a KKM that has not been approved. */
    public function kkmFeedback(DiniyyahAssessmentSet $set, Collection $results): array
    {
        $kkm = app(DiniyyahKkmResolver::class)->resolve($set->classSubject, $set->kkm === null ? null : (float) $set->kkm)['kkm'];
        $belowIds = $kkm === null ? [] : $results
            ->filter(fn ($result) => $result->is_complete && $result->final_score !== null && (float) $result->final_score < (float) $kkm)
            ->pluck('class_enrollment_id')->map(fn ($id) => (int) $id)->values()->all();

        return ['kkm' => $kkm === null ? null : (float) $kkm, 'below_kkm_students' => count($belowIds), 'below_kkm_enrollment_ids' => $belowIds];
    }

    private function value(mixed $score): ?string
    {
        return $score === null || $score === '' ? null : number_format((float) $score, 2, '.', '');
    }
}
