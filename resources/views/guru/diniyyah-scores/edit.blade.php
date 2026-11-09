<x-layouts.portal title="{{ $assessmentSet->title }}" portalLabel="Portal Guru" breadcrumb="Input Nilai / {{ $assessmentSet->classSubject?->classroomTerm?->name }}">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-scores.index') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">
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
                content:attr(data-label);
                text-transform:none; letter-spacing:normal; color:var(--ui-muted); font-size:12px; line-height:18px; font-weight:400; }
            .score-matrix td[data-label="Santri"]::before { display:none; }
            .score-matrix td[data-label="Santri"] {  padding-bottom:.5rem;
                border-bottom:1px solid var(--ui-surface-muted); margin-bottom:.25rem; font-size:14px; line-height:20px; font-weight:500; }
        }
    </style>
    @endpush

    <div data-score-editor data-can-edit="{{ $canEdit ? '1' : '0' }}"
        data-autosave-url="{{ route('guru.diniyyah-scores.autosave', $assessmentSet) }}"
        data-component-url="{{ route('guru.diniyyah-scores.components.store', $assessmentSet) }}"
        data-next-ulangan="{{ $nextComponents['ulangan'] }}" data-next-tugas="{{ $nextComponents['tugas'] }}">

    <!-- Header Info -->
    <header class="mb-6 rounded-2xl glass-card p-6 sm:p-8 animate-fade-in-up">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="inline-flex items-center gap-1 text-soft hover:text-warning-ink mb-3 transition-colors sm:hidden text-theme-sm font-medium">
                    &larr; Kembali
                </a>
                <br class="sm:hidden">
                <span class="inline-flex items-center rounded-full bg-brand-soft border border-brand-line px-2.5 py-0.5 text-brand-ink mb-3 text-theme-xs font-medium">
                    Tugas Pengisian Nilai
                </span>
                <h1 class="text-heading ui-page-title">{{ $assessmentSet->title }}</h1>
                <p class="mt-2 text-theme-sm font-normal text-muted">
                    {{ $assessmentSet->classSubject?->classroomTerm?->name }} &middot; {{ $assessmentSet->classSubject?->subject?->name }}
                </p>
                @if($assessmentSet->classSubject?->subject?->isSuluk())
                    <p class="mt-2 text-theme-sm text-muted">Suluk diisi wali kelas. KKM tetap 70 dari Sekolah/Kabag Diniyyah.</p>
                @else
                    <p class="mt-2 text-theme-sm text-muted">Nilai akhir = {{ $assessmentSet->daily_weight ?? 40 }}% rata-rata nilai harian + {{ $assessmentSet->exam_weight ?? 60 }}% nilai ujian. Keaktifan/presensi otomatis dari jurnal. Kolom opsional yang kosong tidak dihitung sebagai nol.</p>
                    @if($assessmentSet->tested_material && $assessmentSet->tested_material !== '-')<p class="mt-1 text-theme-sm text-muted">Materi ujian: {{ $assessmentSet->tested_material }}</p>@endif
                @endif
            </div>
            
            <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4 lg:min-w-[450px]">
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-theme-xs font-normal uppercase text-muted">KKM</p>
                    <p class="mt-1 font-semibold text-heading text-lg">{{ $assessmentSet->kkm ?? 'Menunggu ACC' }}</p>
                </div>
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-theme-xs font-normal uppercase text-muted">Terisi</p>
                    <p data-filled-cells class="mt-1 font-semibold text-heading text-lg">{{ $filledCells }}/{{ $totalCells }}</p>
                </div>
                <div class="rounded-2xl bg-surface/60 p-3 border border-line">
                    <p class="text-theme-xs font-normal uppercase text-muted">Lengkap</p>
                    <p data-complete-students class="mt-1 font-semibold text-heading text-lg">{{ $completeStudents }}/{{ $enrollments->count() }}</p>
                </div>
                <div class="rounded-2xl border border-warning-line bg-warning-soft/80 p-3">
                    <p class="text-theme-xs font-normal uppercase text-warning-ink">Progres</p>
                    <p data-completion-percentage class="mt-1 font-semibold text-warning-ink text-lg">{{ $completionPercentage }}%</p>
                </div>
            </div>
        </div>

        <!-- Validation/Submission Status Alert -->
        <div class="mt-6 flex flex-col gap-4 rounded-2xl bg-surface-subtle/80 border border-line/50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-theme-xs font-normal uppercase text-soft">Status Validasi</p>
                <p class="mt-1 text-theme-sm font-medium text-heading">{{ \App\Support\UiLabel::statusLabel($assessmentSet->status) }}</p>
            </div>
            @if ($canEdit)
                <form data-score-submit method="POST" action="{{ route('guru.diniyyah-scores.submit', $assessmentSet) }}" class="w-full sm:w-auto">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full justify-center rounded-xl px-5 py-2.5 text-white shadow-md transition-all {{ $completionPercentage >= 100 && $enrollments->count() > 0 ? 'bg-success-600 hover:bg-success-700 hover:shadow-success-500/20' : 'bg-line-strong' }} text-theme-sm font-medium"
                        @disabled($completionPercentage < 100 || $enrollments->count() === 0)
                    >
                        Submit ke Kabag Diniyyah
                    </button>
                </form>
            @else
                <span class="inline-flex items-center rounded-xl bg-surface px-4 py-2 text-theme-xs font-normal text-body border border-line">
                    Nilai sudah dikunci / divalidasi
                </span>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-success-line bg-success-soft p-4 text-theme-sm font-medium text-success-ink shadow-sm animate-fade-in-up">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-danger-line bg-danger-soft p-4 text-theme-sm font-medium text-danger-ink shadow-sm animate-fade-in-up">
            {{ $errors->first() }}
        </div>
    @endif

    <section data-kkm-warning role="status" aria-live="polite" class="mb-4 rounded-2xl border border-warning-line bg-warning-soft p-4 text-warning-ink" @if($kkmFeedback['below_kkm_students'] === 0) hidden @endif>
        <p class="font-medium text-theme-sm">Perhatian: <span data-below-kkm-count>{{ $kkmFeedback['below_kkm_students'] }}</span> santri memiliki nilai akhir di bawah KKM <span data-warning-kkm>{{ $kkmFeedback['kkm'] }}</span>.</p>
        <p class="mt-1 text-theme-sm">Nilai akhir perlu mencapai KKM. Periksa santri yang ditandai dan lakukan tindak lanjut. Nilai tetap tersimpan sesuai hasil perhitungan.</p>
    </section>

    <!-- Filter bar -->
    <div class="mb-6 rounded-2xl glass-card p-4 animate-fade-in-up" style="animation-delay:100ms;">
        <label for="student-filter" class="text-muted ui-form-label">Cari santri</label>
        <input
            id="student-filter"
            type="search"
            placeholder="Ketik nama atau NIS santri..."
            class="mt-2 w-full rounded-2xl border-2 border-line bg-surface/50 px-4 py-2.5 shadow-sm outline-none transition-all placeholder:text-soft focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10 text-theme-sm font-normal"
        >
    </div>

    @if($canEdit && in_array($assessmentSet->assessment_method, ['weighted', 'practical'], true) && ! $assessmentSet->classSubject?->subject?->isSuluk())
        <section class="mb-4 rounded-2xl glass-card p-4" aria-label="Tambah kolom nilai">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" data-add-component="ulangan" class="btn btn-outline min-h-11">+ Ulangan Harian</button>
                <button type="button" data-add-component="tugas" class="btn btn-outline min-h-11">+ Nilai Tugas</button>
            </div>
            <p class="mt-2 text-theme-xs text-muted">Kolom bawaan tersedia sejak awal: Ulangan Harian 1, Ulangan Harian 2, dan Nilai Tugas 1. Tombol di atas menambah Ulangan Harian 3 atau Nilai Tugas 2 dan seterusnya sesuai kebutuhan. Kolom tambahan opsional; yang kosong tidak masuk rata-rata.</p>
        </section>
    @endif

    <!-- Existing IDs and saved values are preserved when adding optional columns. -->
    <form data-score-form method="POST" action="{{ route('guru.diniyyah-scores.update', $assessmentSet) }}" class="rounded-2xl glass-card shadow-sm overflow-hidden animate-fade-in-up" style="animation-delay:150ms;">
        @csrf
        @method('PUT')

        <div class="overflow-visible md:overflow-x-auto">
            <table class="score-matrix w-full text-left text-theme-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-surface-subtle border-b border-line text-theme-xs font-normal uppercase text-muted">
                        <th class="md:sticky md:left-0 md:z-10 md:bg-surface-subtle px-6 py-4 text-theme-xs font-medium">Santri</th>
                        @foreach ($assessmentSet->components as $component)
                            <th data-component-id="{{ $component->id }}" class="px-6 py-4 text-theme-xs font-medium">{{ $component->inputLabel() }} @if($component->isDefaultInputColumn())<span class="normal-case text-muted">(bawaan)</span>@elseif(! $component->is_required)<span class="normal-case text-muted">(tambahan opsional)</span>@endif</th>
                        @endforeach
                        <th data-result-heading class="px-6 py-4 text-theme-xs font-medium">Nilai Akhir</th>
                    </tr>
                </thead>
                <tbody id="score-rows" class="divide-y divide-line">
                    @foreach ($enrollments as $enrollment)
                        @php
                            $result = $results->get($enrollment->id);
                            $studentComplete = (bool) $result?->is_complete;
                        @endphp
                        <tr class="hover:bg-surface-subtle/50 transition-colors" data-enrollment-id="{{ $enrollment->id }}" data-student-name="{{ $enrollment->student?->name }}" data-student="{{ Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                            <td data-label="Santri" class="md:sticky md:left-0 md:z-10 md:bg-surface px-6 py-4 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0">
                                        <div class="text-heading text-theme-sm font-medium truncate">{{ $enrollment->student?->name }}</div>
                                        <div class="text-theme-xs font-normal text-soft mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                    </div>
                                    <span data-row-complete class="rounded-full px-2 py-0.5 shrink-0 {{ $studentComplete ? 'bg-success-soft text-success-ink border border-success-line' : 'bg-warning-soft text-warning-ink border border-warning-line' }} text-theme-xs font-medium">
                                        {{ $studentComplete ? 'Lengkap' : 'Belum' }}
                                    </span>
                                </div>
                            </td>
                            @foreach ($assessmentSet->components as $component)
                                @php
                                    $score = $scores->get($enrollment->id.'-'.$component->id)?->score;
                                @endphp
                                <td data-component-id="{{ $component->id }}" data-label="{{ $component->inputLabel() }}" class="px-6 py-3">
                                    <input
                                        data-score data-saved="{{ $score }}" data-enrollment-id="{{ $enrollment->id }}" data-component-id="{{ $component->id }}"
                                        aria-label="{{ $enrollment->student?->name }} — {{ $component->inputLabel() }}"
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        name="scores[{{ $enrollment->id }}][{{ $component->id }}]"
                                        value="{{ old('scores.'.$enrollment->id.'.'.$component->id, $score) }}"
                                        placeholder="0.00"
                                        class="w-24 rounded-xl border-2 {{ $component->code === 'keaktifan_presensi' ? 'border-brand-line bg-brand-soft/50 text-brand-ink' : 'border-line bg-surface/50 text-heading' }} px-3 py-2 text-center shadow-sm outline-none transition-all focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10 text-theme-sm font-normal"
                                        @readonly($component->code === 'keaktifan_presensi')
                                        @disabled(! $canEdit)
                                    >
                                    @if($component->code === 'keaktifan_presensi')
                                        <div class="mt-1 text-theme-xs font-normal text-center text-brand-400 uppercase">Otomatis</div>
                                    @endif
                                </td>
                            @endforeach
                            <td data-row-result data-label="Nilai Akhir" class="px-6 py-3 font-medium text-heading text-theme-sm">
                                <div class="text-right md:text-left">
                                    <span data-row-final-score>{{ $result?->final_score ?? '-' }}</span>
                                    <span data-row-kkm-warning class="mt-1 block whitespace-normal text-theme-xs text-warning-ink" @if(! in_array($enrollment->id, $kkmFeedback['below_kkm_enrollment_ids'], true)) hidden @endif>Perlu tindak lanjut: di bawah KKM <span data-row-kkm>{{ $kkmFeedback['kkm'] }}</span></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer Action Block -->
        <div class="sticky bottom-0 flex flex-col items-stretch justify-between gap-3 border-t border-line/60 bg-surface/90 p-5 backdrop-blur-md sm:flex-row sm:items-center">
            <div>
                <p data-save-status role="status" aria-live="polite" class="text-theme-sm font-medium text-heading">{{ $canEdit ? 'Autosave aktif. Perubahan disimpan sebagai draf.' : 'Nilai hanya dapat dibaca.' }}</p>
                <p data-kkm-save-warning class="mt-1 text-theme-xs text-warning-ink" @if($kkmFeedback['below_kkm_students'] === 0) hidden @endif>Perhatian: ada nilai akhir di bawah KKM. Periksa santri yang ditandai.</p>
                <p data-save-error role="alert" class="mt-1 text-theme-xs text-danger-ink" hidden></p>
            </div>
            <button
                data-save-button
                class="rounded-xl px-5 py-3 text-white shadow-md transition-all {{ in_array($assessmentSet->status, ['active', 'needs_revision'], true) ? 'bg-warning-600 hover:bg-warning-700 hover:shadow-brand-500/20' : 'bg-gray-400' }} text-theme-sm font-medium"
                type="submit"
                @disabled(! $canEdit)
            >
                Simpan Perubahan
            </button>
        </div>
    </form>
    <template data-score-input-template><input data-score type="number" inputmode="decimal" step="0.01" min="0" max="100" placeholder="0.00" class="w-24 rounded-xl border-2 border-line bg-surface/50 text-heading px-3 py-2 text-center shadow-sm outline-none transition-all focus:border-brand-500 focus:bg-surface focus:ring-4 focus:ring-brand-500/10 text-theme-sm font-normal"></template>
    </div>
</x-layouts.portal>
