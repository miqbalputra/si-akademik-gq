<x-layouts.portal title="Edit Jurnal Kelas Diniyyah" portalLabel="Portal Guru" breadcrumb="Edit Jurnal Kelas">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index', ['classroom_term_id' => $classroomTerm->id, 'date' => $journal->date->format('Y-m-d')]) }}" class="btn btn-outline btn-sm text-theme-sm font-medium">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Jurnal Kelas</span>
        </a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <h1 class="text-heading ui-page-title">Edit Jurnal</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-success-soft border border-success-line text-theme-sm font-medium text-success-ink">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass-card rounded-2xl p-6 border border-line">
        <!-- Konteks read-only: kelas, mapel, tanggal, sesi tidak bisa diubah -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5 p-4 bg-surface-subtle rounded-xl border border-line">
            <div>
                <span class="block text-theme-xs font-normal text-soft uppercase mb-0.5">Kelas</span>
                <span class="text-theme-sm font-medium text-heading break-words">{{ $classroomTerm->name }}</span>
            </div>
            <div>
                <span class="block text-theme-xs font-normal text-soft uppercase mb-0.5">Mata Pelajaran</span>
                <span class="text-theme-sm font-medium text-heading break-words">{{ $journal->teacherAssignment->classSubject->subject->name }}</span>
            </div>
            <div>
                <span class="block text-theme-xs font-normal text-soft uppercase mb-0.5">Tanggal</span>
                <span class="text-theme-sm font-medium text-heading break-words">{{ $journal->date->locale('id')->translatedFormat('l, d F Y') }}</span>
            </div>
            <div>
                <span class="block text-theme-xs font-normal text-soft uppercase mb-0.5">Sesi</span>
                <span class="text-theme-sm font-medium text-heading break-words">
                    {{ $sessionLabel }}
                    @if($sessionTime['starts_at'])
                        <span class="text-theme-xs font-normal text-muted">({{ \Carbon\Carbon::parse($sessionTime['starts_at'])->format('H:i') }} - {{ \Carbon\Carbon::parse($sessionTime['ends_at'])->format('H:i') }})</span>
                    @endif
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('guru.diniyyah-journals.update', $journal) }}">
            @csrf
            @method('PUT')

            <div class="mb-5">
                <label for="material" class="block text-body mb-1.5 ui-form-label">Materi</label>
                <textarea id="material" name="material" rows="3" required class="w-full rounded-xl border-line-strong shadow-sm focus:ring-success-500 focus:border-success-500 text-theme-sm font-normal">{{ $journal->material }}</textarea>
            </div>

            <div class="bg-surface-subtle border border-line rounded-xl p-4">
                <h4 class="text-theme-sm font-medium text-heading mb-1">Presensi Sesi Ini</h4>
                <p class="text-theme-xs text-muted mb-3 font-normal">Centang santri yang tidak hadir. Santri yang sudah absen harian oleh wali kelas otomatis tercatat.</p>

                @if($students->isEmpty())
                    <p class="text-theme-sm text-muted italic">Tidak ada santri aktif di kelas ini.</p>
                @else
                    @include('guru.diniyyah-journals.partials._absence-grid', ['existingAbsences' => $existingAbsences])
                @endif
            </div>

            <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="{{ route('guru.diniyyah-journals.index', ['classroom_term_id' => $classroomTerm->id, 'date' => $journal->date->format('Y-m-d')]) }}" class="text-center rounded-xl px-6 py-3 text-theme-sm font-medium text-body border border-line-strong hover:bg-surface-subtle transition-colors">
                    Batal
                </a>
                <button type="submit" class="rounded-xl bg-success-600 px-6 py-3 text-white hover:bg-success-700 shadow-sm transition-colors text-theme-sm font-medium">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal>