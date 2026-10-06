<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
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
            DeleteAction::make()->label('Nonaktifkan akun'),
            RestoreAction::make()->label('Aktifkan kembali akun'),
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
        $oldRoles = $this->record->roles()->pluck('name')->sort()->values()->all();
        $newRoles = array_values(array_unique($this->rolesData ?? []));
        sort($newRoles);
        $this->record->syncRoles($newRoles);

        if ($oldRoles !== $newRoles) {
            activity('security')
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->withProperties(['old_roles' => $oldRoles, 'new_roles' => $newRoles])
                ->log('user_roles_updated');
        }

        // Sync email ke profil Guru/Wali yang terhubung agar login + Google tetap konsisten.
        if ($this->record->teacher) {
            $this->record->teacher->forceFill(['email' => $this->record->email])->save();
        }

        if ($this->record->guardian) {
            $this->record->guardian->forceFill(['email' => $this->record->email])->save();
        }
    }
}
