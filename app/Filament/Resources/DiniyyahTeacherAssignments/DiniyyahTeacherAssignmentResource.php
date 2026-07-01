<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahTeacherAssignments\Pages\CreateDiniyyahTeacherAssignment;
use App\Filament\Resources\DiniyyahTeacherAssignments\Pages\EditDiniyyahTeacherAssignment;
use App\Filament\Resources\DiniyyahTeacherAssignments\Pages\ListDiniyyahTeacherAssignments;
use App\Filament\Resources\DiniyyahTeacherAssignments\Schemas\DiniyyahTeacherAssignmentForm;
use App\Filament\Resources\DiniyyahTeacherAssignments\Tables\DiniyyahTeacherAssignmentsTable;
use App\Models\DiniyyahTeacherAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahTeacherAssignmentResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Penugasan Guru';

    protected const NAVIGATION_SORT = 30;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahTeacherAssignment::class;

    protected static ?string $modelLabel = 'Penugasan Guru';
    protected static ?string $pluralModelLabel = 'Penugasan Guru';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahTeacherAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahTeacherAssignmentsTable::configure($table);
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
            'index' => ListDiniyyahTeacherAssignments::route('/'),
            'create' => CreateDiniyyahTeacherAssignment::route('/create'),
            'edit' => EditDiniyyahTeacherAssignment::route('/{record}/edit'),
        ];
    }
}
