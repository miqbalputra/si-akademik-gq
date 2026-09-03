<?php

namespace App\Filament\Resources\DiniyyahScheduleChangeLogs;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahScheduleChangeLogs\Pages\ListDiniyyahScheduleChangeLogs;
use App\Filament\Resources\DiniyyahScheduleChangeLogs\Tables\DiniyyahScheduleChangeLogsTable;
use App\Models\DiniyyahScheduleChangeLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Riwayat perubahan jadwal mengajar & penugasan diniyyah (read-only audit log).
 * MANAGE_ROLES kosong → canCreate()=false (CreateAction hidden) & canDeleteAny()
 * =false (bulk delete disabled). Tidak ada halaman Create/Edit.
 */
class DiniyyahScheduleChangeLogResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected static ?string $model = DiniyyahScheduleChangeLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Diniyyah';

    protected static ?string $modelLabel = 'Riwayat Perubahan Jadwal';

    protected static ?string $pluralModelLabel = 'Riwayat Perubahan Jadwal';

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = [];

    protected static ?int $navigationSort = 32;

    public static function table(Table $table): Table
    {
        return DiniyyahScheduleChangeLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiniyyahScheduleChangeLogs::route('/'),
        ];
    }
}
