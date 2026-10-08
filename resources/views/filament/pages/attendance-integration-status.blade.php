<x-filament-panels::page>
    @php
        $connection = $audit['connection'] ?? [];
        $summary = $audit['summary'] ?? [];
        $connectionKey = $connection['key'] ?? 'unknown';
        $connectionClass = match ($connectionKey) {
            'connected' => 'border-success-line bg-success-soft text-success-ink dark:border-success-900 dark:bg-success-950/30 dark:text-success-200',
            'disabled', 'incomplete' => 'border-warning-line bg-warning-soft text-warning-ink dark:border-warning-900 dark:bg-warning-950/30 dark:text-warning-200',
            'failed' => 'border-danger-line bg-danger-soft text-danger-ink dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-200',
            default => 'border-line bg-surface-subtle text-body dark:border-gray-700 dark:bg-surface dark:text-heading',
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
                        <h2 class="mt-2 text-2xl font-semibold">{{ $connectionLabel }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 opacity-80">{{ $connection['message'] ?? 'Belum ada pemeriksaan koneksi.' }}</p>
                    </div>
                    <span class="rounded-full border border-current px-3 py-1 text-xs font-semibold uppercase tracking-wide">
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

            <article class="rounded-xl border border-line bg-surface p-5 shadow-sm dark:border-gray-800 dark:bg-surface">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-muted dark:text-muted">Tindakan</p>
                        <h2 class="mt-2 text-lg font-semibold text-heading dark:text-white">Periksa ulang koneksi</h2>
                        <p class="mt-2 text-sm leading-6 text-body dark:text-body">Pemeriksaan memakai API key server dan tidak menampilkan secret ke browser.</p>
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
                    ['label' => 'Guru aktif', 'key' => 'total_active', 'class' => 'border-line bg-surface text-heading dark:border-gray-800 dark:bg-surface dark:text-white'],
                    ['label' => 'Terhubung', 'key' => 'connected', 'class' => 'border-success-line bg-success-soft text-success-ink dark:border-success-900 dark:bg-success-950/30 dark:text-success-200'],
                    ['label' => 'NIY belum diisi', 'key' => 'missing_niy', 'class' => 'border-warning-line bg-warning-soft text-warning-ink dark:border-warning-900 dark:bg-warning-950/30 dark:text-warning-200'],
                    ['label' => 'NIY duplikat', 'key' => 'duplicate_niy', 'class' => 'border-danger-line bg-danger-soft text-danger-ink dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-200'],
                    ['label' => 'Tidak ditemukan', 'key' => 'not_found', 'class' => 'border-danger-line bg-danger-soft text-danger-ink dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-200'],
                    ['label' => 'Belum diverifikasi', 'key' => 'unverified', 'class' => 'border-warning-line bg-warning-soft text-warning-ink dark:border-warning-900 dark:bg-warning-950/30 dark:text-warning-200'],
                ];
            @endphp
            @foreach ($metrics as $metric)
                <article class="rounded-xl border p-4 shadow-sm {{ $metric['class'] }}">
                    <p class="text-xs font-bold uppercase tracking-wide opacity-70">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold">{{ number_format((int) ($summary[$metric['key']] ?? 0)) }}</p>
                </article>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-xl border border-line bg-surface shadow-sm dark:border-gray-800 dark:bg-surface">
            <div class="border-b border-line px-5 py-4 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-heading dark:text-white">Status mapping guru aktif</h2>
                <p class="mt-1 text-sm text-body dark:text-body">Terhubung berarti NIY lokal unik dan ditemukan sebagai <code>users.id_guru</code> aktif di GeoPresensi.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-surface-subtle text-xs uppercase tracking-wide text-muted dark:bg-canvas dark:text-muted">
                        <tr>
                            <th class="px-5 py-3">Guru</th>
                            <th class="px-5 py-3">Akun</th>
                            <th class="px-5 py-3">NIY</th>
                            <th class="px-5 py-3">Status GeoPresensi</th>
                            <th class="px-5 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-gray-800">
                        @forelse (($audit['teachers'] ?? []) as $teacher)
                            @php
                                $badgeClass = match ($teacher['color'] ?? 'warning') {
                                    'success' => 'bg-success-soft-strong text-success-ink dark:bg-success-900/60 dark:text-success-200',
                                    'danger' => 'bg-danger-soft-strong text-danger-ink dark:bg-danger-900/60 dark:text-danger-200',
                                    default => 'bg-warning-soft-strong text-warning-ink dark:bg-warning-900/60 dark:text-warning-200',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-5 py-4 font-bold text-heading dark:text-white">{{ $teacher['name'] }}</td>
                                <td class="px-5 py-4 text-body dark:text-body">
                                    <div>{{ $teacher['account_name'] ?: '-' }}</div>
                                    @if ($teacher['username'])<div class="text-xs text-muted">{{ '@'.$teacher['username'] }}</div>@endif
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-body dark:text-heading">{{ $teacher['niy'] ?: '—' }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $teacher['label'] }}</span></td>
                                <td class="max-w-md px-5 py-4 text-body dark:text-body">{{ $teacher['reason'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-muted">Belum ada guru aktif untuk diverifikasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
