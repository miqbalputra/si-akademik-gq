<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $assessmentSet->title }} - SIAKAD Griya Qur'an</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fafafa; }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }
    </style>
    @include("partials.pwa-head")
</head>
<body class="min-h-screen text-slate-800 antialiased overflow-x-hidden selection:bg-amber-200 selection:text-amber-900">

    <!-- Background Elements -->
    <div class="fixed inset-0 z-[-1] pointer-events-none">
        <div class="absolute inset-0 bg-grid"></div>
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-amber-400/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-brand-500/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
    </div>

    <!-- Top Navigation -->
    <nav class="sticky top-0 z-50 glass-card border-b-0 border-white/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Kembali ke Daftar</span>
                    </div>
                </a>
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    Kembali
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        
        <!-- Header Info -->
        <header class="mb-6 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
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
    </main>

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
</body>
</html>
