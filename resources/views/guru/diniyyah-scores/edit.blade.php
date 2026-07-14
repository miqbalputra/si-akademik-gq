<x-layouts.portal title="{{ $assessmentSet->title }}" portalLabel="Portal Guru" breadcrumb="Input Nilai / {{ $assessmentSet->classSubject?->classroomTerm?->name }}">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-scores.index') }}" class="btn btn-outline btn-sm hidden sm:inline-flex">Kembali ke Daftar</a>
    </x-slot>

    <!-- Header Info -->
    <header class="mb-6 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-slate-400 hover:text-amber-600 mb-3 transition-colors sm:hidden">
                    &larr; Kembali
                </a>
                <br class="sm:hidden">
                <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 text-xs font-bold text-indigo-700 mb-3">
                    Tugas Pengisian Nilai
                </span>
                <h1 class="text-2xl font-black text-slate-900 leading-tight">{{ $assessmentSet->title }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-500">
                    {{ $assessmentSet->classSubject?->classroomTerm?->name }} &middot; {{ $assessmentSet->classSubject?->subject?->name }}
                </p>
            </div>
            
            <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4 lg:min-w-[450px]">
                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">KKM</p>
                    <p class="mt-1 font-black text-slate-900 text-lg">{{ $assessmentSet->kkm ?? '-' }}</p>
                </div>
                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Terisi</p>
                    <p class="mt-1 font-black text-slate-900 text-lg">{{ $filledCells }}/{{ $totalCells }}</p>
                </div>
                <div class="rounded-2xl bg-white/60 p-3 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Lengkap</p>
                    <p class="mt-1 font-black text-slate-900 text-lg">{{ $completeStudents }}/{{ $enrollments->count() }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/80 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Progres</p>
                    <p class="mt-1 font-black text-amber-900 text-lg">{{ $completionPercentage }}%</p>
                </div>
            </div>
        </div>

        <!-- Validation/Submission Status Alert -->
        <div class="mt-6 flex flex-col gap-4 rounded-2xl bg-slate-50/80 border border-slate-200/50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status Validasi</p>
                <p class="mt-1 text-sm font-black text-slate-800 uppercase tracking-wide">{{ $assessmentSet->status }}</p>
            </div>
            @if (in_array($assessmentSet->status, ['active', 'needs_revision'], true))
                <form method="POST" action="{{ route('guru.diniyyah-scores.submit', $assessmentSet) }}" class="w-full sm:w-auto">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full justify-center rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-md transition-all {{ $completionPercentage >= 100 && $enrollments->count() > 0 ? 'bg-emerald-600 hover:bg-emerald-700 hover:shadow-emerald-500/20' : 'bg-slate-300' }}"
                        @disabled($completionPercentage < 100 || $enrollments->count() === 0)
                    >
                        Submit ke Kabag Diniyyah
                    </button>
                </form>
            @else
                <span class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-xs font-bold text-slate-600 border border-slate-200">
                    Nilai sudah dikunci / divalidasi
                </span>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm animate-fade-in-up">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 shadow-sm animate-fade-in-up">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Filter bar -->
    <div class="mb-6 rounded-3xl glass-card p-4 animate-fade-in-up" style="animation-delay: 100ms;">
        <label for="student-filter" class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Cari santri</label>
        <input
            id="student-filter"
            type="search"
            placeholder="Ketik nama atau NIS santri..."
            class="mt-2 w-full rounded-2xl border-2 border-slate-100 bg-white/50 px-4 py-2.5 text-sm font-semibold shadow-sm outline-none transition-all placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10"
        >
    </div>

    <!-- Form Table -->
    <form method="POST" action="{{ route('guru.diniyyah-scores.update', $assessmentSet) }}" class="rounded-[2rem] glass-card shadow-sm overflow-hidden animate-fade-in-up" style="animation-delay: 150ms;">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="sticky left-0 z-10 bg-slate-50 px-6 py-4">Santri</th>
                        @foreach ($assessmentSet->components as $component)
                            <th class="px-6 py-4">{{ $component->name }}</th>
                        @endforeach
                        <th class="px-6 py-4">Nilai Akhir</th>
                    </tr>
                </thead>
                <tbody id="score-rows" class="divide-y divide-slate-100">
                    @foreach ($enrollments as $enrollment)
                        @php
                            $result = $results->get($enrollment->id);
                            $studentComplete = (bool) $result?->is_complete;
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors" data-student="{{ Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                            <td class="sticky left-0 z-10 bg-white px-6 py-4 font-bold">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <div class="text-slate-900 text-sm font-extrabold">{{ $enrollment->student?->name }}</div>
                                        <div class="text-xs font-semibold text-slate-400 mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $studentComplete ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $studentComplete ? 'Lengkap' : 'Belum' }}
                                    </span>
                                </div>
                            </td>
                            @foreach ($assessmentSet->components as $component)
                                @php($score = $scores->get($enrollment->id.'-'.$component->id)?->score)
                                <td class="px-6 py-3">
                                    <input
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        name="scores[{{ $enrollment->id }}][{{ $component->id }}]"
                                        value="{{ old('scores.'.$enrollment->id.'.'.$component->id, $score) }}"
                                        placeholder="0.00"
                                        class="w-24 rounded-xl border-2 {{ $component->code === 'keaktifan_presensi' ? 'border-indigo-100 bg-indigo-50/50 text-indigo-800' : 'border-slate-100 bg-white/50 text-slate-900' }} px-3 py-2 text-center text-sm font-extrabold shadow-sm outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10"
                                        @readonly($component->code === 'keaktifan_presensi')
                                        @disabled(! in_array($assessmentSet->status, ['active', 'needs_revision'], true) && ! auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
                                    >
                                    @if($component->code === 'keaktifan_presensi')
                                        <div class="mt-1 text-[8px] font-bold text-center text-indigo-400 uppercase tracking-wide">Otomatis</div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-6 py-3 font-black text-slate-800 text-base">
                                {{ $result?->final_score ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer Action Block -->
        <div class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-slate-200/60 bg-white/90 p-5 backdrop-blur-md">
            <p class="text-xs font-semibold text-slate-500">Simpan perubahan Anda sebagai draf kapan saja untuk dihitung otomatis.</p>
            <button
                class="rounded-xl px-5 py-3 text-xs font-bold text-white shadow-md transition-all {{ in_array($assessmentSet->status, ['active', 'needs_revision'], true) || auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) ? 'bg-amber-600 hover:bg-amber-700 hover:shadow-brand-500/20' : 'bg-slate-400' }}"
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
