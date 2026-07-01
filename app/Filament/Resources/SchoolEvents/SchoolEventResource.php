<?php

namespace App\Filament\Resources\SchoolEvents;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\SchoolEvents\Pages\CreateSchoolEvent;
use App\Filament\Resources\SchoolEvents\Pages\EditSchoolEvent;
use App\Filament\Resources\SchoolEvents\Pages\ListSchoolEvents;
use App\Filament\Resources\SchoolEvents\Pages\SchoolEventRecap;
use App\Filament\Resources\SchoolEvents\RelationManagers\ResponsesRelationManager;
use App\Filament\Resources\SchoolEvents\Schemas\SchoolEventForm;
use App\Filament\Resources\SchoolEvents\Tables\SchoolEventsTable;
use App\Models\SchoolEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SchoolEventResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Data Sekolah';

    protected const NAVIGATION_LABEL = 'Event Sekolah';

    protected const NAVIGATION_SORT = 36;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = SchoolEvent::class;

    protected static ?string $modelLabel = 'Acara Sekolah';
    protected static ?string $pluralModelLabel = 'Acara Sekolah';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SchoolEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolEvents::route('/'),
            'create' => CreateSchoolEvent::route('/create'),
            'edit' => EditSchoolEvent::route('/{record}/edit'),
            'recap' => SchoolEventRecap::route('/{record}/recap'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ResponsesRelationManager::class,
        ];
    }
}
