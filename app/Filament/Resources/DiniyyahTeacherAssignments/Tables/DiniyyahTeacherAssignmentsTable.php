<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahTeacherAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classSubject.classroomTerm.name')->label('Kelas')->searchable(),
                TextColumn::make('classSubject.subject.name')->label('Mapel')->searchable(),
                TextColumn::make('teacher.name')->label('Guru')->searchable()->sortable(),
                TextColumn::make('assignment_role')->label('Peran Tugas')->badge(),
                TextColumn::make('starts_at')->label('Dimulai Pada')->date()->sortable(),
                TextColumn::make('ends_at')->label('Selesai Pada')->date()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
