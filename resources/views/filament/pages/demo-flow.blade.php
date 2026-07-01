<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Data Demo</p>
                    <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $demo['classroom_name'] }}</h2>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Gunakan halaman ini untuk mencoba alur MVP Diniyyah sampai rapor tanpa mengingat URL manual.
                    </p>
                </div>
                <a
                    href="{{ url('/admin') }}"
                    class="inline-flex w-fit rounded-lg border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm hover:bg-amber-100 dark:border-amber-800 dark:bg-gray-900 dark:text-amber-200"
                >
                    Kembali ke Dashboard
                </a>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Santri Demo</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $demo['student_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Presensi</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $demo['attendance_count'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Leger</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $demo['ledger_status'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">Rapor Published</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $demo['published_report_count'] }}</p>
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
                <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-start gap-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-800 dark:bg-amber-900 dark:text-amber-100">
                            {{ $step['number'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $step['body'] }}</p>
                            @if ($step['enabled'])
                                <a
                                    href="{{ $step['url'] }}"
                                    class="mt-4 inline-flex rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700"
                                >
                                    Buka
                                </a>
                            @else
                                <span class="mt-4 inline-flex rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    Belum tersedia
                                </span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold text-gray-950 dark:text-white">Akun Demo</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Semua akun memakai password <span class="font-semibold">password</span>.</p>

            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['role' => 'Admin', 'email' => 'admin@example.com'],
                    ['role' => 'Kabag Diniyyah', 'email' => 'kabag@example.com'],
                    ['role' => 'Kepala Sekolah', 'email' => 'kepala@example.com'],
                    ['role' => 'Guru Diniyyah', 'email' => 'guru@example.com'],
                    ['role' => 'Wali Kelas', 'email' => 'walikelas@example.com'],
                    ['role' => 'Wali Santri', 'email' => 'wali@example.com'],
                ] as $account)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $account['role'] }}</p>
                        <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $account['email'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
