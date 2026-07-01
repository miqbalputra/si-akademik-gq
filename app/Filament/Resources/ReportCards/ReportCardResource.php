<?php

namespace App\Filament\Resources\ReportCards;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\ReportCards\Pages\CreateReportCard;
use App\Filament\Resources\ReportCards\Pages\EditReportCard;
use App\Filament\Resources\ReportCards\Pages\ListReportCards;
use App\Filament\Resources\ReportCards\RelationManagers\SignaturesRelationManager;
use App\Filament\Resources\ReportCards\Schemas\ReportCardForm;
use App\Filament\Resources\ReportCards\Tables\ReportCardsTable;
use App\Models\ReportCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportCardResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Leger & Rapor';

    protected const NAVIGATION_LABEL = 'Rapor Diniyyah';

    protected const NAVIGATION_SORT = 20;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = ReportCard::class;

    protected static ?string $modelLabel = 'Rapor Santri';
    protected static ?string $pluralModelLabel = 'Rapor Santri';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ReportCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportCardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SignaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportCards::route('/'),
            'create' => CreateReportCard::route('/create'),
            'edit' => EditReportCard::route('/{record}/edit'),
        ];
    }
}
