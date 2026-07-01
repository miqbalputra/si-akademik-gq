<?php

namespace App\Filament\Resources\TahfidzHalaqahs;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TahfidzHalaqahs\Pages\CreateTahfidzHalaqah;
use App\Filament\Resources\TahfidzHalaqahs\Pages\EditTahfidzHalaqah;
use App\Filament\Resources\TahfidzHalaqahs\Pages\ListTahfidzHalaqahs;
use App\Filament\Resources\TahfidzHalaqahs\RelationManagers\MembersRelationManager;
use App\Filament\Resources\TahfidzHalaqahs\Schemas\TahfidzHalaqahForm;
use App\Filament\Resources\TahfidzHalaqahs\Tables\TahfidzHalaqahsTable;
use App\Models\TahfidzHalaqah;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TahfidzHalaqahResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';

    protected const NAVIGATION_LABEL = 'Halaqah';

    protected const NAVIGATION_SORT = 10;

    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TahfidzHalaqah::class;

    protected static ?string $modelLabel = 'Halaqah';
    protected static ?string $pluralModelLabel = 'Halaqah';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return TahfidzHalaqahForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahfidzHalaqahsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahfidzHalaqahs::route('/'),
            'create' => CreateTahfidzHalaqah::route('/create'),
            'edit' => EditTahfidzHalaqah::route('/{record}/edit'),
        ];
    }
}