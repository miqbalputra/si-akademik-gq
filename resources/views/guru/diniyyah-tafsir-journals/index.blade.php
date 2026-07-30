<x-layouts.portal title="Jurnal Tafsir" portalLabel="Portal Guru" breadcrumb="Jurnal Tafsir">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-journals.index') ? 'bg-slate-100 border-slate-300 text-slate-800' : 'text-slate-500 hover:bg-slate-50' }}">Jurnal Kelas</a>
        <a href="{{ route('guru.diniyyah-substitute-tafsir-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-substitute-tafsir-journals.index') ? 'bg-slate-100 border-slate-300 text-slate-800' : 'text-slate-500 hover:bg-slate-50' }}">Pengganti Tafsir</a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Jurnal Tafsir (Serentak)</h1>
            <p class="text-xs font-semibold text-slate-500 mt-1">Tafsir Kamis 09:50-10:20 diajar serentak ke beberapa kelas. Cukup isi 1 materi, sistem membuat 1 jurnal per kelas Anda.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm font-medium text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if($tafsirAssignments->isEmpty())
        <div class="glass-card rounded-2xl p-10 border border-slate-200 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13M12 6.253C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253M12 6.253C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <p class="text-sm font-bold text-slate-700">Anda belum memiliki penugasan Tafsir.</p>
            <p class="text-xs text-slate-500 mt-1">Minta admin menambahkan di menu Diniyyah: subject <strong>Tafsir Al Quran</strong> + penugasan ke kelas Anda.</p>
        </div>
    @else
        <form method="POST" action="{{ route('guru.diniyyah-tafsir-journals.store') }}" x-data="{ checked() { return [...$root.querySelectorAll('input[name=\'assignments[]\']')].filter(c => c.checked).length } }">
            @csrf
            <!-- Daftar kelas Tafsir -->
            <div class="glass-card rounded-2xl p-6 mb-6 border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-lg font-black text-slate-800">Kelas Tafsir Anda</h2>
                    <div class="flex gap-2">
                        <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']')].forEach(c => c.checked = true)" class="text-[11px] font-bold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg px-2 py-1 hover:bg-teal-100">Centang Semua</button>
                        <button type="button" @click="[...$root.querySelectorAll('input[name=\'assignments[]\']')].forEach(c => c.checked = false)" class="text-[11px] font-bold text-slate-600 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 hover:bg-slate-100">Kosongkan</button>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mb-4">Centang kelas yang ikut sesi Tafsir Kamis ini (sesi 09:50-10:20). Kelas yang sudah ada jurnal Tafsir di tanggal ini akan di-skip.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($tafsirAssignments as $assignment)
                        <label for="tafsir-{{ $assignment->id }}" class="rounded-xl border border-slate-200 bg-white p-3 flex items-start gap-2 cursor-pointer hover:bg-slate-50 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 transition-colors">
                            <input type="checkbox" name="assignments[]" value="{{ $assignment->id }}" id="tafsir-{{ $assignment->id }}" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                            <span>
                                <span class="block text-xs font-bold text-slate-800">{{ $assignment->classSubject->classroomTerm->name ?? $assignment->classSubject->classroomTerm->classroom->name }}</span>
                                <span class="block text-[10px] uppercase text-slate-400 font-semibold mt-0.5">Tafsir · 09:50-10:20</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Form Serentak -->
            <div class="glass-card rounded-2xl p-6 border border-slate-200">
                <h3 class="text-lg font-black text-slate-800 mb-2 border-b border-slate-100 pb-2">Isi Jurnal Tafsir</h3>
                <p class="text-xs font-semibold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg p-3 mb-4">
                    Pilih <strong>tanggal Kamis</strong>, centang kelas yang ikut, lalu tulis materi sekali. Jurnal hanya dibuat untuk <strong>kelas yang tercentang</strong>. Kelas yang sudah ada jurnal Tafsir di tanggal ini akan di-skip.
                </p>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="sm:col-span-1 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tanggal (harus Kamis)</label>
                            <input type="date" name="date" value="{{ $selectedDate }}" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm py-2 focus:ring-teal-500 focus:border-teal-500">
                            <p class="text-[10px] text-slate-400 mt-1">Tafsir hanya pada hari Kamis.</p>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Materi</label>
                        <textarea name="material" rows="6" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-teal-500 focus:border-teal-500" placeholder="Tuliskan materi Tafsir yang diajarkan ke semua kelas..."></textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-slate-500">
                        <span x-text="checked()"></span> dari {{ $tafsirAssignments->count() }} kelas tercentang.
                    </p>
                    <button type="submit" class="rounded-xl bg-teal-600 px-6 py-3 text-sm font-bold text-white hover:bg-teal-700 shadow-sm transition-colors" :class="{ 'opacity-50 cursor-not-allowed': checked() === 0 }" :disabled="checked() === 0">
                        Buat Jurnal untuk Kelas Tercentang
                    </button>
                </div>
            </div>
        </form>
    @endif
</x-layouts.portal>