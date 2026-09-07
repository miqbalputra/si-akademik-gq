<?php

namespace App\Filament\Resources\ClassSessions;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\ClassSessions\Pages\CreateClassSession;
use App\Filament\Resources\ClassSessions\Pages\EditClassSession;
use App\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use App\Filament\Resources\ClassSessions\Schemas\ClassSessionForm;
use App\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use App\Models\ClassSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClassSessionResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected static ?string $model = ClassSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Data Sekolah';
    
    protected static ?string $modelLabel = 'Jam Pelajaran / Sesi';
    protected static ?string $pluralModelLabel = 'Jam Pelajaran / Sesi';

    protected static ?string $recordTitleAttribute = 'session_name';

    // Sesi adalah bagian dari konfigurasi jadwal Diniyyah. Kabag Tahfidz
    // tidak boleh memperoleh akses hanya karena dapat masuk ke panel Filament.
    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    public static function form(Schema $schema): Schema
    {
        return ClassSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassSessionsTable::configure($table);
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
            'index' => ListClassSessions::route('/'),
            'create' => CreateClassSession::route('/create'),
            'edit' => EditClassSession::route('/{record}/edit'),
        ];
    }
}
