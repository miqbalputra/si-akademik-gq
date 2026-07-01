<?php

namespace App\Filament\Resources\DiniyyahAssessmentResults;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahAssessmentResults\Pages\CreateDiniyyahAssessmentResult;
use App\Filament\Resources\DiniyyahAssessmentResults\Pages\EditDiniyyahAssessmentResult;
use App\Filament\Resources\DiniyyahAssessmentResults\Pages\ListDiniyyahAssessmentResults;
use App\Filament\Resources\DiniyyahAssessmentResults\Schemas\DiniyyahAssessmentResultForm;
use App\Filament\Resources\DiniyyahAssessmentResults\Tables\DiniyyahAssessmentResultsTable;
use App\Models\DiniyyahAssessmentResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiniyyahAssessmentResultResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Hasil Penilaian';

    protected const NAVIGATION_SORT = 70;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahAssessmentResult::class;

    protected static ?string $modelLabel = 'Hasil Penilaian';
    protected static ?string $pluralModelLabel = 'Hasil Penilaian';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahAssessmentResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahAssessmentResultsTable::configure($table);
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
            'index' => ListDiniyyahAssessmentResults::route('/'),
            'create' => CreateDiniyyahAssessmentResult::route('/create'),
            'edit' => EditDiniyyahAssessmentResult::route('/{record}/edit'),
        ];
    }
}
