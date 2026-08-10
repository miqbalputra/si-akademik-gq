<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat perubahan jadwal & penugasan untuk sebuah DiniyyahTeacherAssignment
 * (read-only). Ditampilkan di halaman Edit Penugasan Guru supaya admin melihat
 * konteks perubahan saat mengelola penugasan tertentu.
 */
class DiniyyahScheduleChangeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleChangeLogs';

    protected static ?string $title = 'Riwayat Perubahan Jadwal';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('change_summary')
                    ->label('Perubahan')
                    ->wrap()
                    ->limit(220),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                        default => $state,
                    }),
                TextColumn::make('changer.name')
                    ->label('Diubah Oleh')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                    ]),
            ]);
    }
}