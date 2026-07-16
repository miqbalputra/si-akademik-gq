<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string>|null */
    protected ?array $rolesData = null;

    protected function getHeaderActions(): array
    {
        return [
            // Hapus akun dijaga oleh UserPolicy::delete (admin tak bisa hapus akun sendiri).
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Prefill peran dari relasi Spatie. Password tak pernah di-prefill.
        $data['user_roles'] = $this->record->roles->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $roles = $data['user_roles'] ?? [];

        // Cegah lockout diri: admin tak boleh melepas peran admin dari akunnya sendiri.
        if ($this->record->id === auth()->id() && ! in_array('admin', $roles, true)) {
            throw ValidationException::withMessages([
                'user_roles' => 'Anda tidak boleh menghapus peran admin dari akun Anda sendiri.',
            ]);
        }

        $this->rolesData = $roles;
        unset($data['user_roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles($this->rolesData ?? []);

        // Sync email ke profil Guru/Wali yang terhubung agar login + Google tetap konsisten.
        if ($this->record->teacher) {
            $this->record->teacher->forceFill(['email' => $this->record->email])->save();
        }

        if ($this->record->guardian) {
            $this->record->guardian->forceFill(['email' => $this->record->email])->save();
        }
    }
}