<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WorkspaceRedirectService
{
    public const GURU = 'guru';
    public const KABAG_TAHFIDZ = 'kabag_tahfidz';
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
                'destination' => route('admin.tasmi-report.index'),
            ];
        }

        if ($user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])) {
            $workspaces[self::MANAGEMENT] = [
                'label' => 'Manajemen Akademik',
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
        // Laravel menyimpan tujuan yang dilindungi middleware auth di session.
        // Biarkan middleware tujuan menguji otorisasi per-rute setelah login.
        if ($request->session()->has('url.intended')) {
            return redirect()->intended($this->defaultDestination($user));
        }

        if ($this->needsSelection($user)) {
            return redirect()->route('workspace.choose');
        }

        return redirect()->to($this->defaultDestination($user));
    }
}
