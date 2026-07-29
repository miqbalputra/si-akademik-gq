<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahTeacherAssignment;
use Illuminate\Support\Carbon;

/**
 * Statistik ringkasan penugasan guru Diniyyah untuk satu periode (academic term).
 *
 * Dipakai halaman Ringkasan Penugasan Guru (tabel interaktif Filament). Tabel
 * sendiri me-load baris via query-nya; service ini hanya menyediakan agregat
 * (stat cards + red flag "kelas tanpa penugasan aktif").
 *
 * Catatan timezone: app tz = UTC, "hari ini" dari sudut user = WIB (Asia/Jakarta).
 * Status aktif = ends_at NULL atau >= hari ini WIB.
 */
class RingkasanPenugasanGuruService
{
    /** @return array{term_label: string, stats: array<string, int>} */
    public function stats(?int $academicTermId): array
    {
        $term = $academicTermId
            ? AcademicTerm::with('academicYear')->find($academicTermId)
            : AcademicTerm::with('academicYear')->where('is_active', true)->first()
                ?? AcademicTerm::with('academicYear')->orderByDesc('starts_at')->first();

        $termLabel = $term
            ? trim(($term->academicYear?->name ?? '-').' - '.$term->name)
            : '-';

        if (! $term) {
            return ['term_label' => $termLabel, 'stats' => $this->zeroStats()];
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $totalClassrooms = ClassroomTerm::where('academic_term_id', $term->id)->count();

        $base = DiniyyahTeacherAssignment::query()
            ->whereHas('classSubject.classroomTerm', fn ($q) => $q->where('academic_term_id', $term->id));

        $totalAssignments = (clone $base)->count();
        $totalActive = (clone $base)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->count();
        $totalTeachersUnique = (clone $base)->distinct('teacher_id')->count('teacher_id');

        $totalSubjects = DiniyyahClassSubject::query()
            ->whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $term->id))
            ->where('is_active', true)
            ->has('teacherAssignments')
            ->distinct('subject_id')
            ->count('subject_id');

        $classesWithoutAssignment = ClassroomTerm::where('academic_term_id', $term->id)
            ->whereDoesntHave('diniyyahClassSubjects.teacherAssignments', fn ($q) => $q
                ->where(fn ($x) => $x->whereNull('ends_at')->orWhere('ends_at', '>=', $today)))
            ->count();

        return [
            'term_label' => $termLabel,
            'stats' => [
                'total_classrooms' => $totalClassrooms,
                'total_assignments' => $totalAssignments,
                'total_active' => $totalActive,
                'total_inactive' => $totalAssignments - $totalActive,
                'total_teachers_unique' => $totalTeachersUnique,
                'total_subjects' => $totalSubjects,
                'classes_without_assignment' => $classesWithoutAssignment,
            ],
        ];
    }

    /** @return array<string, int> */
    private function zeroStats(): array
    {
        return [
            'total_classrooms' => 0,
            'total_assignments' => 0,
            'total_active' => 0,
            'total_inactive' => 0,
            'total_teachers_unique' => 0,
            'total_subjects' => 0,
            'classes_without_assignment' => 0,
        ];
    }
}