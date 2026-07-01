<?php

namespace App\Filament\Widgets;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiniyyahWorkflowStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Alur Diniyyah';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Set Penilaian', number_format(DiniyyahAssessmentSet::count()))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->description('Total set nilai Diniyyah'),
            Stat::make('Menunggu Validasi', number_format(DiniyyahAssessmentSet::where('status', 'submitted')->count()))
                ->icon(Heroicon::OutlinedClock)
                ->description('Nilai sudah disubmit guru'),
            Stat::make('Leger Belum Lock', number_format(DiniyyahLedgerSnapshot::whereIn('status', ['draft', 'validated'])->count()))
                ->icon(Heroicon::OutlinedTableCells)
                ->description('Leger masih bisa diproses'),
            Stat::make('Rapor Published', number_format(ReportCard::where('status', 'published')->count()))
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->description('Sudah terlihat oleh wali'),
        ];
    }
}
