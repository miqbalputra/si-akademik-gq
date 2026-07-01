<?php

namespace App\Filament\Resources\DiniyyahClassSubjects;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahClassSubjects\Pages\CreateDiniyyahClassSubject;
use App\Filament\Resources\DiniyyahClassSubjects\Pages\EditDiniyyahClassSubject;
use App\Filament\Resources\DiniyyahClassSubjects\Pages\ListDiniyyahClassSubjects;
use App\Filament\Resources\DiniyyahClassSubjects\Schemas\DiniyyahClassSubjectForm;
use App\Filament\Resources\DiniyyahClassSubjects\Tables\DiniyyahClassSubjectsTable;
use App\Models\DiniyyahClassSubject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DiniyyahClassSubjectResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Mapel Per Kelas';

    protected const NAVIGATION_SORT = 20;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahClassSubject::class;

    protected static ?string $modelLabel = 'Mapel Per Kelas';
    protected static ?string $pluralModelLabel = 'Mapel Per Kelas';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahClassSubjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahClassSubjectsTable::configure($table);
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
            'index' => ListDiniyyahClassSubjects::route('/'),
            'create' => CreateDiniyyahClassSubject::route('/create'),
            'edit' => EditDiniyyahClassSubject::route('/{record}/edit'),
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
