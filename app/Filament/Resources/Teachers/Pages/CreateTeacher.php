<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Services\TeacherAccountService;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    /**
     * Kredensial login (username/password) yang diisi di section "Akun Login".
     * Ditarik keluar dari data form di mutateFormDataBeforeCreate lalu dipakai
     * di afterCreate untuk membuat/sinkron akun User yang ter-link ke teacher.
     *
     * @var array<string, string|null>
     */
    protected array $loginAccountData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->loginAccountData = [
            'login_username' => $data['login_username'] ?? null,
            'login_password' => $data['login_password'] ?? null,
        ];

        unset($data['login_username'], $data['login_password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(TeacherAccountService::class)->syncForTeacher(
            $this->record,
            $this->loginAccountData['login_username'] ?? null,
            $this->loginAccountData['login_password'] ?? null,
        );
    }
}