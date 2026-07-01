<?php

namespace App\Filament\Resources\DiniyyahScoreComponents;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahScoreComponents\Pages\CreateDiniyyahScoreComponent;
use App\Filament\Resources\DiniyyahScoreComponents\Pages\EditDiniyyahScoreComponent;
use App\Filament\Resources\DiniyyahScoreComponents\Pages\ListDiniyyahScoreComponents;
use App\Filament\Resources\DiniyyahScoreComponents\Schemas\DiniyyahScoreComponentForm;
use App\Filament\Resources\DiniyyahScoreComponents\Tables\DiniyyahScoreComponentsTable;
use App\Models\DiniyyahScoreComponent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahScoreComponentResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Komponen Nilai';

    protected const NAVIGATION_SORT = 50;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahScoreComponent::class;

    protected static ?string $modelLabel = 'Komponen Nilai';
    protected static ?string $pluralModelLabel = 'Komponen Nilai';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahScoreComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahScoreComponentsTable::configure($table);
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
            'index' => ListDiniyyahScoreComponents::route('/'),
            'create' => CreateDiniyyahScoreComponent::route('/create'),
            'edit' => EditDiniyyahScoreComponent::route('/{record}/edit'),
        ];
    }
}
