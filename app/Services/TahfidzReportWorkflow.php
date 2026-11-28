<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\TahfidzReportEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TahfidzReportWorkflow
{
    public const FIELDS = [
        'juz_tested' => 'Juz yang diujikan',
        'uas_score' => 'Nilai Ujian Tahfidz',
        'sabaq_score' => 'Sabaq',
        'sabqi_score' => 'Sabqi',
        'manzil_score' => 'Manzil',
    ];

    public function enrollments(User $user, int $termId): Builder
    {
        abort_unless($user->hasRole('kabag_tahfidz'), 403);

        return ClassEnrollment::where('academic_term_id', $termId)->where('status', 'active')->forVisibleStudents();
    }

    public function latestVersions(int $termId, Collection $studentIds): Collection
    {
        return TahfidzReportEntry::where('academic_term_id', $termId)->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, MAX(version) AS latest_version')->groupBy('student_id')->pluck('latest_version', 'student_id');
    }

    public static function valueRules(): array
    {
        return ['juz_tested' => ['nullable', 'string', 'max:255']]
            + array_fill_keys(['uas_score', 'sabaq_score', 'sabqi_score', 'manzil_score'], ['nullable', 'numeric', 'between:0,100']);
    }

    public function save(User $user, ClassEnrollment $enrollment, array $values, bool $final, int $expectedVersion): TahfidzReportEntry
    {
        abort_unless($this->enrollments($user, $enrollment->academic_term_id)->whereKey($enrollment->id)->exists(), 403);
        $values = Validator::make($values, self::valueRules())->validate();

        return DB::transaction(function () use ($user, $enrollment, $values, $final, $expectedVersion) {
            $this->lockClass($enrollment->classroomTerm);
            $this->ensureOpen($enrollment->classroomTerm);
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            abort_unless($this->currentEnrollment($enrollment), 403);
            $previous = $this->latest($enrollment->academic_term_id, $enrollment->student_id);
            $this->ensureVersion($previous, $expectedVersion);
            if ($final && ! $this->complete($values)) {
                throw ValidationException::withMessages(['tahfidz' => 'Lengkapi juz dan seluruh nilai sebelum menyimpan final. Nilai nol tetap diterima.']);
            }

            return $this->record($user, $enrollment, $previous, $values, $final);
        }, 3);
    }

    public function import(User $user, int $termId, int $classId, Collection $rows): array
    {
        $enrollments = $this->enrollments($user, $termId)->whereKey($rows->keys()->all())
            ->with('student', 'classroomTerm.academicTerm')->get()->keyBy('id');
        foreach ($rows as $id => $row) {
            $enrollment = $enrollments->get($id);
            if (! $enrollment || $enrollment->classroom_term_id !== $row['class_id']
                || ($classId && $enrollment->classroom_term_id !== $classId)
                || (string) $enrollment->student->nis !== $row['nis']) {
                throw ValidationException::withMessages(['file' => "Baris {$row['number']}: identitas santri atau kelas berubah. Unduh template terbaru."]);
            }
        }

        return DB::transaction(function () use ($user, $enrollments, $rows) {
            $counts = ['draft' => 0, 'final' => 0];
            foreach ($enrollments->groupBy('classroom_term_id')->sortKeys() as $members) {
                $classroom = $members->first()->classroomTerm;
                $this->lockClass($classroom);
                if ($this->isLocked($classroom, forUpdate: true)) {
                    throw ValidationException::withMessages(['file' => "Leger/rapor kelas {$classroom->name} sudah dikunci. Kosongkan nilai untuk kelas tersebut."]);
                }

                $sources = app(TahfidzReportSources::class);
                $classSources = $sources->load($classroom, $members->pluck('student_id'));
                foreach ($members->sortBy('student_id') as $enrollment) {
                    $row = $rows[$enrollment->id];
                    try {
                        $student = Student::whereKey($enrollment->student_id)->lockForUpdate()->first();
                        if (! $student || (string) $student->nis !== $row['nis'] || ! $this->currentEnrollment($enrollment)) {
                            throw ValidationException::withMessages(['file' => 'Identitas santri atau kelas berubah. Unduh template terbaru.']);
                        }
                        $previous = $this->latest($enrollment->academic_term_id, $enrollment->student_id);
                        $this->ensureVersion($previous, $row['version']);
                        $existing = $previous?->only(array_keys(self::FIELDS))
                            ?? $sources->entryValues($classSources, $enrollment->student_id);
                        $values = array_replace($existing, $row['values']);
                        $entry = $this->record($user, $enrollment, $previous, $values, $this->complete($values));
                        $counts[$entry->status]++;
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages(['file' => "Baris {$row['number']}: ".$exception->getMessage()]);
                    }
                }
            }

            return $counts;
        }, 3);
    }

    public function review(User $user, ClassEnrollment $enrollment, int $expectedVersion): TahfidzReportEntry
    {
        abort_unless(app(DiniyyahLedgerAccess::class)->allows($user, $enrollment->classroom_term_id), 403);

        return DB::transaction(function () use ($user, $enrollment, $expectedVersion) {
            $this->lockClass($enrollment->classroomTerm);
            $this->ensureOpen($enrollment->classroomTerm);
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            abort_unless(app(DiniyyahLedgerAccess::class)->allows($user, $enrollment->classroom_term_id), 403);
            abort_unless($this->currentEnrollment($enrollment), 403);
            $previous = $this->latest($enrollment->academic_term_id, $enrollment->student_id);
            $this->ensureVersion($previous, $expectedVersion);
            if ($previous?->status !== 'final') {
                throw ValidationException::withMessages(['tahfidz' => 'Hanya nilai final yang dapat diperiksa wali kelas.']);
            }
            $entry = TahfidzReportEntry::create(array_merge($previous->only(array_keys(self::FIELDS)), [
                'academic_term_id' => $enrollment->academic_term_id, 'student_id' => $enrollment->student_id,
                'version' => $previous->version + 1, 'status' => 'reviewed', 'input_by' => $previous->input_by,
                'input_role' => $previous->input_role,
                'recorded_by' => $user->id, 'reviewed_by' => $user->id, 'reviewed_at' => now(),
            ]));
            activity('report_cards')->performedOn($entry)->causedBy($user)->withProperties(['previous_entry_id' => $previous->id])->log('tahfidz_report_reviewed');

            return $entry;
        }, 3);
    }

    public function isLocked(ClassroomTerm $classroom, bool $forUpdate = false): bool
    {
        return DiniyyahLedgerSnapshot::where('classroom_term_id', $classroom->id)->whereIn('status', ['locked', 'published'])->lock($forUpdate ? true : null)->first(['id']) !== null
            || ReportCard::where('classroom_term_id', $classroom->id)->whereIn('status', ['locked', 'published'])->lock($forUpdate ? true : null)->first(['id']) !== null;
    }

    private function ensureOpen(ClassroomTerm $classroom): void
    {
        if ($this->isLocked($classroom, forUpdate: true)) {
            throw ValidationException::withMessages(['tahfidz' => 'Leger/rapor kelas sudah dikunci. Nilai final tidak dapat diubah.']);
        }
    }

    private function lockClass(ClassroomTerm $classroom): void
    {
        // Ledger and report generation use the same classroom lock.
        ClassroomTerm::whereKey($classroom->id)->lockForUpdate()->firstOrFail();
    }

    private function currentEnrollment(ClassEnrollment $enrollment): bool
    {
        return ClassEnrollment::where('academic_term_id', $enrollment->academic_term_id)->where('status', 'active')->forVisibleStudents()
            ->where('classroom_term_id', $enrollment->classroom_term_id)->where('student_id', $enrollment->student_id)
            ->whereKey($enrollment->id)->lockForUpdate()->first() !== null;
    }

    private function latest(int $termId, int $studentId): ?TahfidzReportEntry
    {
        return TahfidzReportEntry::where('academic_term_id', $termId)->where('student_id', $studentId)
            ->latest('version')->lockForUpdate()->first();
    }

    private function complete(array $values): bool
    {
        return collect(array_keys(self::FIELDS))->every(fn ($field) => filled($values[$field] ?? null));
    }

    private function record(User $user, ClassEnrollment $enrollment, ?TahfidzReportEntry $previous, array $values, bool $final): TahfidzReportEntry
    {
        $entry = TahfidzReportEntry::create(array_merge(array_fill_keys(array_keys(self::FIELDS), null), $values, [
            'academic_term_id' => $enrollment->academic_term_id, 'student_id' => $enrollment->student_id,
            'version' => ($previous?->version ?? 0) + 1, 'status' => $final ? 'final' : 'draft',
            'input_by' => $user->id, 'input_role' => 'kabag_tahfidz', 'recorded_by' => $user->id,
        ]));
        activity('report_cards')->performedOn($entry)->causedBy($user)->withProperties(['previous_entry_id' => $previous?->id])->log('tahfidz_report_saved');

        return $entry;
    }

    private function ensureVersion(?TahfidzReportEntry $entry, int $version): void
    {
        if (($entry?->version ?? 0) !== $version) {
            throw ValidationException::withMessages(['tahfidz' => 'Data telah diperbarui. Muat ulang halaman atau unduh template terbaru sebelum menyimpan.']);
        }
    }
}
