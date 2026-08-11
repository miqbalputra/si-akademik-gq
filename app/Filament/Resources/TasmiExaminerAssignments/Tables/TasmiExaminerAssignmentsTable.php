<?php

namespace App\Filament\Resources\TasmiExaminerAssignments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasmiExaminerAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicTerm.name')->label('Periode')->searchable()->sortable(),
                TextColumn::make('teacher.name')->label('Guru PJ Tasmi\'')->searchable()->sortable(),
                TextColumn::make('teacher.gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'male' => 'Ustadz (Ikhwan)',
                        'female' => 'Ustadzah (Akwat)',
                        default => $state,
                    }),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Nonaktif',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('tasmi_records_count')
                    ->label('Jumlah Record')
                    ->counts('tasmiRecords')
                    ->sortable(),
                TextColumn::make('assignedBy.name')->label('Ditugaskan Oleh')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Periode')
                    ->relationship('academicTerm', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Nonaktif',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}