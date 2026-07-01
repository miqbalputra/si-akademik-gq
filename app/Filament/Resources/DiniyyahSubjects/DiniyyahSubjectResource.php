<?php

namespace App\Filament\Resources\DiniyyahSubjects;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahSubjects\Pages\CreateDiniyyahSubject;
use App\Filament\Resources\DiniyyahSubjects\Pages\EditDiniyyahSubject;
use App\Filament\Resources\DiniyyahSubjects\Pages\ListDiniyyahSubjects;
use App\Filament\Resources\DiniyyahSubjects\Schemas\DiniyyahSubjectForm;
use App\Filament\Resources\DiniyyahSubjects\Tables\DiniyyahSubjectsTable;
use App\Models\DiniyyahSubject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DiniyyahSubjectResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Mata Pelajaran';

    protected const NAVIGATION_SORT = 10;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahSubject::class;

    protected static ?string $modelLabel = 'Mata Pelajaran';
    protected static ?string $pluralModelLabel = 'Mata Pelajaran';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahSubjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahSubjectsTable::configure($table);
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
            'index' => ListDiniyyahSubjects::route('/'),
            'create' => CreateDiniyyahSubject::route('/create'),
            'edit' => EditDiniyyahSubject::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
