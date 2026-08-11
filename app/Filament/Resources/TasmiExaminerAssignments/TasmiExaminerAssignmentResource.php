<?php

namespace App\Filament\Resources\TasmiExaminerAssignments;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TasmiExaminerAssignments\Pages\CreateTasmiExaminerAssignment;
use App\Filament\Resources\TasmiExaminerAssignments\Pages\EditTasmiExaminerAssignment;
use App\Filament\Resources\TasmiExaminerAssignments\Pages\ListTasmiExaminerAssignments;
use App\Filament\Resources\TasmiExaminerAssignments\Schemas\TasmiExaminerAssignmentForm;
use App\Filament\Resources\TasmiExaminerAssignments\Tables\TasmiExaminerAssignmentsTable;
use App\Models\TasmiExaminerAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TasmiExaminerAssignmentResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';
    protected const NAVIGATION_LABEL = 'Penugasan PJ Tasmi\'';
    protected const NAVIGATION_SORT = 40;
    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TasmiExaminerAssignment::class;

    protected static ?string $modelLabel = 'Penugasan PJ Tasmi\'';
    protected static ?string $pluralModelLabel = 'Penugasan PJ Tasmi\'';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    public static function form(Schema $schema): Schema
    {
        return TasmiExaminerAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasmiExaminerAssignmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasmiExaminerAssignments::route('/'),
            'create' => CreateTasmiExaminerAssignment::route('/create'),
            'edit' => EditTasmiExaminerAssignment::route('/{record}/edit'),
        ];
    }
}