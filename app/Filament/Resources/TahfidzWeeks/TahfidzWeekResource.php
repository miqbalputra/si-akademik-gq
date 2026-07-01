<?php

namespace App\Filament\Resources\TahfidzWeeks;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TahfidzWeeks\Pages\CreateTahfidzWeek;
use App\Filament\Resources\TahfidzWeeks\Pages\EditTahfidzWeek;
use App\Filament\Resources\TahfidzWeeks\Pages\ListTahfidzWeeks;
use App\Filament\Resources\TahfidzWeeks\Schemas\TahfidzWeekForm;
use App\Filament\Resources\TahfidzWeeks\Tables\TahfidzWeeksTable;
use App\Models\TahfidzWeek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TahfidzWeekResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';
    protected const NAVIGATION_LABEL = 'Pekan Tahfidz';
    protected const NAVIGATION_SORT = 20;
    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TahfidzWeek::class;

    protected static ?string $modelLabel = 'Pekan Tahfidz';
    protected static ?string $pluralModelLabel = 'Pekan Tahfidz';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    public static function form(Schema $schema): Schema
    {
        return TahfidzWeekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahfidzWeeksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahfidzWeeks::route('/'),
            'create' => CreateTahfidzWeek::route('/create'),
            'edit' => EditTahfidzWeek::route('/{record}/edit'),
        ];
    }
}