<?php

namespace App\Filament\Resources\StudentDepartures\Tables;

use App\Models\StudentDeparture;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentDeparturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')->label('Nama Santri')->searchable()->sortable(),
                TextColumn::make('student.nis')->label('NIS')->searchable(),
                TextColumn::make('type')->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === StudentDeparture::TYPE_TRANSFER ? 'Pindah' : 'Keluar')
                    ->color(fn (string $state): string => $state === StudentDeparture::TYPE_TRANSFER ? 'info' : 'warning'),
                TextColumn::make('effective_date')->label('Tanggal Keluar')->date()->sortable(),
                TextColumn::make('destination_school')->label('Sekolah Tujuan')->placeholder('—')->toggleable(),
                TextColumn::make('reason')->label('Alasan')->limit(60)->wrap(),
                TextColumn::make('exitedBy.name')->label('Diproses Oleh')->placeholder('Akun tidak tersedia'),
                TextColumn::make('restored_at')->label('Status Arsip')
                    ->formatStateUsing(fn ($state): string => $state ? 'Profil dipulihkan' : 'Diarsipkan')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'gray' : 'warning'),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ])
            ->defaultSort('effective_date', 'desc');
    }
}
