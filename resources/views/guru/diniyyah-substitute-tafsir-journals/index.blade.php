<x-layouts.portal title="Jurnal Pengganti Tafsir" portalLabel="Portal Guru" breadcrumb="Jurnal Pengganti Tafsir">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-substitute-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-substitute-journals.index') ? 'bg-surface-muted border-line-strong text-heading' : 'text-muted hover:bg-surface-subtle' }}">Jurnal Pengganti</a>
    </x-slot>

    <div class="mb-6 glass-card rounded-2xl p-5">
        <h1 class="text-2xl font-semibold text-heading">Jurnal Pengganti Tafsir Serentak</h1>
        <p class="mt-1 text-xs font-semibold text-muted">Hanya sesi Tafsir milik guru lain yang berlangsung bersamaan untuk beberapa kelas yang dapat diisi sekaligus dari halaman ini.</p>
    </div>

    @if(session('success'))<div class="mb-6 rounded-xl border border-success-line bg-success-soft p-4 text-sm text-success-ink">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-6 rounded-xl border border-danger-line bg-danger-soft p-4 text-sm text-danger-ink">{{ session('error') }}</div>@endif

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-surface p-4 shadow-sm">
        <div>
            <label for="sub-tafsir-date" class="mb-1 block text-sm font-bold text-body">Tanggal jadwal</label>
            <input id="sub-tafsir-date" type="date" name="date" value="{{ $selectedDate }}" required class="rounded-xl border-line-strong text-sm shadow-sm focus:border-warning-500 focus:ring-warning-500">
        </div>
        <button type="submit" class="rounded-xl bg-warning-600 px-4 py-2 text-sm font-bold text-white hover:bg-warning-700">Tampilkan sesi</button>
    </form>

    @if($simultaneousGroups->isEmpty())
        <div class="glass-card rounded-2xl border border-warning-line bg-warning-soft p-8 text-center">
            <p class="text-base font-semibold text-warning-ink">Tidak ada sesi Tafsir serentak yang dapat digantikan pada tanggal ini.</p>
            <p class="mt-2 text-sm text-warning-ink">Tafsir untuk satu kelas tetap dapat diisi melalui Jurnal Pengganti reguler.</p>
            <a href="{{ route('guru.diniyyah-substitute-journals.index', ['date' => $selectedDate]) }}" class="mt-5 inline-flex rounded-xl border border-warning-600 bg-surface px-4 py-2 text-sm font-bold text-warning-ink hover:bg-warning-soft-strong">Buka Jurnal Pengganti</a>
        </div>
    @else
        @foreach($simultaneousGroups as $group)
            <form method="POST" action="{{ route('guru.diniyyah-substitute-tafsir-journals.store') }}" x-data="{ checked() { return [...$root.querySelectorAll('input[name=\'assignments[]\']')].filter(c => c.checked && !c.disabled).length } }" class="mb-6">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">
                <div class="glass-card rounded-2xl border border-line p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-line pb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-heading">Tafsir Serentak · {{ \Carbon\Carbon::parse($group['starts_at'])->format('H:i') }}–{{ \Carbon\Carbon::parse($group['ends_at'])->format('H:i') }}</h2>
                            <p class="mt-1 text-xs text-muted">Guru asli: {{ $group['assignments']->first()?->teacher?->name ?? '-' }} · {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']')].filter(c => !c.disabled).forEach(c => c.checked = true)" class="rounded-lg border border-warning-line bg-warning-soft px-2 py-1 text-[11px] font-bold text-warning-ink hover:bg-warning-soft-strong">Centang Semua</button>
                            <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']')].forEach(c => c.checked = false)" class="rounded-lg border border-line bg-surface-subtle px-2 py-1 text-[11px] font-bold text-body hover:bg-surface-muted">Kosongkan</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($group['assignments'] as $assignment)
                            @php($agenda = $group['agenda_assignments'][$assignment->id] ?? null)
                            <label for="sub-tafsir-{{ $group['key'] }}-{{ $assignment->id }}" class="flex items-start gap-2 rounded-xl border p-3 transition-colors {{ $agenda ? 'cursor-not-allowed border-info-line bg-info-soft' : 'cursor-pointer border-line bg-surface hover:bg-surface-subtle has-[:checked]:border-warning-500 has-[:checked]:bg-warning-soft' }}">
                                <input type="checkbox" name="assignments[]" value="{{ $assignment->id }}" id="sub-tafsir-{{ $group['key'] }}-{{ $assignment->id }}" @disabled($agenda) class="mt-0.5 rounded border-line-strong text-warning-ink focus:ring-warning-500 disabled:cursor-not-allowed">
                                <span>
                                    <span class="block text-sm font-bold text-heading">{{ $assignment->classSubject->classroomTerm->name }}</span>
                                    <span class="mt-0.5 block text-[10px] font-semibold uppercase text-soft">Tafsir · {{ \Carbon\Carbon::parse($group['starts_at'])->format('H:i') }}–{{ \Carbon\Carbon::parse($group['ends_at'])->format('H:i') }}</span>
                                    @if($agenda)<span class="mt-1 block text-xs font-bold text-info-ink">{{ $agenda['reason'] }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 border-t border-line pt-5 md:grid-cols-[1fr_auto] md:items-end">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-body">Materi Tafsir</label>
                            <textarea name="material" rows="4" required class="w-full rounded-xl border-line-strong text-sm shadow-sm focus:border-warning-500 focus:ring-warning-500" placeholder="Tuliskan materi yang diajarkan..."></textarea>
                            <p class="mt-2 text-xs text-muted"><span x-text="checked()"></span> dari {{ $group['assignments']->count() }} kelas tercentang.</p>
                        </div>
                        <button type="submit" class="rounded-xl bg-warning-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-warning-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="checked() === 0">Simpan Jurnal Pengganti</button>
                    </div>
                </div>
            </form>
        @endforeach
    @endif
</x-layouts.portal>
