<?php

namespace App\Filament\Resources\SchoolEvents\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'Respon Wali Santri';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('responded_at', 'desc')
            ->columns([
                TextColumn::make('guardian.name')
                    ->label('Nama Wali')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('guardian.whatsapp')
                    ->label('WhatsApp')
                    ->toggleable(),
                TextColumn::make('attendance_status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state, $record) => $record->statusLabel())
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'attending' => 'success',
                        'permission' => 'warning',
                        'not_attending' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('responded_at')
                    ->label('Waktu Respon')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
