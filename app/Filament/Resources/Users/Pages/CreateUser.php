<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Peran yang dipilih di field virtual "user_roles". Ditarik keluar di
     * mutateFormDataBeforeCreate (agar tak tersimpan sebagai atribut User)
     * lalu dipasang via Spatie syncRoles di afterCreate.
     *
     * @var array<int, string>|null
     */
    protected ?array $rolesData = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rolesData = $data['user_roles'] ?? [];

        unset($data['user_roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // syncRoles (bukan relasi langsung) agar cache permission Spatie di-clear.
        $this->record->syncRoles($this->rolesData ?? []);
    }
}