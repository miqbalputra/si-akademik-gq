<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class ReportCardBulkWorkflow
{
    public function __construct(
        private readonly ReportCardWorkflow $workflow,
    ) {}

    /** @return array<string, int> */
    public function summaryForSnapshot(DiniyyahLedgerSnapshot $snapshot, bool $includeArchivedStudents = false): array
    {
        $expected = $this->expectedReportCount($snapshot, $includeArchivedStudents);
        $cards = $this->reportCardsForSnapshot($snapshot, $includeArchivedStudents);

        return [
            'expected' => $expected,
            'total' => $cards->count(),
            'draft' => $cards->where('status', 'draft')->count(),
            'locked' => $cards->where('status', 'locked')->count(),
            'published' => $cards->where('status', 'published')->count(),
            'missing' => max($expected - $cards->count(), 0),
        ];
    }

    /** @return array<string, int> */
    public function lockForSnapshot(DiniyyahLedgerSnapshot $snapshot, User $user): array
    {
        $includeArchivedStudents = $user->hasRole('admin');
        $summary = $this->summaryForSnapshot($snapshot, $includeArchivedStudents);

        if ($summary['missing'] > 0) {
            throw new DomainException('Generate semua rapor terlebih dahulu sebelum lock massal.');
        }

        return $this->lockMany($this->reportCardsForSnapshot($snapshot, $includeArchivedStudents), $user);
    }

    /** @return array<string, int> */
    public function publishForSnapshot(DiniyyahLedgerSnapshot $snapshot, User $user): array
    {
        $includeArchivedStudents = $user->hasRole('admin');
        $summary = $this->summaryForSnapshot($snapshot, $includeArchivedStudents);

        if ($summary['missing'] > 0) {
            throw new DomainException('Generate semua rapor terlebih dahulu sebelum publish massal.');
        }

        if ($summary['draft'] > 0) {
            throw new DomainException('Lock semua rapor terlebih dahulu sebelum publish massal.');
        }

        return $this->publishMany($this->reportCardsForSnapshot($snapshot, $includeArchivedStudents), $user);
    }

    /**
     * @param  Collection<int, ReportCard>  $reportCards
     * @return array<string, int>
     */
    public function lockMany(Collection $reportCards, User $user): array
    {
        $result = ['locked' => 0, 'skipped' => 0];
        $reportCards = $this->visibleCardsForUser($reportCards, $user);

        foreach ($reportCards as $reportCard) {
            if ($reportCard->status !== 'draft') {
                $result['skipped']++;

                continue;
            }

            $this->workflow->lock($reportCard, $user);
            $result['locked']++;
        }

        return $result;
    }

    /**
     * @param  Collection<int, ReportCard>  $reportCards
     * @return array<string, int>
     */
    public function publishMany(Collection $reportCards, User $user): array
    {
        $result = ['published' => 0, 'skipped' => 0];
        $reportCards = $this->visibleCardsForUser($reportCards, $user);

        foreach ($reportCards as $reportCard) {
            if ($reportCard->status !== 'locked') {
                $result['skipped']++;

                continue;
            }

            $this->workflow->publish($reportCard, $user);
            $result['published']++;
        }

        return $result;
    }

    /** @return Collection<int, ReportCard> */
    private function reportCardsForSnapshot(DiniyyahLedgerSnapshot $snapshot, bool $includeArchivedStudents): Collection
    {
        return ReportCard::query()
            ->where('academic_term_id', $snapshot->academic_term_id)
            ->where('classroom_term_id', $snapshot->classroom_term_id)
            ->where('report_type', 'diniyyah')
            ->when(! $includeArchivedStudents, fn ($query) => $query->whereHas('student', fn ($students) => $students->where('status', 'active')))
            ->get();
    }

    private function expectedReportCount(DiniyyahLedgerSnapshot $snapshot, bool $includeArchivedStudents): int
    {
        $rows = $snapshot->rows()->with('classEnrollment.student')->get();

        if (! $includeArchivedStudents) {
            $rows = $rows->filter(fn ($row): bool => $row->classEnrollment?->student?->status === 'active');
        }

        return $rows
            ->whereNotNull('rank_in_class')
            ->whereNotNull('total_diniyyah_score')
            ->whereNotNull('average_diniyyah_score')
            ->count();
    }

    /** @param Collection<int, ReportCard> $reportCards
     *  @return Collection<int, ReportCard>
     */
    private function visibleCardsForUser(Collection $reportCards, User $user): Collection
    {
        return $user->hasRole('admin')
            ? $reportCards
            : $reportCards->filter(fn (ReportCard $card): bool => $card->student()->where('status', 'active')->exists());
    }
}
