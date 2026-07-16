<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Membuat/memperbarui akun login (User) yang ter-link ke seorang Teacher.
 *
 * Pola ini mengikuti App\Services\Imports\TeacherAssignmentCsvImporter::syncTeacherUser():
 * find-or-create User by email, isi name dari teacher, set password bila diberikan,
 * lalu assignRole('guru') dan link teacher.user_id.
 *
 * Dipakai oleh halaman Create/Edit Guru (Filament) sehingga admin bisa membuat
 * akun login guru (username + password) langsung dari menu Guru. Email login
 * diambil dari kolom Teacher.email yang sudah ada di form.
 */
class TeacherAccountService
{
    /**
     * Sinkronkan akun login untuk teacher.
     *
     * @param  string|null  $username  Username baru (plain). Null = tidak mengubah.
     * @param  string|null  $password  Password baru (plain). Null saat update = biarkan; saat buat baru wajib.
     * @return User|null User yang dibuat/diperbarui, atau null bila tidak ada akun dibuat.
     *
     * @throws ValidationException Bila email kosong, username/email dipakai akun lain, atau password kosong saat buat baru.
     */
    public function syncForTeacher(Teacher $teacher, ?string $username, ?string $password): ?User
    {
        $username = $this->normalize($username);
        $password = $this->normalize($password);
        $existingUser = $teacher->user;

        // Tidak ada kredensial yang diisi dan belum ada akun → tidak ada yang dikerjakan.
        if ($username === null && $password === null && $existingUser === null) {
            return null;
        }

        // Tidak ada kredensial yang diisi tapi akun sudah ada → cukup sinkron name/email.
        if ($username === null && $password === null && $existingUser !== null) {
            $existingUser->forceFill([
                'name' => $teacher->name,
                'email' => $teacher->email,
            ])->save();

            return $existingUser;
        }

        // Ada username atau password → kita membuat atau memperbarui akun.
        $email = $this->normalize($teacher->email);

        if ($email === null) {
            throw ValidationException::withMessages([
                'email' => 'Email wajib diisi untuk membuat akun login.',
            ]);
        }

        $this->ensureUnique($username, 'username', 'Username sudah dipakai.', $existingUser);
        $this->ensureUnique($email, 'email', 'Email sudah dipakai akun lain.', $existingUser);

        return DB::transaction(function () use ($teacher, $existingUser, $email, $username, $password): User {
            $user = $existingUser ?: User::where('email', $email)->first();

            $payload = [
                'name' => $teacher->name,
                'email' => $email,
            ];

            if ($username !== null) {
                $payload['username'] = $username;
            }

            if ($user) {
                // Update akun yang sudah ada. Password hanya diubah bila diberikan.
                $user->forceFill($payload)->save();

                if ($password !== null) {
                    $user->forceFill(['password' => $password])->save();
                }
            } else {
                if ($password === null) {
                    throw ValidationException::withMessages([
                        'login_password' => 'Password wajib saat membuat akun baru.',
                    ]);
                }

                $payload['password'] = $password;
                $user = User::create($payload);
            }

            Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
            $user->assignRole('guru');

            $teacher->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Pastikan nilai unik pada kolom users, mengabaikan akun milik teacher ini sendiri.
     */
    private function ensureUnique(?string $value, string $column, string $message, ?User $ownUser): void
    {
        if ($value === null) {
            return;
        }

        $query = User::query()->where($column, $value);

        if ($ownUser !== null) {
            $query->whereKeyNot($ownUser->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $column === 'username' ? 'login_username' : 'email' => $message,
            ]);
        }
    }
}