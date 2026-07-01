<?php

namespace App\Filament\Resources\HomeroomAssignments;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\HomeroomAssignments\Pages\CreateHomeroomAssignment;
use App\Filament\Resources\HomeroomAssignments\Pages\EditHomeroomAssignment;
use App\Filament\Resources\HomeroomAssignments\Pages\ListHomeroomAssignments;
use App\Filament\Resources\HomeroomAssignments\Schemas\HomeroomAssignmentForm;
use App\Filament\Resources\HomeroomAssignments\Tables\HomeroomAssignmentsTable;
use App\Models\HomeroomAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomeroomAssignmentResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Struktur Kelas';

    protected const NAVIGATION_LABEL = 'Wali Kelas';

    protected const NAVIGATION_SORT = 40;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = HomeroomAssignment::class;

    protected static ?string $modelLabel = 'Wali Kelas';
    protected static ?string $pluralModelLabel = 'Wali Kelas';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return HomeroomAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeroomAssignmentsTable::configure($table);
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
            'index' => ListHomeroomAssignments::route('/'),
            'create' => CreateHomeroomAssignment::route('/create'),
            'edit' => EditHomeroomAssignment::route('/{record}/edit'),
        ];
    }
}
