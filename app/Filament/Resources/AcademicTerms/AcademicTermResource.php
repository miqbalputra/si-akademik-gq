<?php

namespace App\Filament\Resources\AcademicTerms;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\AcademicTerms\Pages\CreateAcademicTerm;
use App\Filament\Resources\AcademicTerms\Pages\EditAcademicTerm;
use App\Filament\Resources\AcademicTerms\Pages\ListAcademicTerms;
use App\Filament\Resources\AcademicTerms\Schemas\AcademicTermForm;
use App\Filament\Resources\AcademicTerms\Tables\AcademicTermsTable;
use App\Models\AcademicTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AcademicTermResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Data Sekolah';

    protected const NAVIGATION_LABEL = 'Periode';

    protected const NAVIGATION_SORT = 30;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = AcademicTerm::class;

    protected static ?string $modelLabel = 'Periode Akademik';
    protected static ?string $pluralModelLabel = 'Periode Akademik';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AcademicTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicTermsTable::configure($table);
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
            'index' => ListAcademicTerms::route('/'),
            'create' => CreateAcademicTerm::route('/create'),
            'edit' => EditAcademicTerm::route('/{record}/edit'),
        ];
    }
}
