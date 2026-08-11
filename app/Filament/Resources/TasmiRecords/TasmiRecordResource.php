<?php

namespace App\Filament\Resources\TasmiRecords;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\TasmiRecords\Pages\CreateTasmiRecord;
use App\Filament\Resources\TasmiRecords\Pages\EditTasmiRecord;
use App\Filament\Resources\TasmiRecords\Pages\ListTasmiRecords;
use App\Filament\Resources\TasmiRecords\Pages\ViewTasmiRecord;
use App\Filament\Resources\TasmiRecords\Schemas\TasmiRecordForm;
use App\Filament\Resources\TasmiRecords\Tables\TasmiRecordsTable;
use App\Models\TasmiRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TasmiRecordResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Tahfidz';
    protected const NAVIGATION_LABEL = 'Laporan Tasmi\'';
    protected const NAVIGATION_SORT = 45;
    // Admin & kabag_tahfidz bisa kelola (edit semua). Kepala sekolah read-only.
    protected const VIEW_ROLES = ['admin', 'kabag_tahfidz', 'kepala_sekolah'];
    protected const MANAGE_ROLES = ['admin', 'kabag_tahfidz'];

    protected static ?string $model = TasmiRecord::class;

    protected static ?string $modelLabel = 'Record Tasmi\'';
    protected static ?string $pluralModelLabel = 'Record Tasmi\'';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return TasmiRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasmiRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasmiRecords::route('/'),
            'create' => CreateTasmiRecord::route('/create'),
            'view' => ViewTasmiRecord::route('/{record}'),
            'edit' => EditTasmiRecord::route('/{record}/edit'),
        ];
    }
}