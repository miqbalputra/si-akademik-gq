<?php

namespace App\Filament\Resources\Rpps;

use App\Filament\Resources\Rpps\Pages\ListRpps;
use App\Models\Rpp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RppResource extends Resource
{
    protected static ?string $model = Rpp::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum & RPP';
    protected static ?string $navigationLabel = 'Monitoring RPP';
    protected static ?string $modelLabel = 'RPP';
    protected static ?string $pluralModelLabel = 'Monitoring RPP';

    public static function canViewAny(): bool { return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false; }

    public static function form(Schema $schema): Schema { return $schema; }

    public static function table(Table $table): Table
    {
        return $table->query(Rpp::query()->with(['teacher', 'classSubject.subject', 'classSubject.classroomTerm']))
            ->columns([
                TextColumn::make('materi')->label('Materi')->searchable()->wrap(),
                TextColumn::make('teacher.name')->label('Guru')->searchable(),
                TextColumn::make('classSubject.subject.name')->label('Mapel')->searchable(),
                TextColumn::make('classSubject.classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('input_method')->label('Metode')->badge(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array { return ['index' => ListRpps::route('/')]; }
}
