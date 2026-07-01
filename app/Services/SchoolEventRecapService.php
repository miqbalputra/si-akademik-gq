<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use Illuminate\Support\Collection;

class SchoolEventRecapService
{
    /** @return array<string, mixed> */
    public function build(SchoolEvent $event): array
    {
        $event->loadMissing(['academicTerm.academicYear', 'targetClassroomTerms.classroom', 'responses.guardian']);

        $targetClassroomTerms = $this->targetClassroomTerms($event);
        $targetEnrollments = ClassEnrollment::query()
            ->with(['student.guardians'])
            ->where('academic_term_id', $event->academic_term_id)
            ->where('status', 'active')
            ->whereIn('classroom_term_id', $targetClassroomTerms->pluck('id'))
            ->get();

        $responsesByGuardian = $event->responses->keyBy('guardian_id');
        $guardianRows = $this->guardianRows($targetEnrollments, $responsesByGuardian);

        $respondedCount = $guardianRows->where('attendance_status', '!=', 'pending')->count();
        $targetGuardianCount = $guardianRows->count();
        $studentCount = $targetEnrollments->pluck('student_id')->unique()->count();

        $fatherRows = $guardianRows->filter(fn (array $row) => in_array('Bapak', $row['relationship_labels'], true));
        $motherRows = $guardianRows->filter(fn (array $row) => in_array('Ibu', $row['relationship_labels'], true));

        return [
            'event' => $event,
            'target_classroom_terms' => $targetClassroomTerms,
            'guardian_rows' => $guardianRows->values(),
            'stats' => [
                'target_students' => $studentCount,
                'target_guardians' => $targetGuardianCount,
                'responded' => $respondedCount,
                'pending' => $guardianRows->where('attendance_status', 'pending')->count(),
                'attending' => $guardianRows->where('attendance_status', 'attending')->count(),
                'permission' => $guardianRows->where('attendance_status', 'permission')->count(),
                'not_attending' => $guardianRows->where('attendance_status', 'not_attending')->count(),
                'response_rate' => $targetGuardianCount > 0 ? round($respondedCount / $targetGuardianCount * 100, 2) : 0.0,
                'father_target' => $fatherRows->count(),
                'father_responded' => $fatherRows->where('attendance_status', '!=', 'pending')->count(),
                'mother_target' => $motherRows->count(),
                'mother_responded' => $motherRows->where('attendance_status', '!=', 'pending')->count(),
            ],
        ];
    }

    /** @return Collection<int, ClassroomTerm> */
    public function targetClassroomTerms(SchoolEvent $event): Collection
    {
        $query = ClassroomTerm::query()
            ->with('classroom')
            ->where('academic_term_id', $event->academic_term_id);

        return match ($event->target_scope) {
            'classes' => $event->targetClassroomTerms()->with('classroom')->get(),
            'level' => $query->whereHas('classroom', fn ($query) => $query->where('level_name', $event->target_level_name))->get(),
            'gender' => $query->whereHas('classroom', fn ($query) => $query->where('gender_group', $event->target_gender_group))->get(),
            'level_gender' => $query->whereHas('classroom', function ($query) use ($event): void {
                $query->where('level_name', $event->target_level_name)
                    ->where('gender_group', $event->target_gender_group);
            })->get(),
            default => $query->get(),
        };
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $targetEnrollments
     * @param  Collection<int, SchoolEventResponse>  $responsesByGuardian
     * @return Collection<int, array<string, mixed>>
     */
    private function guardianRows(Collection $targetEnrollments, Collection $responsesByGuardian): Collection
    {
        $guardians = collect();

        foreach ($targetEnrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student) {
                continue;
            }

            foreach ($student->guardians as $guardian) {
                /** @var Guardian $guardian */
                $existing = $guardians->get($guardian->id, [
                    'guardian_id' => $guardian->id,
                    'guardian_name' => $guardian->name,
                    'guardian_gender' => $this->guardianGenderLabel($guardian->gender),
                    'phone' => $guardian->whatsapp ?: $guardian->phone,
                    'email' => $guardian->email,
                    'student_names' => [],
                    'relationship_labels' => [],
                ]);

                $existing['student_names'][] = $student->name;
                $existing['relationship_labels'][] = $this->relationshipLabel($guardian->pivot?->relationship);

                $guardians->put($guardian->id, $existing);
            }
        }

        return $guardians
            ->map(function (array $row, int $guardianId) use ($responsesByGuardian): array {
                /** @var SchoolEventResponse|null $response */
                $response = $responsesByGuardian->get($guardianId);

                return [
                    ...$row,
                    'student_names' => collect($row['student_names'])->filter()->unique()->values()->all(),
                    'relationship_labels' => collect($row['relationship_labels'])->filter()->unique()->values()->all(),
                    'attendance_status' => $response?->attendance_status ?? 'pending',
                    'attendance_label' => $response?->statusLabel() ?? 'Belum Konfirmasi',
                    'notes' => $response?->notes,
                    'responded_at' => $response?->responded_at,
                ];
            })
            ->sortBy([
                ['attendance_status', 'asc'],
                ['guardian_name', 'asc'],
            ]);
    }

    private function relationshipLabel(?string $relationship): string
    {
        return match ($relationship) {
            'father' => 'Bapak',
            'mother' => 'Ibu',
            default => 'Wali',
        };
    }

    private function guardianGenderLabel(?string $gender): string
    {
        return match ($gender) {
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            default => '-',
        };
    }
}
