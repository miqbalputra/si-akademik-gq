<?php

namespace App\Filament\Resources\Students\Tables;

use App\Filament\Resources\Students\Actions\StudentDepartureActions;
use App\Models\Student;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')
                    ->searchable(),
                TextColumn::make('gender')->label('Jenis Kelamin')
                    ->formatStateUsing(fn ($state): string => \App\Support\UiLabel::genderLabel($state))
                    ->searchable(),
                TextColumn::make('nis')->label('NIS')
                    ->searchable(),
                TextColumn::make('status')->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => \App\Support\UiLabel::statusLabel($state))
                    ->color(fn ($state): string => \App\Support\UiLabel::statusColor($state))
                    ->searchable(),
                TextColumn::make('created_at')->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->label('Dihapus Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('latestDeparture.type')->label('Jenis Keluar')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'transfer' => 'Pindah',
                        'left' => 'Keluar',
                        default => '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
                TextColumn::make('latestDeparture.effective_date')->label('Tanggal Keluar')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (Student $record): bool => ! $record->trashed()),
                StudentDepartureActions::depart(),
                StudentDepartureActions::restoreProfile(),
            ]);
    }
}
