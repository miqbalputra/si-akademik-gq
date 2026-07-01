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
    public function summaryForSnapshot(DiniyyahLedgerSnapshot $snapshot): array
    {
        $expected = $this->expectedReportCount($snapshot);
        $cards = $this->reportCardsForSnapshot($snapshot);

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
        $summary = $this->summaryForSnapshot($snapshot);

        if ($summary['missing'] > 0) {
            throw new DomainException('Generate semua rapor terlebih dahulu sebelum lock massal.');
        }

        return $this->lockMany($this->reportCardsForSnapshot($snapshot), $user);
    }

    /** @return array<string, int> */
    public function publishForSnapshot(DiniyyahLedgerSnapshot $snapshot, User $user): array
    {
        $summary = $this->summaryForSnapshot($snapshot);

        if ($summary['missing'] > 0) {
            throw new DomainException('Generate semua rapor terlebih dahulu sebelum publish massal.');
        }

        if ($summary['draft'] > 0) {
            throw new DomainException('Lock semua rapor terlebih dahulu sebelum publish massal.');
        }

        return $this->publishMany($this->reportCardsForSnapshot($snapshot), $user);
    }

    /**
     * @param  Collection<int, ReportCard>  $reportCards
     * @return array<string, int>
     */
    public function lockMany(Collection $reportCards, User $user): array
    {
        $result = ['locked' => 0, 'skipped' => 0];

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
    private function reportCardsForSnapshot(DiniyyahLedgerSnapshot $snapshot): Collection
    {
        return ReportCard::query()
            ->where('academic_term_id', $snapshot->academic_term_id)
            ->where('classroom_term_id', $snapshot->classroom_term_id)
            ->where('report_type', 'diniyyah')
            ->get();
    }

    private function expectedReportCount(DiniyyahLedgerSnapshot $snapshot): int
    {
        $snapshot->loadMissing('rows');

        return $snapshot->rows
            ->whereNotNull('rank_in_class')
            ->whereNotNull('total_diniyyah_score')
            ->whereNotNull('average_diniyyah_score')
            ->count();
    }
}
