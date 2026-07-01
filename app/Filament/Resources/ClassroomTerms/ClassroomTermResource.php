<?php

namespace App\Filament\Resources\ClassroomTerms;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\ClassroomTerms\Pages\CreateClassroomTerm;
use App\Filament\Resources\ClassroomTerms\Pages\EditClassroomTerm;
use App\Filament\Resources\ClassroomTerms\Pages\ListClassroomTerms;
use App\Filament\Resources\ClassroomTerms\Schemas\ClassroomTermForm;
use App\Filament\Resources\ClassroomTerms\Tables\ClassroomTermsTable;
use App\Models\ClassroomTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClassroomTermResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Struktur Kelas';

    protected const NAVIGATION_LABEL = 'Kelas Per Periode';

    protected const NAVIGATION_SORT = 20;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = ClassroomTerm::class;

    protected static ?string $modelLabel = 'Periode Kelas';
    protected static ?string $pluralModelLabel = 'Periode Kelas';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClassroomTermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassroomTermsTable::configure($table);
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
            'index' => ListClassroomTerms::route('/'),
            'create' => CreateClassroomTerm::route('/create'),
            'edit' => EditClassroomTerm::route('/{record}/edit'),
        ];
    }
}
