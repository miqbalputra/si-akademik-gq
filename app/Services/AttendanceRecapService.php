<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\ReportCardAttendance;
use App\Models\StudentAttendance;

class AttendanceRecapService
{
    /** @return array{sick_count: int, permission_count: int, absent_count: int} */
    public function recapForEnrollment(int $academicTermId, int $classEnrollmentId): array
    {
        $counts = StudentAttendance::query()
            ->where('academic_term_id', $academicTermId)
            ->where('class_enrollment_id', $classEnrollmentId)
            ->whereIn('status', StudentAttendance::recapStatuses())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'sick_count' => (int) ($counts[StudentAttendance::STATUS_SICK] ?? 0),
            'permission_count' => (int) ($counts[StudentAttendance::STATUS_PERMISSION] ?? 0),
            'absent_count' => (int) ($counts[StudentAttendance::STATUS_ABSENT] ?? 0),
        ];
    }

    /** @return array{sick_count: int, permission_count: int, absent_count: int} */
    public function recapForReportCard(ReportCard $reportCard): array
    {
        return $this->recapForEnrollment($reportCard->academic_term_id, $reportCard->class_enrollment_id);
    }

    public function syncReportCardAttendance(ReportCard $reportCard): ReportCardAttendance
    {
        return $reportCard->attendance()->updateOrCreate(
            ['report_card_id' => $reportCard->id],
            $this->recapForReportCard($reportCard),
        );
    }
}
