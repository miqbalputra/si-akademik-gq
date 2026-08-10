<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Pages;

use App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource;
use App\Models\DiniyyahTeacherAssignment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahTeacherAssignment extends EditRecord
{
    protected static string $resource = DiniyyahTeacherAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (DiniyyahTeacherAssignment $record): bool => $record->isDeletable())
                ->requiresConfirmation()
                ->modalDescription(fn (DiniyyahTeacherAssignment $record): string => $record->journals()->exists()
                    ? 'Penugasan ini masih memiliki jurnal kelas dan tidak dapat dihapus.'
                    : 'Penugasan akan dihapus permanen beserta jadwal terkait. Jurnal kelas tidak terpengaruh karena tidak ada jurnal pada penugasan ini.'),
        ];
    }
}