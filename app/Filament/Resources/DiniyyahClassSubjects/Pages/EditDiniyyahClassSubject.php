<?php

namespace App\Filament\Resources\DiniyyahClassSubjects\Pages;

use App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahClassSubject extends EditRecord
{
    protected static string $resource = DiniyyahClassSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
