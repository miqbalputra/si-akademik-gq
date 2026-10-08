<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-lg border border-warning-line bg-warning-soft p-5 shadow-sm dark:border-warning-900 dark:bg-warning-950/30">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-theme-sm font-normal text-warning-ink dark:text-warning-300">Data Demo</p>
                    <h2 class="mt-1 text-heading dark:text-white ui-card-title">{{ $demo['classroom_name'] }}</h2>
                    <p class="mt-1 text-theme-sm text-body dark:text-body">
                        Gunakan halaman ini untuk mencoba alur MVP Diniyyah sampai rapor tanpa mengingat URL manual.
                    </p>
                </div>
                <a
                    href="{{ url('/admin') }}"
                    class="inline-flex w-fit rounded-lg border border-warning-line bg-surface px-4 py-2 text-warning-ink shadow-sm hover:bg-warning-soft-strong dark:border-warning-800 dark:bg-surface dark:text-warning-200 text-theme-sm font-medium"
                >
                    Kembali ke Dashboard
                </a>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-theme-xs text-muted dark:text-muted font-normal">Santri Demo</p>
                <p class="mt-1 text-heading dark:text-white ui-metric-value">{{ $demo['student_count'] }}</p>
            </div>
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-theme-xs text-muted dark:text-muted font-normal">Presensi</p>
                <p class="mt-1 text-heading dark:text-white ui-metric-value">{{ $demo['attendance_count'] }}</p>
            </div>
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-theme-xs text-muted dark:text-muted font-normal">Leger</p>
                <p class="mt-1 text-heading dark:text-white ui-metric-value">{{ $demo['ledger_status'] }}</p>
            </div>
            <div class="rounded-lg border border-line bg-surface p-4 shadow-sm dark:border-gray-800 dark:bg-surface">
                <p class="text-theme-xs text-muted dark:text-muted font-normal">Rapor Published</p>
                <p class="mt-1 text-heading dark:text-white ui-metric-value">{{ $demo['published_report_count'] }}</p>
            </div>
        </section>

        <section class="grid gap-3 lg:grid-cols-2">
            @php
                $steps = [
                    ['number' => 1, 'title' => 'Buka Presensi Kelas Demo', 'body' => 'Lihat grid presensi bulan Juli 2025 dengan kode H, S, I, A, dan L.', 'url' => $demo['links']['attendance'], 'enabled' => true],
                    ['number' => 2, 'title' => 'Buka Input Nilai Guru Demo', 'body' => 'Masuk ke assessment latihan Bahasa Arab yang masih aktif untuk simulasi input guru.', 'url' => $demo['links']['score_input'], 'enabled' => true],
                    ['number' => 3, 'title' => 'Buka Monitoring Kabag', 'body' => 'Pantau progres input, status validasi, dan kebutuhan revisi nilai.', 'url' => $demo['links']['monitoring'], 'enabled' => true],
                    ['number' => 4, 'title' => 'Buka Leger Demo', 'body' => 'Cek nilai mapel, total, ranking, dan kolom presensi S/I/A.', 'url' => $demo['links']['ledger'], 'enabled' => (bool) $demo['ledger_id']],
                    ['number' => 5, 'title' => 'Buka Rapor Demo', 'body' => 'Preview rapor published untuk santri pertama dari kelas demo.', 'url' => $demo['links']['report_card'], 'enabled' => (bool) $demo['report_card_id']],
                    ['number' => 6, 'title' => 'Buka Dashboard Wali Santri', 'body' => 'Logout dulu lalu login sebagai wali@example.com agar dashboard wali bisa dibuka.', 'url' => $demo['links']['guardian_dashboard'], 'enabled' => true],
                ];
            @endphp

            @foreach ($steps as $step)
                <article class="rounded-lg border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
                    <div class="flex items-start gap-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning-soft-strong text-theme-sm font-medium text-warning-ink dark:bg-warning-900 dark:text-warning-100">
                            {{ $step['number'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-heading dark:text-white ui-card-title">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-theme-sm text-body dark:text-body">{{ $step['body'] }}</p>
                            @if ($step['enabled'])
                                <a
                                    href="{{ $step['url'] }}"
                                    class="mt-4 inline-flex rounded-lg bg-warning-600 px-4 py-2 text-white shadow-sm hover:bg-warning-700 text-theme-sm font-medium"
                                >
                                    Buka
                                </a>
                            @else
                                <span class="mt-4 inline-flex rounded-lg bg-surface-muted px-4 py-2 text-theme-sm font-medium text-body dark:bg-surface-muted dark:text-body">
                                    Belum tersedia
                                </span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
            <h2 class="text-heading dark:text-white ui-card-title">Akun Demo</h2>
            <p class="mt-1 text-theme-sm text-body dark:text-body">Semua akun memakai password <span class="font-semibold">password</span>.</p>

            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['role' => 'Admin', 'email' => 'admin@example.com'],
                    ['role' => 'Kabag Diniyyah', 'email' => 'kabag@example.com'],
                    ['role' => 'Kepala Sekolah', 'email' => 'kepala@example.com'],
                    ['role' => 'Guru Diniyyah', 'email' => 'guru@example.com'],
                    ['role' => 'Wali Kelas', 'email' => 'walikelas@example.com'],
                    ['role' => 'Wali Santri', 'email' => 'wali@example.com'],
                ] as $account)
                    <div class="rounded-lg bg-surface-subtle p-3 dark:bg-surface-muted">
                        <p class="text-theme-xs font-normal uppercase text-muted dark:text-muted">{{ $account['role'] }}</p>
                        <p class="mt-1 font-sans text-theme-sm text-heading dark:text-heading">{{ $account['email'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
