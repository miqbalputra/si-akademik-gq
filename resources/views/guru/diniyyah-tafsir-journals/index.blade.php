<x-layouts.portal title="Jurnal Tafsir" portalLabel="Portal Guru" breadcrumb="Jurnal Tafsir">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-journals.index') ? 'bg-slate-100 border-slate-300 text-slate-800' : 'text-slate-500 hover:bg-slate-50' }}">Jurnal Kelas</a>
        <a href="{{ route('guru.diniyyah-substitute-tafsir-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-substitute-tafsir-journals.index') ? 'bg-slate-100 border-slate-300 text-slate-800' : 'text-slate-500 hover:bg-slate-50' }}">Pengganti Tafsir</a>
    </x-slot>

    <div class="mb-6 glass-card rounded-2xl p-5">
        <h1 class="text-2xl font-black text-slate-900">Jurnal Tafsir Serentak</h1>
        <p class="mt-1 text-xs font-semibold text-slate-500">Sistem hanya menggabungkan kelas Tafsir yang diajar oleh Anda pada hari dan jam yang sama. Tafsir individual tetap diisi melalui Jurnal Kelas.</p>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label for="tafsir-date" class="mb-1 block text-sm font-bold text-slate-700">Tanggal jadwal</label>
            <input id="tafsir-date" type="date" name="date" value="{{ $selectedDate }}" required class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
        </div>
        <button type="submit" class="rounded-xl border border-teal-600 bg-teal-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-teal-700">Tampilkan sesi</button>
    </form>

    @if($tafsirAssignments->isEmpty())
        <div class="glass-card rounded-2xl border border-slate-200 p-10 text-center">
            <p class="text-sm font-bold text-slate-700">Anda belum memiliki penugasan Tafsir.</p>
            <p class="mt-1 text-xs text-slate-500">Minta admin menambahkan subject Tafsir Al Quran dan penugasan ke kelas Anda.</p>
        </div>
    @elseif($simultaneousGroups->isEmpty())
        <div class="glass-card rounded-2xl border border-sky-200 bg-sky-50 p-8 text-center">
            <p class="text-base font-black text-sky-900">Tidak ada sesi Tafsir serentak pada tanggal ini.</p>
            <p class="mx-auto mt-2 max-w-2xl text-sm text-sky-800">Jadwal Tafsir yang hanya berlaku untuk satu kelas, misalnya Mustawa 1 Akhwat pada Jumat, diisi sebagai jurnal individual melalui Jurnal Kelas.</p>
            <a href="{{ route('guru.diniyyah-journals.index', ['date' => $selectedDate]) }}" class="mt-5 inline-flex rounded-xl border border-sky-600 bg-white px-4 py-2 text-sm font-bold text-sky-800 transition-colors hover:bg-sky-100">Buka Jurnal Kelas</a>
        </div>
    @else
        @foreach($simultaneousGroups as $group)
            <form method="POST" action="{{ route('guru.diniyyah-tafsir-journals.store') }}" x-data="{ checked() { return [...$root.querySelectorAll('input[name=\'assignments[]\']')].filter(c => c.checked).length } }" class="mb-6">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="glass-card rounded-2xl border border-slate-200 p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-black text-slate-800">Tafsir Serentak · {{ \Carbon\Carbon::parse($group['starts_at'])->format('H:i') }}–{{ \Carbon\Carbon::parse($group['ends_at'])->format('H:i') }}</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }} · Centang kelas yang mengikuti sesi ini.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']:not(:disabled)')].forEach(c => c.checked = true)" class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[11px] font-bold text-teal-700 hover:bg-teal-100">Centang Semua</button>
                            <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']')].forEach(c => c.checked = false)" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-bold text-slate-600 hover:bg-slate-100">Kosongkan</button>
                        </div>
                    </div>

                    @if($group['agenda_assignments']->isNotEmpty())
                        <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs font-bold text-sky-800">{{ $group['agenda_assignments']->count() }} kelas dibebaskan oleh agenda tanpa KBM dan tidak perlu dibuatkan jurnal.</div>
                    @endif

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($group['assignments'] as $assignment)
                            <label for="tafsir-{{ $group['key'] }}-{{ $assignment->id }}" class="flex cursor-pointer items-start gap-2 rounded-xl border border-slate-200 bg-white p-3 transition-colors hover:bg-slate-50 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50">
                                <input type="checkbox" name="assignments[]" value="{{ $assignment->id }}" id="tafsir-{{ $group['key'] }}-{{ $assignment->id }}" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(in_array($assignment->id, $group['preselected_assignment_ids'], true)) @disabled($group['agenda_assignments']->has($assignment->id))>
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">{{ $assignment->classSubject->classroomTerm->name ?? $assignment->classSubject->classroomTerm->classroom->name }}</span>
                                    <span class="mt-0.5 block text-[10px] font-semibold uppercase text-slate-400">Tafsir · {{ \Carbon\Carbon::parse($group['starts_at'])->format('H:i') }}–{{ \Carbon\Carbon::parse($group['ends_at'])->format('H:i') }}</span>
                                    @if($group['agenda_assignments']->has($assignment->id))<span class="mt-1 block text-[10px] font-black text-sky-700">Agenda tanpa KBM</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 md:grid-cols-[1fr_auto] md:items-end">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Materi Tafsir</label>
                            <textarea name="material" rows="4" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="Tuliskan materi yang diajarkan ke kelas tercentang..."></textarea>
                            <p class="mt-2 text-xs text-slate-500"><span x-text="checked()"></span> dari {{ $group['assignments']->count() }} kelas tercentang.</p>
                        </div>
                        <button type="submit" class="rounded-xl bg-teal-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="checked() === 0">Simpan Jurnal Serentak</button>
                    </div>
                </div>
            </form>
        @endforeach
    @endif
</x-layouts.portal>
