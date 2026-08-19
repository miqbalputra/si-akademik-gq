<?php

namespace App\Filament\Resources\NoKbmAgendas;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\NoKbmAgendas\Pages\CreateNoKbmAgenda;
use App\Filament\Resources\NoKbmAgendas\Pages\EditNoKbmAgenda;
use App\Filament\Resources\NoKbmAgendas\Pages\ListNoKbmAgendas;
use App\Filament\Resources\NoKbmAgendas\Schemas\NoKbmAgendaForm;
use App\Filament\Resources\NoKbmAgendas\Tables\NoKbmAgendasTable;
use App\Models\SchoolEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NoKbmAgendaResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Data Sekolah';
    protected const NAVIGATION_LABEL = 'Agenda Tanpa KBM';
    protected const NAVIGATION_SORT = 37;
    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = SchoolEvent::class;
    protected static ?string $modelLabel = 'Agenda Tanpa KBM';
    protected static ?string $pluralModelLabel = 'Agenda Tanpa KBM';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_no_kbm', true);
    }

    public static function form(Schema $schema): Schema
    {
        return NoKbmAgendaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NoKbmAgendasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNoKbmAgendas::route('/'),
            'create' => CreateNoKbmAgenda::route('/create'),
            'edit' => EditNoKbmAgenda::route('/{record}/edit'),
        ];
    }
}
