<?php

namespace App\Filament\Resources\DiniyyahScoreValidations;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahScoreValidations\Pages\CreateDiniyyahScoreValidation;
use App\Filament\Resources\DiniyyahScoreValidations\Pages\EditDiniyyahScoreValidation;
use App\Filament\Resources\DiniyyahScoreValidations\Pages\ListDiniyyahScoreValidations;
use App\Filament\Resources\DiniyyahScoreValidations\Schemas\DiniyyahScoreValidationForm;
use App\Filament\Resources\DiniyyahScoreValidations\Tables\DiniyyahScoreValidationsTable;
use App\Models\DiniyyahScoreValidation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahScoreValidationResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Validasi Nilai';

    protected const NAVIGATION_SORT = 80;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahScoreValidation::class;

    protected static ?string $modelLabel = 'Validasi Nilai';
    protected static ?string $pluralModelLabel = 'Validasi Nilai';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahScoreValidationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahScoreValidationsTable::configure($table);
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
            'index' => ListDiniyyahScoreValidations::route('/'),
            'create' => CreateDiniyyahScoreValidation::route('/create'),
            'edit' => EditDiniyyahScoreValidation::route('/{record}/edit'),
        ];
    }
}
