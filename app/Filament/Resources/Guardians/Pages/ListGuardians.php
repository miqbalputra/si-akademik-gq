<?php

namespace App\Filament\Resources\Guardians\Pages;

use App\Filament\Resources\Guardians\GuardianResource;
use App\Services\Imports\GuardianStudentCsvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListGuardians extends ListRecords
{
    protected static string $resource = GuardianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadGuardianStudentImportTemplate')
                ->label('Template Import')
                ->icon('heroicon-o-document-arrow-down')
                ->url('/templates/import-wali-siswa.csv')
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            Action::make('importGuardianStudents')
                ->label('Import Siswa & Wali')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->disk('local')
                        ->directory('imports/guardian-students')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    $result = app(GuardianStudentCsvImporter::class)->import($path);

                    Notification::make()
                        ->title($result->hasErrors() ? 'Import selesai dengan catatan' : 'Import berhasil')
                        ->body($result->summary().($result->hasErrors() ? "\n\n".implode("\n", array_slice($result->errors, 0, 8)) : ''))
                        ->status($result->hasErrors() ? 'warning' : 'success')
                        ->send();
                })
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            CreateAction::make(),
        ];
    }
}
