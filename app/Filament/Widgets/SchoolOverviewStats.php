<?php

namespace App\Filament\Widgets;

use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SchoolOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan Sekolah';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Santri Aktif', number_format(Student::where('status', 'active')->count()))
                ->icon(Heroicon::OutlinedAcademicCap)
                ->description('Data siswa aktif'),
            Stat::make('Guru Aktif', number_format(Teacher::where('status', 'active')->count()))
                ->icon(Heroicon::OutlinedUserGroup)
                ->description('Guru terdaftar aktif'),
            Stat::make('Wali Terhubung', number_format(Guardian::whereNotNull('user_id')->count()))
                ->icon(Heroicon::OutlinedUsers)
                ->description('Wali dengan akun login'),
            Stat::make('Kelas Aktif', number_format(ClassroomTerm::where('status', 'active')->count()))
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->description('Kelas pada periode aktif'),
        ];
    }
}
