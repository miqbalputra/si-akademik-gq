<x-filament-panels::page>
    @php
        $connection = $audit['connection'] ?? [];
        $summary = $audit['summary'] ?? [];
        $connectionKey = $connection['key'] ?? 'unknown';
        $connectionClass = match ($connectionKey) {
            'connected' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200',
            'disabled', 'incomplete' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
            'failed' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200',
            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200',
        };
        $connectionLabel = $connection['label'] ?? 'Belum dicek';
        $checkedAt = ! empty($connection['checked_at'])
            ? \Carbon\Carbon::parse($connection['checked_at'])->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB'
            : 'Belum ada pemeriksaan';
    @endphp

    <div class="space-y-6">
        <section class="grid gap-4 lg:grid-cols-[1.25fr_1fr]">
            <article class="rounded-xl border p-5 shadow-sm {{ $connectionClass }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider opacity-70">Status koneksi umum</p>
                        <h2 class="mt-2 text-2xl font-black">{{ $connectionLabel }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 opacity-80">{{ $connection['message'] ?? 'Belum ada pemeriksaan koneksi.' }}</p>
                    </div>
                    <span class="rounded-full border border-current px-3 py-1 text-xs font-black uppercase tracking-wide">
                        {{ strtoupper($connectionKey) }}
                    </span>
                </div>

                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold opacity-70">Host</dt>
                        <dd class="mt-1 break-all font-bold">{{ ($connection['base_url'] ?? '') ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold opacity-70">Diperiksa</dt>
                        <dd class="mt-1 font-bold">{{ $checkedAt }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold opacity-70">Respons API</dt>
                        <dd class="mt-1 font-bold">{{ ($connection['latency_ms'] ?? null) !== null ? $connection['latency_ms'].' ms' : '-' }}</dd>
                    </div>
                </dl>
            </article>

            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tindakan</p>
                        <h2 class="mt-2 text-lg font-black text-gray-950 dark:text-white">Periksa ulang koneksi</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">Pemeriksaan memakai API key server dan tidak menampilkan secret ke browser.</p>
                    </div>
                    <button type="button" wire:click="refreshStatus" wire:loading.attr="disabled" class="inline-flex shrink-0 items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="refreshStatus">Cek ulang</span>
                        <span wire:loading wire:target="refreshStatus">Memeriksa...</span>
                    </button>
                </div>
            </article>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6" aria-label="Ringkasan mapping guru">
            @php
                $metrics = [
                    ['label' => 'Guru aktif', 'key' => 'total_active', 'class' => 'border-gray-200 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white'],
                    ['label' => 'Terhubung', 'key' => 'connected', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200'],
                    ['label' => 'NIY belum diisi', 'key' => 'missing_niy', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200'],
                    ['label' => 'NIY duplikat', 'key' => 'duplicate_niy', 'class' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200'],
                    ['label' => 'Tidak ditemukan', 'key' => 'not_found', 'class' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200'],
                    ['label' => 'Belum diverifikasi', 'key' => 'unverified', 'class' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200'],
                ];
            @endphp
            @foreach ($metrics as $metric)
                <article class="rounded-xl border p-4 shadow-sm {{ $metric['class'] }}">
                    <p class="text-xs font-bold uppercase tracking-wide opacity-70">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-black">{{ number_format((int) ($summary[$metric['key']] ?? 0)) }}</p>
                </article>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="text-lg font-black text-gray-950 dark:text-white">Status mapping guru aktif</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Terhubung berarti NIY lokal unik dan ditemukan sebagai <code>users.id_guru</code> aktif di GeoPresensi.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3">Guru</th>
                            <th class="px-5 py-3">Akun</th>
                            <th class="px-5 py-3">NIY</th>
                            <th class="px-5 py-3">Status GeoPresensi</th>
                            <th class="px-5 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse (($audit['teachers'] ?? []) as $teacher)
                            @php
                                $badgeClass = match ($teacher['color'] ?? 'warning') {
                                    'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200',
                                    'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200',
                                    default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-5 py-4 font-bold text-gray-950 dark:text-white">{{ $teacher['name'] }}</td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                    <div>{{ $teacher['account_name'] ?: '-' }}</div>
                                    @if ($teacher['username'])<div class="text-xs text-gray-500">{{ '@'.$teacher['username'] }}</div>@endif
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $teacher['niy'] ?: '—' }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black {{ $badgeClass }}">{{ $teacher['label'] }}</span></td>
                                <td class="max-w-md px-5 py-4 text-gray-600 dark:text-gray-300">{{ $teacher['reason'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Belum ada guru aktif untuk diverifikasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
