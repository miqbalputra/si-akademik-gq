<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules;

use App\Filament\Resources\DiniyyahTeachingSchedules\Pages\CreateDiniyyahTeachingSchedule;
use App\Filament\Resources\DiniyyahTeachingSchedules\Pages\EditDiniyyahTeachingSchedule;
use App\Filament\Resources\DiniyyahTeachingSchedules\Pages\ListDiniyyahTeachingSchedules;
use App\Filament\Resources\DiniyyahTeachingSchedules\Schemas\DiniyyahTeachingScheduleForm;
use App\Filament\Resources\DiniyyahTeachingSchedules\Tables\DiniyyahTeachingSchedulesTable;
use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Models\DiniyyahTeachingSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahTeachingScheduleResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected static ?string $model = DiniyyahTeachingSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik Diniyyah';

    protected static ?string $modelLabel = 'Jadwal Mengajar';
    protected static ?string $pluralModelLabel = 'Jadwal Mengajar';

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    public static function form(Schema $schema): Schema
    {
        return DiniyyahTeachingScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahTeachingSchedulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiniyyahTeachingSchedules::route('/'),
            'create' => CreateDiniyyahTeachingSchedule::route('/create'),
            'edit' => EditDiniyyahTeachingSchedule::route('/{record}/edit'),
        ];
    }
}
