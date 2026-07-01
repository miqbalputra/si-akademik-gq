<?php

namespace App\Filament\Resources\SchoolHolidays;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\SchoolHolidays\Pages\CreateSchoolHoliday;
use App\Filament\Resources\SchoolHolidays\Pages\EditSchoolHoliday;
use App\Filament\Resources\SchoolHolidays\Pages\ListSchoolHolidays;
use App\Filament\Resources\SchoolHolidays\Schemas\SchoolHolidayForm;
use App\Filament\Resources\SchoolHolidays\Tables\SchoolHolidaysTable;
use App\Models\SchoolHoliday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SchoolHolidayResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Data Sekolah';

    protected const NAVIGATION_LABEL = 'Libur Sekolah';

    protected const NAVIGATION_SORT = 35;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = SchoolHoliday::class;

    protected static ?string $modelLabel = 'Libur Sekolah';
    protected static ?string $pluralModelLabel = 'Libur Sekolah';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SchoolHolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolHolidaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolHolidays::route('/'),
            'create' => CreateSchoolHoliday::route('/create'),
            'edit' => EditSchoolHoliday::route('/{record}/edit'),
        ];
    }
}
