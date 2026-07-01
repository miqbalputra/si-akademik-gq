<?php

namespace App\Filament\Resources\Classrooms\Pages;

use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Services\Imports\ClassEnrollmentCsvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListClassrooms extends ListRecords
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadClassEnrollmentImportTemplate')
                ->label('Template Import')
                ->icon('heroicon-o-document-arrow-down')
                ->url('/templates/import-kelas-enrollment.csv')
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            Action::make('importClassEnrollments')
                ->label('Import Kelas & Siswa')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->disk('local')
                        ->directory('imports/class-enrollments')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    $result = app(ClassEnrollmentCsvImporter::class)->import($path);

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
