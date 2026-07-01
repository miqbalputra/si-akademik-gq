<?php

namespace App\Filament\Pages;

use App\Models\ClassroomTerm;
use App\Services\Imports\AttendanceSpreadsheetImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class AttendanceShortcut extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Struktur Kelas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Presensi Kelas';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.attendance-shortcut';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Presensi Kelas';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAttendanceSpreadsheet')
                ->label('Import Absensi XLSX')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('classroom_term_id')
                        ->label('Kelas Periode')
                        ->options(fn () => ClassroomTerm::query()
                            ->with('academicTerm.academicYear')
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (ClassroomTerm $classroomTerm) => [
                                $classroomTerm->id => trim(sprintf(
                                    '%s - %s %s',
                                    $classroomTerm->name,
                                    $classroomTerm->academicTerm?->academicYear?->name ?? '-',
                                    $classroomTerm->academicTerm?->semester ? '('.strtoupper($classroomTerm->academicTerm->semester).')' : ''
                                )),
                            ]))
                        ->searchable()
                        ->required(),
                    FileUpload::make('file')
                        ->label('File XLSX')
                        ->disk('local')
                        ->directory('imports/attendance')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $classroomTerm = ClassroomTerm::query()->with('academicTerm')->findOrFail($data['classroom_term_id']);
                    $path = Storage::disk('local')->path($data['file']);
                    $result = app(AttendanceSpreadsheetImporter::class)->import($path, $classroomTerm, auth()->user());

                    Notification::make()
                        ->title($result->hasErrors() ? 'Import absensi selesai dengan catatan' : 'Import absensi berhasil')
                        ->body($result->summary().($result->hasErrors() ? "\n\n".implode("\n", array_slice($result->errors, 0, 8)) : ''))
                        ->status($result->hasErrors() ? 'warning' : 'success')
                        ->send();
                })
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
        ];
    }
}
