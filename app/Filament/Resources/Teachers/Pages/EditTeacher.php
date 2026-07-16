<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Services\TeacherAccountService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    /**
     * Kredensial login (username/password) dari section "Akun Login".
     *
     * @var array<string, string|null>
     */
    protected array $loginAccountData = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Prefill username dari akun yang ter-link (bila ada). Password tidak
        // pernah di-prefill demi keamanan — kolom kosong = tidak mengubah password.
        if ($this->record->user) {
            $data['login_username'] = $this->record->user->username;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->loginAccountData = [
            'login_username' => $data['login_username'] ?? null,
            'login_password' => $data['login_password'] ?? null,
        ];

        unset($data['login_username'], $data['login_password']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(TeacherAccountService::class)->syncForTeacher(
            $this->record,
            $this->loginAccountData['login_username'] ?? null,
            $this->loginAccountData['login_password'] ?? null,
        );
    }
}