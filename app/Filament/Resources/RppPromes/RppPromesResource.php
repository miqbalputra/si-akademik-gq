<?php

namespace App\Filament\Resources\RppPromes;

use App\Filament\Resources\RppPromes\Pages\CreateRppPromes;
use App\Filament\Resources\RppPromes\Pages\EditRppPromes;
use App\Filament\Resources\RppPromes\Pages\ListRppPromes;
use App\Models\DiniyyahClassSubject;
use App\Models\RppPromes;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RppPromesResource extends Resource
{
    protected static ?string $model = RppPromes::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum & RPP';
    protected static ?string $navigationLabel = 'Program Semester';
    protected static ?string $modelLabel = 'Promes';
    protected static ?string $pluralModelLabel = 'Program Semester';

    public static function canViewAny(): bool { return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false; }
    public static function canCreate(): bool { return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ?? false; }
    public static function canEdit($record): bool { return static::canCreate(); }
    public static function canDelete($record): bool { return static::canCreate(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('diniyyah_class_subject_id')->label('Mapel dan Kelas')->options(fn () => DiniyyahClassSubject::query()->with(['subject', 'classroomTerm'])->where('is_active', true)->get()->mapWithKeys(fn ($row) => [$row->id => "{$row->subject?->name} — {$row->classroomTerm?->name}"])->all())->searchable()->required()->unique(ignoreRecord: true),
            TextInput::make('url')->label('Tautan Promes')->url()->maxLength(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->query(RppPromes::query()->with(['classSubject.subject', 'classSubject.classroomTerm']))->columns([
            TextColumn::make('classSubject.subject.name')->label('Mapel')->searchable(),
            TextColumn::make('classSubject.classroomTerm.name')->label('Kelas')->searchable(),
            TextColumn::make('url')->label('Tautan')->limit(45)->url(fn ($record) => $record->url, true),
            TextColumn::make('updated_at')->label('Diperbarui')->since(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListRppPromes::route('/'), 'create' => CreateRppPromes::route('/create'), 'edit' => EditRppPromes::route('/{record}/edit')]; }
}
