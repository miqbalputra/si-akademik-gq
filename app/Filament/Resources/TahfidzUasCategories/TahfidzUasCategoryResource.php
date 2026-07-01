<?php

namespace App\Filament\Resources\TahfidzUasCategories;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TahfidzUasCategories\Pages\CreateTahfidzUasCategory;
use App\Filament\Resources\TahfidzUasCategories\Pages\EditTahfidzUasCategory;
use App\Filament\Resources\TahfidzUasCategories\Pages\ListTahfidzUasCategories;
use App\Filament\Resources\TahfidzUasCategories\Schemas\TahfidzUasCategoryForm;
use App\Filament\Resources\TahfidzUasCategories\Tables\TahfidzUasCategoriesTable;
use App\Models\TahfidzUasCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TahfidzUasCategoryResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';
    protected const NAVIGATION_LABEL = 'Aspek Penilaian UAS';
    protected const NAVIGATION_SORT = 30;
    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TahfidzUasCategory::class;

    protected static ?string $modelLabel = 'Aspek Penilaian UAS';
    protected static ?string $pluralModelLabel = 'Aspek Penilaian UAS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return TahfidzUasCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahfidzUasCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahfidzUasCategories::route('/'),
            'create' => CreateTahfidzUasCategory::route('/create'),
            'edit' => EditTahfidzUasCategory::route('/{record}/edit'),
        ];
    }
}