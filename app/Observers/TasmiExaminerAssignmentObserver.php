<?php

namespace App\Observers;

use App\Models\TasmiExaminerAssignment;
use App\Services\NotificationDispatcher;

class TasmiExaminerAssignmentObserver
{
    public function created(TasmiExaminerAssignment $assignment): void
    {
        if ($assignment->status !== 'active') {
            return;
        }

        $termName = $assignment->academicTerm?->name ?? 'periode aktif';

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Penugasan PJ Tasmi\' baru',
            "Anda ditugaskan sebagai PJ Tasmi' untuk {$termName}. Menu Tasmi' kini tersedia di dashboard Anda.",
            'assignment_created',
            route('guru.tasmi.index'),
            'info',
        );
    }

    public function updated(TasmiExaminerAssignment $assignment): void
    {
        if (! $assignment->wasChanged('status')) {
            return;
        }

        $status = $assignment->status;
        $termName = $assignment->academicTerm?->name ?? 'periode aktif';
        $severity = $status === 'active' ? 'info' : 'warning';
        $body = $status === 'active'
            ? "Penugasan PJ Tasmi' Anda untuk {$termName} diaktifkan kembali."
            : "Penugasan PJ Tasmi' Anda untuk {$termName} dinonaktifkan. Menu Tasmi' disembunyikan dari dashboard.";

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Status penugasan PJ Tasmi\' diperbarui',
            $body,
            'assignment_updated',
            route('guru.tasmi.index'),
            $severity,
        );
    }

    public function deleted(TasmiExaminerAssignment $assignment): void
    {
        $termName = $assignment->academicTerm?->name ?? 'periode aktif';

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Penugasan PJ Tasmi\' dicabut',
            "Penugasan PJ Tasmi' Anda untuk {$termName} dicabut oleh admin.",
            'assignment_removed',
            route('guru.dashboard'),
            'warning',
        );
    }
}