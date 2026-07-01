<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Pages;

use App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource;
use App\Services\Imports\TeacherAssignmentCsvImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListDiniyyahTeacherAssignments extends ListRecords
{
    protected static string $resource = DiniyyahTeacherAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTeacherImportTemplate')
                ->label('Template Import')
                ->icon('heroicon-o-document-arrow-down')
                ->url('/templates/import-guru-penugasan.csv')
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            Action::make('importTeachers')
                ->label('Import Guru & Penugasan')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->disk('local')
                        ->directory('imports/teachers')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    $result = app(TeacherAssignmentCsvImporter::class)->import($path, auth()->user());

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
