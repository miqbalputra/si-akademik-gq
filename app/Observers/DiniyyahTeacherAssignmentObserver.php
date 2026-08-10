<?php

namespace App\Observers;

use App\Models\DiniyyahTeacherAssignment;
use App\Services\DiniyyahScheduleChangeLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan penugasan guru diniyyah ke riwayat audit. Lihat
 * {@see DiniyyahTeachingScheduleObserver} untuk rationale skip-no-auth & event
 * `deleting`.
 */
class DiniyyahTeacherAssignmentObserver
{
    public function __construct(private readonly DiniyyahScheduleChangeLogger $logger)
    {
    }

    public function created(DiniyyahTeacherAssignment $assignment): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->logAssignmentCreated($assignment);
    }

    public function updated(DiniyyahTeacherAssignment $assignment): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->logAssignmentUpdated($assignment, $assignment->getOriginal());
    }

    public function deleting(DiniyyahTeacherAssignment $assignment): void
    {
        $this->logger->logAssignmentDeleted($assignment);
    }
}