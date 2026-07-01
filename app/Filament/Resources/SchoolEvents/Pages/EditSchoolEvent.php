<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use App\Models\SchoolEvent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolEvent extends EditRecord
{
    protected static string $resource = SchoolEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recap')
                ->label('Lihat Rekap')
                ->icon('heroicon-o-chart-bar-square')
                ->url(fn (): string => SchoolEventResource::getUrl('recap', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->normalizeEventTargets($this->record);
    }

    private function normalizeEventTargets(SchoolEvent $event): void
    {
        if ($event->target_scope !== 'classes') {
            $event->targetClassroomTerms()->sync([]);
        }

        $event->update([
            'target_level_name' => in_array($event->target_scope, ['level', 'level_gender'], true) ? $event->target_level_name : null,
            'target_gender_group' => in_array($event->target_scope, ['gender', 'level_gender'], true) ? $event->target_gender_group : null,
        ]);
    }
}
