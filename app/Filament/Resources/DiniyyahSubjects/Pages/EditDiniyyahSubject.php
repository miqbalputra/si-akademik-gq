<?php

namespace App\Filament\Resources\DiniyyahSubjects\Pages;

use App\Filament\Resources\DiniyyahSubjects\DiniyyahSubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahSubject extends EditRecord
{
    protected static string $resource = DiniyyahSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
