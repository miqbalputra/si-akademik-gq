<?php

namespace App\Filament\Resources\NoKbmAgendas\Pages;

use App\Filament\Resources\NoKbmAgendas\NoKbmAgendaResource;
use App\Models\ClassroomTerm;
use App\Models\SchoolEvent;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateNoKbmAgenda extends CreateRecord
{
    protected static string $resource = NoKbmAgendaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validateTargetTerms($data);
        $data['is_no_kbm'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->normalizeTargets($this->record);
    }

    private function normalizeTargets(SchoolEvent $event): void
    {
        if ($event->target_scope !== 'classes') {
            $event->targetClassroomTerms()->sync([]);
        }

        $event->update([
            'target_level_name' => null,
            'target_gender_group' => $event->target_scope === 'gender' ? $event->target_gender_group : null,
        ]);
    }

    private function validateTargetTerms(array $data): void
    {
        if (($data['target_scope'] ?? 'all') !== 'classes') {
            return;
        }

        $targetIds = collect($data['targetClassroomTerms'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $validCount = ClassroomTerm::query()
            ->where('academic_term_id', (int) ($data['academic_term_id'] ?? 0))
            ->whereIn('id', $targetIds)
            ->count();

        if ($targetIds->isEmpty() || $validCount !== $targetIds->count()) {
            throw ValidationException::withMessages([
                'targetClassroomTerms' => 'Semua kelas target harus berasal dari periode ajaran yang dipilih.',
            ]);
        }
    }
}
