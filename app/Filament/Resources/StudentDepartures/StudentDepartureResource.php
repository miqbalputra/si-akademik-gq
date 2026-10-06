<?php

namespace App\Filament\Resources\StudentDepartures;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\StudentDepartures\Pages\ListStudentDepartures;
use App\Filament\Resources\StudentDepartures\Pages\ViewStudentDeparture;
use App\Filament\Resources\StudentDepartures\Schemas\StudentDepartureInfolist;
use App\Filament\Resources\StudentDepartures\Tables\StudentDeparturesTable;
use App\Models\StudentDeparture;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentDepartureResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Data Sekolah';

    protected const NAVIGATION_LABEL = 'Arsip Siswa';

    protected const NAVIGATION_SORT = 41;

    protected const VIEW_ROLES = ['admin'];

    protected const MANAGE_ROLES = [];

    protected static ?string $model = StudentDeparture::class;

    protected static ?string $modelLabel = 'Arsip Siswa';

    protected static ?string $pluralModelLabel = 'Arsip Siswa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentDepartureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentDeparturesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'exitedBy', 'restoredBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentDepartures::route('/'),
            'view' => ViewStudentDeparture::route('/{record}'),
        ];
    }
}
