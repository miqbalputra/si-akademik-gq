<?php

namespace App\Filament\Resources\Teachers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Akun Pengguna')
                    ->searchable(),
                TextColumn::make('name')->label('Nama')
                    ->searchable(),
                TextColumn::make('gender')->label('Jenis Kelamin')
                    ->formatStateUsing(fn ($state): string => \App\Support\UiLabel::genderLabel($state))
                    ->searchable(),
                TextColumn::make('phone')->label('Telepon')
                    ->searchable(),
                TextColumn::make('whatsapp')->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Alamat email')
                    ->searchable(),
                TextColumn::make('started_at')->label('Dimulai Pada')
                    ->date()
                    ->sortable(),
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
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
