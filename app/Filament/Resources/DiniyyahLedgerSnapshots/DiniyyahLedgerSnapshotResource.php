<?php

namespace App\Filament\Resources\DiniyyahLedgerSnapshots;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahLedgerSnapshots\Pages\CreateDiniyyahLedgerSnapshot;
use App\Filament\Resources\DiniyyahLedgerSnapshots\Pages\EditDiniyyahLedgerSnapshot;
use App\Filament\Resources\DiniyyahLedgerSnapshots\Pages\ListDiniyyahLedgerSnapshots;
use App\Filament\Resources\DiniyyahLedgerSnapshots\Schemas\DiniyyahLedgerSnapshotForm;
use App\Filament\Resources\DiniyyahLedgerSnapshots\Tables\DiniyyahLedgerSnapshotsTable;
use App\Models\DiniyyahLedgerSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahLedgerSnapshotResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Leger & Rapor';

    protected const NAVIGATION_LABEL = 'Leger Diniyyah';

    protected const NAVIGATION_SORT = 10;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahLedgerSnapshot::class;

    protected static ?string $modelLabel = 'Leger Nilai';
    protected static ?string $pluralModelLabel = 'Leger Nilai';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahLedgerSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahLedgerSnapshotsTable::configure($table);
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
            'index' => ListDiniyyahLedgerSnapshots::route('/'),
            'create' => CreateDiniyyahLedgerSnapshot::route('/create'),
            'edit' => EditDiniyyahLedgerSnapshot::route('/{record}/edit'),
        ];
    }
}
