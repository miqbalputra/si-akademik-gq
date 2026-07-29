<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahTeacherAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan data penugasan guru Diniyyah, dikelompokkan per kelas (classroomTerm).
 *
 * Tujuan: audit relasi kelas <-> guru <-> mapel <-> peran <-> tanggal + jadwal
 * mengajar, supaya admin mudah melihat kekeliruan/perubahan.
 *
 * Catatan timezone: app tz = UTC, "hari ini" dari sudut user = WIB (Asia/Jakarta).
 * Penentuan aktif/berakhir pakai wibToday() eksplisit, bukan now() server.
 */
class RingkasanPenugasanGuruService
{
    /** @return array<string, mixed> */
    public function build(?int $academicTermId): array
    {
        $term = $academicTermId
            ? AcademicTerm::with('academicYear')->find($academicTermId)
            : AcademicTerm::with('academicYear')->where('is_active', true)->first()
                ?? AcademicTerm::with('academicYear')->orderByDesc('starts_at')->first();

        $termLabel = $term
            ? trim(($term->academicYear?->name ?? '-').' - '.$term->name)
            : '-';

        if (! $term) {
            return $this->emptyResult($termLabel);
        }

        $today = $this->wibToday();

        $classroomTerms = ClassroomTerm::query()
            ->where('academic_term_id', $term->id)
            ->with([
                'classroom',
                'academicTerm.academicYear',
                'diniyyahClassSubjects' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'diniyyahClassSubjects.subject',
                'diniyyahClassSubjects.teacherAssignments' => fn ($q) => $q->orderBy('assignment_role'),
                'diniyyahClassSubjects.teacherAssignments.teacher',
                'diniyyahClassSubjects.teacherAssignments.schedules.classSession',
            ])
            ->orderBy('name')
            ->get();

        $classrooms = collect();
        $totalAssignments = 0;
        $totalActive = 0;
        $totalInactive = 0;
        $teacherIds = [];
        $subjectIds = [];
        $classesWithoutActiveAssignment = 0;

        foreach ($classroomTerms as $ct) {
            $rows = collect();
            $hasActiveInClass = false;

            foreach ($ct->diniyyahClassSubjects as $cs) {
                if ($cs->subject) {
                    $subjectIds[$cs->subject->id] = true;
                }

                foreach ($cs->teacherAssignments as $ta) {
                    /** @var DiniyyahTeacherAssignment $ta */
                    $isActive = $this->assignmentIsActive($ta, $today);
                    if ($ta->teacher) {
                        $teacherIds[$ta->teacher->id] = true;
                    }
                    if ($isActive) {
                        $hasActiveInClass = true;
                    }

                    $totalAssignments++;
                    if ($isActive) {
                        $totalActive++;
                    } else {
                        $totalInactive++;
                    }

                    $rows->push([
                        'subject_name' => $cs->subject?->name ?? '(mapel dihapus)',
                        'teacher_name' => $ta->teacher?->name ?? '(guru belum diisi)',
                        'assignment_role' => $ta->assignment_role,
                        'starts_at' => $ta->starts_at?->format('d M Y'),
                        'ends_at' => $ta->ends_at?->format('d M Y'),
                        'is_active' => $isActive,
                        'schedules' => $this->formatSchedules($ta->schedules ?? collect()),
                    ]);
                }
            }

            if (! $hasActiveInClass) {
                $classesWithoutActiveAssignment++;
            }

            $classrooms->push([
                'classroom_term' => $ct,
                'label' => $ct->name ?? '-',
                'classroom_name' => $ct->classroom?->name ?? '-',
                'rows' => $rows,
            ]);
        }

        return [
            'term' => $term,
            'term_label' => $termLabel,
            'classrooms' => $classrooms,
            'stats' => [
                'total_classrooms' => $classroomTerms->count(),
                'total_assignments' => $totalAssignments,
                'total_active' => $totalActive,
                'total_inactive' => $totalInactive,
                'total_teachers_unique' => count($teacherIds),
                'total_subjects' => count($subjectIds),
                'classes_without_assignment' => $classesWithoutActiveAssignment,
            ],
        ];
    }

    /**
     * Format jadwal mengajar: "Senin 07:00-08:00", urut by hari lalu jam mulai.
     *
     * @param  Collection<int, \App\Models\DiniyyahTeachingSchedule>  $schedules
     * @return list<string>
     */
    private function formatSchedules(Collection $schedules): array
    {
        $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

        $items = $schedules
            ->filter(fn ($s) => $s->classSession && ! $s->classSession->is_break)
            ->map(function ($s) use ($days): array {
                $session = $s->classSession;
                $start = $session->starts_at ? Carbon::parse($session->starts_at)->format('H:i') : '?';
                $end = $session->ends_at ? Carbon::parse($session->ends_at)->format('H:i') : '?';
                $day = $days[$s->day_of_week] ?? '?';
                $sort = str_pad((string) $s->day_of_week, 2, '0', STR_PAD_LEFT).'|'.($session->starts_at ?? '');

                return [
                    'sort' => $sort,
                    'text' => "{$day} {$start}-{$end}",
                ];
            })
            ->sortBy('sort')
            ->values();

        return $items->map(fn ($i) => $i['text'])->all();
    }

    private function assignmentIsActive(DiniyyahTeacherAssignment $ta, string $today): bool
    {
        return $ta->ends_at === null || $ta->ends_at->greaterThanOrEqualTo($today);
    }

    private function wibToday(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    /** @return array<string, mixed> */
    private function emptyResult(string $termLabel): array
    {
        return [
            'term' => null,
            'term_label' => $termLabel,
            'classrooms' => collect(),
            'stats' => [
                'total_classrooms' => 0,
                'total_assignments' => 0,
                'total_active' => 0,
                'total_inactive' => 0,
                'total_teachers_unique' => 0,
                'total_subjects' => 0,
                'classes_without_assignment' => 0,
            ],
        ];
    }
}