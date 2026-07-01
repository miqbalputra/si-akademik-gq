<?php

namespace App\Filament\Resources\TahfidzUasDays;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TahfidzUasDays\Pages\CreateTahfidzUasDay;
use App\Filament\Resources\TahfidzUasDays\Pages\EditTahfidzUasDay;
use App\Filament\Resources\TahfidzUasDays\Pages\ListTahfidzUasDays;
use App\Filament\Resources\TahfidzUasDays\Schemas\TahfidzUasDayForm;
use App\Filament\Resources\TahfidzUasDays\Tables\TahfidzUasDaysTable;
use App\Models\TahfidzUasDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TahfidzUasDayResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';
    protected const NAVIGATION_LABEL = 'Jadwal UAS';
    protected const NAVIGATION_SORT = 40;
    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TahfidzUasDay::class;

    protected static ?string $modelLabel = 'Jadwal UAS';
    protected static ?string $pluralModelLabel = 'Jadwal UAS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return TahfidzUasDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahfidzUasDaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahfidzUasDays::route('/'),
            'create' => CreateTahfidzUasDay::route('/create'),
            'edit' => EditTahfidzUasDay::route('/{record}/edit'),
        ];
    }
}