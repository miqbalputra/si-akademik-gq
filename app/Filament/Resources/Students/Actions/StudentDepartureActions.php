<?php

namespace App\Filament\Resources\Students\Actions;

use App\Models\Student;
use App\Models\StudentDeparture;
use App\Services\StudentDepartureWorkflow;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

class StudentDepartureActions
{
    public static function depart(): Action
    {
        return Action::make('departStudent')
            ->label('Keluarkan Santri')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('warning')
            ->visible(fn (Student $record): bool => ! $record->trashed()
                && (auth()->user()?->hasRole('admin') ?? false))
            ->modalHeading('Keluarkan Santri')
            ->modalDescription('Data siswa dan riwayat terkait akan disimpan di arsip. Enrollment aktif akan dinonaktifkan.')
            ->modalSubmitActionLabel('Simpan ke Arsip')
            ->schema([
                Select::make('type')
                    ->label('Jenis Pengeluaran')
                    ->options([
                        StudentDeparture::TYPE_TRANSFER => 'Pindah sekolah',
                        StudentDeparture::TYPE_LEFT => 'Keluar',
                    ])
                    ->live()
                    ->required(),
                DatePicker::make('effective_date')
                    ->label('Tanggal Keluar')
                    ->default(now()->toDateString())
                    ->required(),
                TextInput::make('destination_school')
                    ->label('Sekolah Tujuan')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('type') === StudentDeparture::TYPE_TRANSFER)
                    ->required(fn (Get $get): bool => $get('type') === StudentDeparture::TYPE_TRANSFER),
                Textarea::make('reason')
                    ->label('Alasan')
                    ->rows(4)
                    ->maxLength(5000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Student $record): void {
                $actor = auth()->user();
                abort_unless($actor?->hasRole('admin'), 403);

                try {
                    app(StudentDepartureWorkflow::class)->depart($record, $data, $actor);
                    Notification::make()->title('Santri berhasil diarsipkan')->success()->send();
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function restoreProfile(): Action
    {
        return Action::make('restoreStudentProfile')
            ->label('Pulihkan Profil')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->visible(fn (Student $record): bool => $record->trashed()
                && (auth()->user()?->hasRole('admin') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Pulihkan profil santri?')
            ->modalDescription('Profil akan aktif kembali. Enrollment kelas dan halaqah tetap nonaktif sehingga admin perlu menempatkannya kembali secara manual.')
            ->modalSubmitActionLabel('Pulihkan Profil')
            ->action(function (Student $record): void {
                $actor = auth()->user();
                abort_unless($actor?->hasRole('admin'), 403);

                $departure = $record->departures()
                    ->whereNull('restored_at')
                    ->latest('id')
                    ->first();

                if (! $departure) {
                    Notification::make()->title('Catatan pengeluaran belum ditemukan')->danger()->send();

                    return;
                }

                try {
                    app(StudentDepartureWorkflow::class)->restoreProfile($departure, $actor);
                    Notification::make()->title('Profil santri dipulihkan')->success()->send();
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }
}
