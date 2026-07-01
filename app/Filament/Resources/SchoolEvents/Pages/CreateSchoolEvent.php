<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use App\Models\SchoolEvent;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolEvent extends CreateRecord
{
    protected static string $resource = SchoolEventResource::class;

    protected function afterCreate(): void
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
