<?php

namespace App\Observers;

use App\Models\HomeroomAssignment;
use App\Services\NotificationDispatcher;

class HomeroomAssignmentObserver
{
    public function created(HomeroomAssignment $assignment): void
    {
        $className = $assignment->classroomTerm?->name ?? 'kelas';
        $termName = $assignment->classroomTerm?->academicTerm?->name ?? 'periode aktif';

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Penugasan wali kelas baru',
            "Anda ditugaskan sebagai wali kelas {$className} untuk {$termName}. Menu Presensi kini tersedia.",
            'assignment_created',
            route('attendance.index'),
            'info',
        );
    }

    public function updated(HomeroomAssignment $assignment): void
    {
        // Bila guru berubah (pindah tugas).
        if ($assignment->wasChanged('teacher_id')) {
            $oldTeacherId = (int) $assignment->getOriginal('teacher_id');
            $newTeacherId = (int) $assignment->teacher_id;
            $className = $assignment->classroomTerm?->name ?? 'kelas';

            if ($oldTeacherId && $oldTeacherId !== $newTeacherId) {
                app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
                    $oldTeacherId,
                    'Penugasan wali kelas dicabut',
                    "Penugasan wali kelas {$className} dicabut/dipindahkan.",
                    'assignment_removed',
                    route('guru.dashboard'),
                    'warning',
                );
            }
            if ($newTeacherId) {
                app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
                    $newTeacherId,
                    'Penugasan wali kelas baru',
                    "Anda ditugaskan sebagai wali kelas {$className}.",
                    'assignment_created',
                    route('attendance.index'),
                    'info',
                );
            }
        }
    }

    public function deleted(HomeroomAssignment $assignment): void
    {
        $className = $assignment->classroomTerm?->name ?? 'kelas';

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Penugasan wali kelas dicabut',
            "Penugasan wali kelas {$className} dicabut oleh admin.",
            'assignment_removed',
            route('guru.dashboard'),
            'warning',
        );
    }
}