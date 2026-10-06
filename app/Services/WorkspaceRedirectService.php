<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

class WorkspaceRedirectService
{
    public const GURU = 'guru';
    public const KABAG_TAHFIDZ = 'kabag_tahfidz';
    public const KABAG_DINIYYAH = 'kabag_diniyyah';
    public const MANAGEMENT = 'management';
    public const WALI = 'wali';

    /**
     * @return array<string, array{label: string, description: string, destination: string}>
     */
    public function availableFor(User $user): array
    {
        $workspaces = [];

        if ($user->hasRole('guru')) {
            $workspaces[self::GURU] = [
                'label' => 'Portal Guru',
                'description' => 'Jurnal, jadwal, kelas, penilaian, dan kegiatan mengajar.',
                'destination' => route('guru.dashboard'),
            ];
        }

        if ($user->hasRole('kabag_tahfidz')) {
            $workspaces[self::KABAG_TAHFIDZ] = [
                'label' => 'Kabag Tahfidz',
                'description' => "Pantau hasil Tasmi' seluruh kelas dan PJ, lalu ekspor laporannya.",
                'destination' => route('kabag-tahfidz.dashboard'),
            ];
        }

        if ($user->hasRole('kabag_diniyyah')) {
            $workspaces[self::KABAG_DINIYYAH] = [
                'label' => 'Kabag Diniyyah',
                'description' => 'Pantau, validasi, dan kelola alur pembelajaran Diniyyah.',
                'destination' => route('kabag-diniyyah.dashboard'),
            ];
        }

        if ($user->hasAnyRole(['admin', 'kepala_sekolah'])) {
            $workspaces[self::MANAGEMENT] = [
                'label' => $user->hasRole('admin') ? 'Manajemen Admin' : 'Manajemen Akademik',
                'description' => 'Kelola data akademik, kurikulum, dan laporan sekolah.',
                'destination' => url('/admin'),
            ];
        }

        if ($user->hasRole('wali_santri')) {
            $workspaces[self::WALI] = [
                'label' => 'Portal Wali Santri',
                'description' => 'Lihat perkembangan anak, Tahfidz, agenda, dan rapor.',
                'destination' => route('wali.dashboard'),
            ];
        }

        return $workspaces;
    }

    public function needsSelection(User $user): bool
    {
        return count($this->availableFor($user)) > 1;
    }

    public function defaultDestination(User $user): string
    {
        return Arr::first($this->availableFor($user))['destination'] ?? url('/');
    }

    public function destinationFor(User $user, string $workspace): ?string
    {
        return $this->availableFor($user)[$workspace]['destination'] ?? null;
    }

    public function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        // Admin panel and management exports use an independent session guard.
        if ($request->session()->has('url.intended')) {
            $intended = (string) $request->session()->get('url.intended');
            $path = parse_url($intended, PHP_URL_PATH) ?: '';

            if (str_starts_with($path, '/admin') && $user->canAccessPanel(\Filament\Panel::make())) {
                $this->switchToAdminGuard($request, $user);
            }

            return redirect()->intended($this->defaultDestination($user));
        }

        if ($this->needsSelection($user)) {
            return redirect()->route('workspace.choose');
        }

        $destination = $this->defaultDestination($user);

        if (str_starts_with(parse_url($destination, PHP_URL_PATH) ?: '', '/admin')
            && $user->canAccessPanel(\Filament\Panel::make())) {
            $this->switchToAdminGuard($request, $user);
        }

        return redirect()->to($destination);
    }

    public function switchToAdminGuard(Request $request, User $user): void
    {
        Auth::guard('web')->logout();
        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
    }
}
