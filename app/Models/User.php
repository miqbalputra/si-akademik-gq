<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function savedFilters(): HasMany
    {
        return $this->hasMany(PanelSavedFilter::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(PanelUserPreference::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PanelNotification::class);
    }

    public function exportRequests(): HasMany
    {
        return $this->hasMany(ReportExportRequest::class, 'requested_by');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'kabag_diniyyah', 'kabag_tahfidz', 'kepala_sekolah']);
    }

    /**
     * Apakah user ini adalah guru yang ditugaskan sebagai PJ Tasmi' pada
     * periode akademik aktif. Dipakai untuk menampilkan menu "Tasmi'" di
     * dashboard guru.
     */
    public function isTasmiExaminer(): bool
    {
        $teacher = $this->teacher;

        if (! $teacher) {
            return false;
        }

        return $teacher->isTasmiExaminer();
    }

    /**
     * Presensi kelas hanya tersedia untuk manajemen atau guru yang sedang
     * mendapat penugasan wali kelas aktif.
     */
    public function canAccessAttendance(): bool
    {
        if ($this->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])) {
            return true;
        }

        $teacherId = $this->teacher?->id;
        if (! $teacherId) {
            return false;
        }

        return HomeroomAssignment::query()
            ->where('teacher_id', $teacherId)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
