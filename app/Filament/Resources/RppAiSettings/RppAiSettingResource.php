<?php

namespace App\Filament\Resources\RppAiSettings;

use App\Filament\Resources\RppAiSettings\Pages\CreateRppAiSetting;
use App\Filament\Resources\RppAiSettings\Pages\EditRppAiSetting;
use App\Filament\Resources\RppAiSettings\Pages\ListRppAiSettings;
use App\Models\RppAiSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RppAiSettingResource extends Resource
{
    protected static ?string $model = RppAiSetting::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum & RPP';
    protected static ?string $navigationLabel = 'Konfigurasi AI RPP';
    protected static ?string $modelLabel = 'Konfigurasi AI RPP';
    protected static ?string $pluralModelLabel = 'Konfigurasi AI RPP';

    public static function canViewAny(): bool { return auth()->user()?->hasRole('admin') ?? false; }
    public static function canCreate(): bool { return static::canViewAny() && ! RppAiSetting::query()->exists(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')->label('Aktifkan asisten AI')->default(false),
            TextInput::make('endpoint')->label('Endpoint OpenAI-compatible')->url()->required(),
            TextInput::make('model')->label('Model vision')->required(),
            TextInput::make('api_key')->label('API key')->password()->revealable()->dehydrated(fn ($state) => filled($state))->helperText('Kosongkan saat edit untuk mempertahankan key yang sudah tersimpan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([IconColumn::make('enabled')->label('Aktif')->boolean(), TextColumn::make('endpoint')->label('Endpoint'), TextColumn::make('model')->label('Model'), TextColumn::make('updated_at')->label('Diperbarui')->since()])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListRppAiSettings::route('/'), 'create' => CreateRppAiSetting::route('/create'), 'edit' => EditRppAiSetting::route('/{record}/edit')]; }
}
