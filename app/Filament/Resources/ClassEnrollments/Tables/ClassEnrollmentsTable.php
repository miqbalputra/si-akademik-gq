<?php

namespace App\Filament\Resources\ClassEnrollments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with([
                'academicTerm',
                'classroomTerm',
                'student'
            ]))
            ->columns([
                TextColumn::make('academicTerm.name')
                    ->label('Periode Akademik')
                    ->searchable(),
                TextColumn::make('classroomTerm.name')
                    ->label('Kelas Periode')
                    ->searchable(),
                TextColumn::make('student.name')
                    ->label('Nama Santri')
                    ->searchable(),
                TextColumn::make('roll_number')
                    ->label('No. Absen')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
