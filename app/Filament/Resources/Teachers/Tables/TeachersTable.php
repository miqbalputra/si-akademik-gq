<?php

namespace App\Filament\Resources\Teachers\Tables;

use App\Models\Teacher;
use App\Services\AttendanceIntegrationStatusService;
use App\Support\UiLabel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('niy')->label('NIY')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('journal_attendance_exempt')
                    ->label('Syarat presensi jurnal')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Dikecualikan' : 'Wajib GeoPresensi')
                    ->color(fn ($state): string => $state ? 'info' : 'gray'),
                TextColumn::make('attendance_integration_status')
                    ->label('GeoPresensi')
                    ->state(fn (Teacher $record): string => app(AttendanceIntegrationStatusService::class)->badgeLabel($record))
                    ->badge()
                    ->color(fn (Teacher $record): string => app(AttendanceIntegrationStatusService::class)->badgeColor($record)),
                TextColumn::make('gender')->label('Jenis Kelamin')
                    ->formatStateUsing(fn ($state): string => UiLabel::genderLabel($state))
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
                    ->formatStateUsing(fn ($state): string => UiLabel::statusLabel($state))
                    ->color(fn ($state): string => UiLabel::statusColor($state))
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
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
