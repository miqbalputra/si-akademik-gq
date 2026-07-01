<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - SIAKAD Griya Qur'an</title>

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
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ $backUrl }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Kalender Akademik</span>
                    </div>
                </a>
                <a href="{{ $backUrl }}" class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    Kembali
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        
        <!-- Header -->
        <header class="mb-6 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 mb-2">
                Portal Guru Pengajar
            </span>
            <h1 class="text-3xl font-black text-slate-900 leading-tight">{{ $title }}</h1>
            <p class="mt-2 text-sm font-semibold text-slate-500">{{ $subtitle }}</p>
        </header>

        <!-- Filters Form -->
        <section class="mb-6 rounded-3xl glass-card p-6 animate-fade-in-up" style="animation-delay: 50ms;">
            <form method="GET" class="grid gap-4 sm:grid-cols-3 items-end">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Periode Akademik</label>
                    <select name="term" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2.5 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($termOptions as $term)
                            <option value="{{ $term['id'] }}" @selected($selectedAcademicTermId === $term['id'])>{{ $term['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Bulan</label>
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Kategori</label>
                    <select name="category" class="w-full rounded-xl border-2 border-slate-100 bg-white/50 px-3 py-2.5 text-sm font-semibold outline-none transition-all focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category['value'] }}" @selected($selectedCategory === $category['value'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-amber-600 py-3 text-xs font-bold text-white hover:bg-amber-700 transition-colors shadow-md sm:col-span-3">
                    Tampilkan Kalender
                </button>
            </form>
            <div class="mt-4 pt-4 border-t border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400 flex flex-wrap gap-2">
                <span>{{ $selectedTermLabel }}</span>
                <span>&middot;</span>
                <span>{{ $selectedMonthLabel }}</span>
                <span>&middot;</span>
                <span>Filter {{ collect($categoryOptions)->firstWhere('value', $selectedCategory)['label'] ?? 'Semua' }}</span>
            </div>
        </section>

        @include('calendar._calendar')
    </main>
</body>
</html>
