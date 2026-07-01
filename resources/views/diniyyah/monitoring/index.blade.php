<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitoring Input Diniyyah - SIAKAD Griya Qur'an</title>

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
        .premium-card {
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .premium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.05);
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
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">PJ Diniyyah</span>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/admin') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                        Dashboard Utama
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        
        <!-- Header -->
        <header class="mb-8 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 mb-2">
                    Monitoring & Validasi
                </span>
                <h1 class="text-3xl font-black tracking-tight text-slate-900">Monitoring Input Nilai Diniyyah</h1>
                <p class="mt-2 text-sm text-slate-600 font-medium">Lacak keaktifan guru, kelas, mapel, dan lakukan verifikasi berkas nilai.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm animate-fade-in-up">
                {{ session('status') }}
            </div>
        @endif

        @php
            $totalSets = $summaries->count();
            $completeSets = $summaries->filter(fn ($summary) => $summary['progress_percentage'] >= 100)->count();
            $incompleteStudents = $summaries->sum('incomplete_students');
            $submittedSets = $summaries->where('status', 'submitted')->count();
        @endphp

        <!-- Metrics Grid -->
        <section class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4 animate-fade-in-up" style="animation-delay: 50ms;">
            <div class="rounded-3xl glass-card p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Set Ditampilkan</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $totalSets }}</p>
            </div>
            <div class="rounded-3xl border border-emerald-100 bg-emerald-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Set Lengkap</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ $completeSets }}</p>
            </div>
            <div class="rounded-3xl border border-red-100 bg-red-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-red-700">Santri Kurang Nilai</p>
                <p class="mt-1 text-2xl font-black text-red-900">{{ $incompleteStudents }}</p>
            </div>
            <div class="rounded-3xl border border-amber-100 bg-amber-50/80 backdrop-blur-md p-5 text-center transition-transform hover:scale-[1.02]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Menunggu Validasi</p>
                <p class="mt-1 text-2xl font-black text-amber-900">{{ $submittedSets }}</p>
            </div>
        </section>

        <!-- Filters Form -->
        <form method="GET" class="mb-8 rounded-3xl glass-card p-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 items-end animate-fade-in-up" style="animation-delay: 100ms;">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Kelas</label>
                <select name="classroom" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2.5 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                    <option value="">Semua kelas</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom }}" @selected(request('classroom') === $classroom)>{{ $classroom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Mapel</label>
                <select name="subject" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2.5 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                    <option value="">Semua mapel</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject }}" @selected(request('subject') === $subject)>{{ $subject }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                <select name="status" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2.5 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex h-11 items-center gap-2.5 rounded-xl bg-slate-50/80 px-4 border border-slate-200 cursor-pointer select-none">
                    <input type="checkbox" name="needs_attention" value="1" @checked(request()->boolean('needs_attention')) class="h-4 w-4 rounded border-slate-350 text-amber-600 focus:ring-amber-500">
                    <span class="text-xs font-bold text-slate-600">Perlu Perhatian</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button class="w-full rounded-xl bg-amber-600 py-3 text-xs font-bold text-white hover:bg-amber-700 transition-colors shadow-md" type="submit">Filter</button>
                <a href="{{ route('diniyyah.monitoring.index') }}" class="w-full text-center rounded-xl border-2 border-slate-200 bg-white py-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <!-- Monitoring Cards -->
        <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 animate-fade-in-up" style="animation-delay: 150ms;">
            @forelse ($summaries as $summary)
                @php
                    $assessmentSet = $summary['assessment_set'];
                    $progress = $summary['progress_percentage'];
                    $isComplete = $progress >= 100;
                    $needsAttention = ! $isComplete || in_array($summary['status'], ['draft', 'active', 'needs_revision', 'submitted'], true);
                @endphp
                <article class="premium-card rounded-3xl glass-card p-6 flex flex-col justify-between border-slate-100">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="min-w-0">
                                <h2 class="font-black text-slate-900 group-hover:text-amber-700 transition-colors leading-tight">{{ $assessmentSet->title }}</h2>
                                <p class="text-xs font-semibold text-slate-500 mt-1">{{ $summary['classroom_name'] }} &middot; {{ $summary['subject_name'] }}</p>
                                <p class="text-[10px] font-bold text-slate-400 mt-0.5 truncate">{{ implode(', ', $summary['teacher_names']) ?: 'Guru belum ditentukan' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider {{ $summary['status'] === 'validated' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $summary['status'] }}
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="mb-1 flex justify-between text-[10px] font-bold text-slate-500">
                                <span>{{ $summary['complete_students'] }} lengkap</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $isComplete ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <!-- Summary Grid -->
                        <dl class="grid grid-cols-3 gap-2 text-center text-xs mb-4">
                            <div class="rounded-xl bg-slate-50 p-2">
                                <dt class="text-[9px] font-bold uppercase text-slate-400">Siswa</dt>
                                <dd class="font-extrabold text-slate-800 mt-0.5">{{ $summary['total_students'] }}</dd>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-2 border border-emerald-100/50">
                                <dt class="text-[9px] font-bold uppercase text-emerald-600">Lengkap</dt>
                                <dd class="font-extrabold text-emerald-700 mt-0.5">{{ $summary['complete_students'] }}</dd>
                            </div>
                            <div class="rounded-xl bg-red-50 p-2 border border-red-100/50">
                                <dt class="text-[9px] font-bold uppercase text-red-600">Kurang</dt>
                                <dd class="font-extrabold text-red-700 mt-0.5">{{ $summary['incomplete_students'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100/80">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('guru.diniyyah-scores.edit', $assessmentSet) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                                Cek Nilai
                            </a>
                            @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
                                <a href="{{ url('/admin/diniyyah-assessment-sets/'.$assessmentSet->id.'/edit') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition-colors">
                                    Edit Set
                                </a>
                            @endif
                        </div>

                        <!-- Action validations -->
                        @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) && $summary['status'] === 'submitted')
                            <div class="mt-4 grid gap-3 border-t border-slate-200/40 pt-4">
                                <form method="POST" action="{{ route('diniyyah.assessment-sets.approve', $assessmentSet) }}" class="grid gap-2">
                                    @csrf
                                    <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 bg-white/50 px-3 py-2 text-xs font-medium outline-none focus:border-brand-500 focus:bg-white" placeholder="Catatan persetujuan..."></textarea>
                                    <button type="submit" class="rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-all">
                                        Validasi (Approve)
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('diniyyah.assessment-sets.revision', $assessmentSet) }}" class="grid gap-2">
                                    @csrf
                                    <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 bg-white/50 px-3 py-2 text-xs font-medium outline-none focus:border-brand-500 focus:bg-white" placeholder="Catatan perbaikan..."></textarea>
                                    <button type="submit" class="rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-all">
                                        Kembalikan Untuk Revisi
                                    </button>
                                </form>
                            </div>
                        @elseif (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) && $summary['status'] === 'validated')
                            <form method="POST" action="{{ route('diniyyah.assessment-sets.revision', $assessmentSet) }}" class="mt-4 grid gap-2 border-t border-slate-200/40 pt-4">
                                @csrf
                                <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 bg-white/50 px-3 py-2 text-xs font-medium outline-none focus:border-brand-500 focus:bg-white" placeholder="Alasan pembukaan revisi..."></textarea>
                                <button type="submit" class="rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-all">
                                    Buka Ulang Untuk Revisi
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-10 text-center text-slate-500 md:col-span-2 lg:col-span-3">
                    <p class="text-sm font-bold">Belum ada data tugas penilaian yang sesuai filter.</p>
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
