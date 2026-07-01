<?php

namespace App\Filament\Resources\DiniyyahScores;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\DiniyyahScores\Pages\CreateDiniyyahScore;
use App\Filament\Resources\DiniyyahScores\Pages\EditDiniyyahScore;
use App\Filament\Resources\DiniyyahScores\Pages\ListDiniyyahScores;
use App\Filament\Resources\DiniyyahScores\Schemas\DiniyyahScoreForm;
use App\Filament\Resources\DiniyyahScores\Tables\DiniyyahScoresTable;
use App\Models\DiniyyahScore;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class DiniyyahScoreResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Diniyyah';

    protected const NAVIGATION_LABEL = 'Nilai Diniyyah';

    protected const NAVIGATION_SORT = 60;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    protected static ?string $model = DiniyyahScore::class;

    protected static ?string $modelLabel = 'Nilai Ujian';
    protected static ?string $pluralModelLabel = 'Nilai Ujian';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DiniyyahScoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiniyyahScoresTable::configure($table);
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
            'index' => ListDiniyyahScores::route('/'),
            'create' => CreateDiniyyahScore::route('/create'),
            'edit' => EditDiniyyahScore::route('/{record}/edit'),
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

        return $query->whereHas('assessmentSet.classSubject.teacherAssignments', function (Builder $query) use ($user) {
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
