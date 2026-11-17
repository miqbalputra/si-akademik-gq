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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TahfidzReportWorkflow
{
    public const FIELDS = ['juz_tested', 'uas_score', 'sabaq_score', 'sabqi_score', 'manzil_score'];

    public function enrollments(User $user, int $termId): Builder
    {
        $teacher = $user->teacher;
        $query = ClassEnrollment::where('academic_term_id', $termId)->where('status', 'active');
        if (! $user->hasRole('guru') || ! $teacher || $teacher->status !== 'active' || ! in_array($teacher->gender, ['male', 'female'], true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('student', fn ($q) => $q->where('status', 'active')->where('gender', $teacher->gender));
    }

    public function latest(int $termId, int $studentId): ?TahfidzReportEntry
    {
        return TahfidzReportEntry::where('academic_term_id', $termId)->where('student_id', $studentId)->latest('version')->first();
    }

    public function save(User $user, ClassEnrollment $enrollment, array $values, bool $final, int $expectedVersion): TahfidzReportEntry
    {
        abort_unless($this->enrollments($user, $enrollment->academic_term_id)->whereKey($enrollment->id)->exists(), 403);
        Validator::make($values, [
            'juz_tested' => ['nullable', 'string', 'max:255'],
            'uas_score' => ['nullable', 'numeric', 'between:0,100'], 'sabaq_score' => ['nullable', 'numeric', 'between:0,100'],
            'sabqi_score' => ['nullable', 'numeric', 'between:0,100'], 'manzil_score' => ['nullable', 'numeric', 'between:0,100'],
        ])->validate();

        return app(DiniyyahKkmResolver::class)->withClassLocks($enrollment->classroomTerm, function () use ($user, $enrollment, $values, $final, $expectedVersion) {
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($enrollment->classroomTerm);
            abort_unless($this->enrollments($user, $enrollment->academic_term_id)->whereKey($enrollment->id)->exists(), 403);
            $previous = $this->latest($enrollment->academic_term_id, $enrollment->student_id);
            $this->ensureVersion($previous, $expectedVersion);
            if ($final && collect(self::FIELDS)->contains(fn ($field) => ! array_key_exists($field, $values) || $values[$field] === null || $values[$field] === '')) {
                throw ValidationException::withMessages(['tahfidz' => 'Lengkapi juz dan seluruh nilai sebelum menyimpan final. Nilai nol tetap diterima.']);
            }
            $entry = TahfidzReportEntry::create(array_merge(array_fill_keys(self::FIELDS, null), array_intersect_key($values, array_flip(self::FIELDS)), [
                'academic_term_id' => $enrollment->academic_term_id, 'student_id' => $enrollment->student_id,
                'version' => ($previous?->version ?? 0) + 1, 'status' => $final ? 'final' : 'draft',
                'input_by' => $user->id, 'recorded_by' => $user->id,
            ]));
            activity('report_cards')->performedOn($entry)->causedBy($user)->withProperties(['previous_entry_id' => $previous?->id])->log('tahfidz_report_saved');

            return $entry;
        });
    }

    public function review(User $user, ClassEnrollment $enrollment, int $expectedVersion): TahfidzReportEntry
    {
        abort_unless(app(DiniyyahLedgerAccess::class)->allows($user, $enrollment->classroom_term_id), 403);

        return app(DiniyyahKkmResolver::class)->withClassLocks($enrollment->classroomTerm, function () use ($user, $enrollment, $expectedVersion) {
            Student::whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($enrollment->classroomTerm);
            abort_unless(app(DiniyyahLedgerAccess::class)->allows($user, $enrollment->classroom_term_id), 403);
            $previous = $this->latest($enrollment->academic_term_id, $enrollment->student_id);
            $this->ensureVersion($previous, $expectedVersion);
            if ($previous?->status !== 'final') {
                throw ValidationException::withMessages(['tahfidz' => 'Hanya nilai final guru yang dapat diperiksa wali kelas.']);
            }
            $entry = TahfidzReportEntry::create(array_merge($previous->only(self::FIELDS), [
                'academic_term_id' => $enrollment->academic_term_id, 'student_id' => $enrollment->student_id,
                'version' => $previous->version + 1, 'status' => 'reviewed', 'input_by' => $previous->input_by,
                'recorded_by' => $user->id, 'reviewed_by' => $user->id, 'reviewed_at' => now(),
            ]));
            activity('report_cards')->performedOn($entry)->causedBy($user)->withProperties(['previous_entry_id' => $previous->id])->log('tahfidz_report_reviewed');

            return $entry;
        });
    }

    public function ensureOpen(ClassroomTerm $classroom): void
    {
        if (DiniyyahLedgerSnapshot::where('classroom_term_id', $classroom->id)->whereIn('status', ['locked', 'published'])->exists()
            || ReportCard::where('classroom_term_id', $classroom->id)->whereIn('status', ['locked', 'published'])->exists()) {
            throw ValidationException::withMessages(['tahfidz' => 'Leger/rapor kelas sudah dikunci. Nilai final tidak dapat diubah.']);
        }
    }

    private function ensureVersion(?TahfidzReportEntry $entry, int $version): void
    {
        if (($entry?->version ?? 0) !== $version) {
            throw ValidationException::withMessages(['tahfidz' => 'Data telah diperbarui guru/wali kelas lain. Muat ulang sebelum menyimpan.']);
        }
    }
}
