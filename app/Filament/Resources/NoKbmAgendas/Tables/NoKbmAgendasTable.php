<?php

namespace App\Filament\Resources\NoKbmAgendas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NoKbmAgendasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->modifyQueryUsing(fn ($query) => $query->with(['academicTerm.academicYear', 'targetClassroomTerms.classroom']))
            ->columns([
                TextColumn::make('title')->label('Agenda')->searchable()->wrap(),
                TextColumn::make('event_type')->label('Jenis')->formatStateUsing(fn (string $state) => match ($state) {
                    'outdoor' => 'Outdoor',
                    'meeting' => 'Pertemuan',
                    'religious' => 'Agenda Diniyyah',
                    'exam' => 'Ujian',
                    default => 'Agenda Sekolah',
                }),
                TextColumn::make('target_scope')->label('Cakupan')->formatStateUsing(fn ($state, $record) => $record->targetScopeLabel()),
                TextColumn::make('target_summary')->label('Kelas')->state(fn ($record) => $record->targetSummary())->wrap(),
                TextColumn::make('starts_on')->label('Mulai')->date('d F Y')->sortable(),
                TextColumn::make('ends_on')->label('Selesai')->date('d F Y')->sortable(),
                TextColumn::make('academicTerm.name')->label('Periode')->formatStateUsing(fn ($state, $record) => trim(($record->academicTerm?->academicYear?->name ?? '-').' - '.$state)),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
