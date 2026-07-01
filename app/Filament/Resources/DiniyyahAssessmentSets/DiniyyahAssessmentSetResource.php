<?php

namespace App\Filament\Resources\DiniyyahAssessmentSets;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahAssessmentSets\Pages\CreateDiniyyahAssessmentSet;
use App\Filament\Resources\DiniyyahAssessmentSets\Pages\EditDiniyyahAssessmentSet;
use App\Filament\Resources\DiniyyahAssessmentSets\Pages\ListDiniyyahAssessmentSets;
use App\Filament\Resources\DiniyyahAssessmentSets\Schemas\DiniyyahAssessmentSetForm;
use App\Filament\Resources\DiniyyahAssessmentSets\Tables\DiniyyahAssessmentSetsTable;
use App\Models\DiniyyahAssessmentSet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class DiniyyahAssessmentSetResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Set Penilaian';

    protected const NAVIGATION_SORT = 40;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahAssessmentSet::class;

    protected static ?string $modelLabel = 'Set Penilaian';
    protected static ?string $pluralModelLabel = 'Set Penilaian';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahAssessmentSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahAssessmentSetsTable::configure($table);
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
            'index' => ListDiniyyahAssessmentSets::route('/'),
            'create' => CreateDiniyyahAssessmentSet::route('/create'),
            'edit' => EditDiniyyahAssessmentSet::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (! $user || $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])) {
            return $query;
        }

        if (! $user->hasRole('guru')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('classSubject.teacherAssignments', function (Builder $query) use ($user) {
            $query->where('teacher_id', $user->teacher?->id ?? 0)
                ->where(function (Builder $query) {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                })
                ->where(function (Builder $query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                });
        });
    }
}
