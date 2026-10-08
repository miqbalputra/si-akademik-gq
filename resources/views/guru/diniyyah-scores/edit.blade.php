<x-layouts.portal title="{{ $assessmentSet->title }}" portalLabel="Portal Guru" breadcrumb="Input Nilai / {{ $assessmentSet->classSubject?->classroomTerm?->name }}">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-scores.index') }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Daftar</span>
        </a>
    </x-slot>

    @push('head')
    <style>
        @media (max-width: 767px) {
            .score-matrix thead { display:none; }
            .score-matrix, .score-matrix tbody { display:block; width:100%; }
            .score-matrix tr {
                display:block; margin:0 0 1rem; padding:1rem 1.25rem;
                border-radius:1rem; background:var(--ui-surface); border:1px solid var(--ui-surface-muted);
                box-shadow:0 1px 3px rgba(0,0,0,.04);
            }
            .score-matrix td {
                display:flex; align-items:center; justify-content:space-between;
                gap:1rem; padding:0.5rem 0; border:none; position:static;
            }
            .score-matrix td::before {
                content:attr(data-label); font-size:11px; font-weight:700;
                text-transform:uppercase; letter-spacing:.04em; color:var(--ui-muted);
            }
            .score-matrix td[data-label="Santri"]::before { display:none; }
            .score-matrix td[data-label="Santri"] {
                font-size:15px; padding-bottom:.5rem;
                border-bottom:1px solid var(--ui-surface-muted); margin-bottom:.25rem;
            }
        }
    </style>
    @endpush

    <!-- Header Info -->
    <header class="mb-6 rounded-2xl glass-card p-6 sm:p-8 animate-fade-in-up">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-soft hover:text-warning-ink mb-3 transition-colors sm:hidden">
                    &larr; Kembali
                </a>
                <br class="sm:hidden">
                <span class="inline-flex items-center rounded-full bg-brand-soft border border-brand-line px-2.5 py-0.5 text-xs font-bold text-brand-ink mb-3">
                    Tugas Pengisian Nilai
                </span>
                <h1 class="text-2xl font-semibold text-heading leading-tight">{{ $assessmentSet->title }}</h1>
                <p class="mt-2 text-sm font-semibold text-muted">
                    {{ $assessmentSet->classSubject?->classroomTerm?->name }} &middot; {{ $assessmentSet->classSubject?->subject?->name }}
                </p>
            </div>
            
            <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4 lg:min-w-[450px]">
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">KKM</p>
                    <p class="mt-1 font-semibold text-heading text-lg">{{ $assessmentSet->kkm ?? '-' }}</p>
                </div>
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Terisi</p>
                    <p class="mt-1 font-semibold text-heading text-lg">{{ $filledCells }}/{{ $totalCells }}</p>
                </div>
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted">Lengkap</p>
                    <p class="mt-1 font-semibold text-heading text-lg">{{ $completeStudents }}/{{ $enrollments->count() }}</p>
                </div>
                <div class="rounded-2xl border border-warning-line bg-warning-soft/80 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-warning-ink">Progres</p>
                    <p class="mt-1 font-semibold text-warning-ink text-lg">{{ $completionPercentage }}%</p>
                </div>
            </div>
        </div>

        <!-- Validation/Submission Status Alert -->
        <div class="mt-6 flex flex-col gap-4 rounded-2xl bg-surface-subtle/80 border border-line/50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-soft">Status Validasi</p>
                <p class="mt-1 text-sm font-semibold text-heading tracking-wide">{{ \App\Support\UiLabel::statusLabel($assessmentSet->status) }}</p>
            </div>
            @if (in_array($assessmentSet->status, ['active', 'needs_revision'], true))
                <form method="POST" action="{{ route('guru.diniyyah-scores.submit', $assessmentSet) }}" class="w-full sm:w-auto">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full justify-center rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-md transition-all {{ $completionPercentage >= 100 && $enrollments->count() > 0 ? 'bg-success-600 hover:bg-success-700 hover:shadow-success-500/20' : 'bg-line-strong' }}"
                        @disabled($completionPercentage < 100 || $enrollments->count() === 0)
                    >
                        Submit ke Kabag Diniyyah
                    </button>
                </form>
            @else
                <span class="inline-flex items-center rounded-xl bg-surface px-4 py-2 text-xs font-bold text-body border border-line">
                    Nilai sudah dikunci / divalidasi
                </span>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-success-line bg-success-soft p-4 text-sm font-bold text-success-ink shadow-sm animate-fade-in-up">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-danger-line bg-danger-soft p-4 text-sm font-bold text-danger-ink shadow-sm animate-fade-in-up">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Filter bar -->
    <div class="mb-6 rounded-2xl glass-card p-4 animate-fade-in-up" style="animation-delay:100ms;">
        <label for="student-filter" class="text-[10px] font-bold uppercase tracking-wider text-muted">Cari santri</label>
        <input
            id="student-filter"
            type="search"
            placeholder="Ketik nama atau NIS santri..."
            class="mt-2 w-full rounded-2xl border-2 border-line bg-surface/50 px-4 py-2.5 text-sm font-semibold shadow-sm outline-none transition-all placeholder:text-soft focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10"
        >
    </div>

    <!-- Form Table -->
    <form method="POST" action="{{ route('guru.diniyyah-scores.update', $assessmentSet) }}" class="rounded-2xl glass-card shadow-sm overflow-hidden animate-fade-in-up" style="animation-delay:150ms;">
        @csrf
        @method('PUT')

        <div class="overflow-visible md:overflow-x-auto">
            <table class="score-matrix w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-surface-subtle border-b border-line text-[10px] font-bold uppercase tracking-wider text-muted">
                        <th class="md:sticky md:left-0 md:z-10 md:bg-surface-subtle px-6 py-4">Santri</th>
                        @foreach ($assessmentSet->components as $component)
                            <th class="px-6 py-4">{{ $component->name }}</th>
                        @endforeach
                        <th class="px-6 py-4">Nilai Akhir</th>
                    </tr>
                </thead>
                <tbody id="score-rows" class="divide-y divide-line">
                    @foreach ($enrollments as $enrollment)
                        @php
                            $result = $results->get($enrollment->id);
                            $studentComplete = (bool) $result?->is_complete;
                        @endphp
                        <tr class="hover:bg-surface-subtle/50 transition-colors" data-student="{{ Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                            <td data-label="Santri" class="md:sticky md:left-0 md:z-10 md:bg-surface px-6 py-4 font-bold">
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0">
                                        <div class="text-heading text-sm font-bold truncate">{{ $enrollment->student?->name }}</div>
                                        <div class="text-xs font-semibold text-soft mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0 {{ $studentComplete ? 'bg-success-soft text-success-ink border border-success-line' : 'bg-warning-soft text-warning-ink border border-warning-line' }}">
                                        {{ $studentComplete ? 'Lengkap' : 'Belum' }}
                                    </span>
                                </div>
                            </td>
                            @foreach ($assessmentSet->components as $component)
                                @php($score = $scores->get($enrollment->id.'-'.$component->id)?->score)
                                <td data-label="{{ $component->name }}" class="px-6 py-3">
                                    <input
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        name="scores[{{ $enrollment->id }}][{{ $component->id }}]"
                                        value="{{ old('scores.'.$enrollment->id.'.'.$component->id, $score) }}"
                                        placeholder="0.00"
                                        class="w-24 rounded-xl border-2 {{ $component->code === 'keaktifan_presensi' ? 'border-brand-line bg-brand-soft/50 text-brand-ink' : 'border-line bg-surface/50 text-heading' }} px-3 py-2 text-center text-sm font-bold shadow-sm outline-none transition-all focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10"
                                        @readonly($component->code === 'keaktifan_presensi')
                                        @disabled(! in_array($assessmentSet->status, ['active', 'needs_revision'], true) && ! auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
                                    >
                                    @if($component->code === 'keaktifan_presensi')
                                        <div class="mt-1 text-[8px] font-bold text-center text-brand-400 uppercase tracking-wide">Otomatis</div>
                                    @endif
                                </td>
                            @endforeach
                            <td data-label="Nilai Akhir" class="px-6 py-3 font-semibold text-heading text-base">
                                {{ $result?->final_score ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer Action Block -->
        <div class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-line/60 bg-surface/90 p-5 backdrop-blur-md">
            <p class="text-xs font-semibold text-muted">Simpan perubahan Anda sebagai draf kapan saja untuk dihitung otomatis.</p>
            <button
                class="rounded-xl px-5 py-3 text-xs font-bold text-white shadow-md transition-all {{ in_array($assessmentSet->status, ['active', 'needs_revision'], true) || auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ? 'bg-warning-600 hover:bg-warning-700 hover:shadow-brand-500/20' : 'bg-gray-400' }}"
                type="submit"
                @disabled(! in_array($assessmentSet->status, ['active', 'needs_revision'], true) && ! auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
            >
                Simpan Perubahan
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        const filter = document.getElementById('student-filter');
        const rows = Array.from(document.querySelectorAll('#score-rows tr'));

        filter?.addEventListener('input', () => {
            const value = filter.value.trim().toLowerCase();

            rows.forEach((row) => {
                row.hidden = value.length > 0 && ! row.dataset.student.includes(value);
            });
        });
    </script>
    @endpush
</x-layouts.portal>
