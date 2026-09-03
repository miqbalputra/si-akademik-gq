<?php

namespace App\Filament\Widgets;

use App\Models\AcademicTerm;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TahfidzWorkflowStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Koordinasi Tahfidz';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_tahfidz']) ?? false;
    }

    protected function getStats(): array
    {
        $termId = AcademicTerm::query()->where('is_active', true)->latest('id')->value('id');

        return [
            Stat::make('Halaqah Aktif', number_format($termId ? TahfidzHalaqah::query()->where('academic_term_id', $termId)->where('status', 'active')->count() : 0))
                ->icon(Heroicon::OutlinedUserGroup)
                ->description('Periode akademik aktif'),
            Stat::make('Santri Halaqah', number_format($termId ? TahfidzHalaqahMember::query()->where('status', 'active')->whereHas('halaqah', fn ($query) => $query->where('academic_term_id', $termId))->count() : 0))
                ->icon(Heroicon::OutlinedAcademicCap)
                ->description('Anggota aktif'),
            Stat::make("Hasil Tasmi'", number_format($termId ? TasmiRecord::query()->where('academic_term_id', $termId)->count() : 0))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->description('Setoran tercatat'),
            Stat::make("PJ Tasmi'", number_format($termId ? TasmiExaminerAssignment::query()->where('academic_term_id', $termId)->where('status', 'active')->count() : 0))
                ->icon(Heroicon::OutlinedUserPlus)
                ->description('Penguji aktif'),
        ];
    }
}
