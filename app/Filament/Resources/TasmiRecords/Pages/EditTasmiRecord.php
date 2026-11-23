<?php

namespace App\Filament\Resources\TasmiRecords\Pages;

use App\Filament\Resources\TasmiRecords\TasmiRecordResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditTasmiRecord extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = TasmiRecordResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['last_updated_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if ((int) ($data['review_version'] ?? -1) !== $current->review_version) {
                throw ValidationException::withMessages(['data.review_version' => 'Data atau persetujuan sudah berubah. Muat ulang halaman sebelum mengedit.']);
            }
            unset($data['review_version']);
            $current->fill($data)->save();
            $record->setRawAttributes($current->getAttributes(), true);
            $record->unsetRelations();
            $this->data['review_version'] = $current->review_version;

            return $record;
        });
    }
}
