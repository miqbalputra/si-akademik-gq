<x-layouts.portal title="Edit Jurnal Kelas Diniyyah" portalLabel="Portal Guru" breadcrumb="Edit Jurnal Kelas">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index', ['classroom_term_id' => $classroomTerm->id, 'date' => $journal->date->format('Y-m-d')]) }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Jurnal Kelas</span>
        </a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <h1 class="text-2xl font-black text-slate-900">Edit Jurnal</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass-card rounded-2xl p-6 border border-slate-200">
        <!-- Konteks read-only: kelas, mapel, tanggal, sesi tidak bisa diubah -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5 p-4 bg-slate-50 rounded-xl border border-slate-200">
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Kelas</span>
                <span class="text-sm font-bold text-slate-800 break-words">{{ $classroomTerm->name }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Mata Pelajaran</span>
                <span class="text-sm font-bold text-slate-800 break-words">{{ $journal->teacherAssignment->classSubject->subject->name }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Tanggal</span>
                <span class="text-sm font-bold text-slate-800 break-words">{{ $journal->date->locale('id')->translatedFormat('l, d F Y') }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Sesi</span>
                <span class="text-sm font-bold text-slate-800 break-words">
                    {{ $sessionLabel }}
                    @if($sessionTime['starts_at'])
                        <span class="text-xs font-medium text-slate-500">({{ \Carbon\Carbon::parse($sessionTime['starts_at'])->format('H:i') }} - {{ \Carbon\Carbon::parse($sessionTime['ends_at'])->format('H:i') }})</span>
                    @endif
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('guru.diniyyah-journals.update', $journal) }}">
            @csrf
            @method('PUT')

            <div class="mb-5">
                <label for="material" class="block text-sm font-bold text-slate-700 mb-1.5">Materi</label>
                <textarea id="material" name="material" rows="3" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-emerald-500 focus:border-emerald-500">{{ $journal->material }}</textarea>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                <h4 class="text-sm font-bold text-slate-800 mb-1">Presensi Sesi Ini</h4>
                <p class="text-xs text-slate-500 mb-3">Centang santri yang tidak hadir. Santri yang sudah absen harian oleh wali kelas otomatis tercatat.</p>

                @if($students->isEmpty())
                    <p class="text-sm text-slate-500 italic">Tidak ada santri aktif di kelas ini.</p>
                @else
                    @include('guru.diniyyah-journals.partials._absence-grid', ['existingAbsences' => $existingAbsences])
                @endif
            </div>

            <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="{{ route('guru.diniyyah-journals.index', ['classroom_term_id' => $classroomTerm->id, 'date' => $journal->date->format('Y-m-d')]) }}" class="text-center rounded-xl px-6 py-3 text-sm font-bold text-slate-600 border border-slate-300 hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal>