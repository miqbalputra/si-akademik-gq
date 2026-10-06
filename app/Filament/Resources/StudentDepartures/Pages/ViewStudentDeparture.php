<?php

namespace App\Filament\Resources\StudentDepartures\Pages;

use App\Filament\Resources\StudentDepartures\StudentDepartureResource;
use App\Models\StudentDeparture;
use App\Services\StudentDepartureWorkflow;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStudentDeparture extends ViewRecord
{
    protected static string $resource = StudentDepartureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restoreStudentProfile')
                ->label('Pulihkan Profil')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn (StudentDeparture $record): bool => $record->restored_at === null && $record->student?->trashed())
                ->requiresConfirmation()
                ->modalHeading('Pulihkan profil santri?')
                ->modalDescription('Profil akan aktif kembali; enrollment kelas dan halaqah tetap nonaktif.')
                ->action(function (StudentDeparture $record): void {
                    $actor = auth()->user();
                    abort_unless($actor?->hasRole('admin'), 403);

                    try {
                        app(StudentDepartureWorkflow::class)->restoreProfile($record, $actor);
                        Notification::make()->title('Profil santri dipulihkan')->success()->send();
                    } catch (DomainException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
