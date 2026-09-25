<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahTeachingSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('teacherAssignment.teacher.name')
                    ->label('Guru')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teacherAssignment.classSubject.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teacherAssignment.classSubject.classroomTerm.name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('day_of_week')
                    ->label('Hari')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        6 => 'Sabtu',
                        7 => 'Minggu',
                        default => 'Tidak diketahui',
                    })
                    ->sortable(),
                TextColumn::make('classSession.session_name')
                    ->label('Jam Pelajaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'legacy' => 'Legacy · belum ditinjau',
                        'active' => 'Berlaku',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'legacy' ? 'warning' : 'success'),
                TextColumn::make('effective_from')
                    ->label('Mulai berlaku')
                    ->date('d M Y')
                    ->placeholder('Perilaku lama'),
                TextColumn::make('effective_until')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('day_of_week')
                    ->label('Hari')
                    ->options([
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        6 => 'Sabtu',
                        7 => 'Minggu',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
