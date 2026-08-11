@php
    $dashboardUrl = auth()->check()
        ? match (true) {
            auth()->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kabag_tahfidz', 'kepala_sekolah']) => url('/admin'),
            auth()->user()->hasRole('guru') => route('guru.dashboard'),
            auth()->user()->hasRole('wali_santri') => route('wali.dashboard'),
            default => url('/admin'),
        }
        : route('login');
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SIAKAD — Griya Qur'an Tunas Ilmu</title>
    <meta name="description" content="Sistem Informasi Akademik Griya Qur'an. Portal rapor digital, pantauan tahfidz, dan aktivitas sekolah.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="min-h-screen overflow-x-hidden bg-canvas font-sans text-slate-800 antialiased">
    <div class="fixed inset-0 -z-10 bg-grid opacity-70"></div>

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-56 flex-col border-r border-slate-200 bg-white/90 px-4 py-6 backdrop-blur-xl lg:flex">
        <a href="{{ url('/') }}" class="flex items-center gap-3 px-2 text-ink">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-700 text-sm font-black text-white shadow-lg shadow-amber-500/20">GQ</span>
            <span>
                <strong class="block text-sm font-black leading-tight">Griya Qur'an</strong>
                <span class="mt-0.5 block text-[9px] font-black uppercase tracking-[.18em] text-amber-600">Product line</span>
            </span>
        </a>

        <p class="mt-10 px-2 text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Portal akademik</p>
        <nav class="mt-3 space-y-1" aria-label="Portal akademik">
            <a href="#portal" class="flex min-h-11 items-center gap-3 rounded-xl bg-amber-50 px-3 text-xs font-black text-amber-800">
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                Product line
            </a>
            <a href="#cara-kerja" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-xs font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                Cara kerja
            </a>
            <a href="#fitur" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-xs font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                Fitur utama
            </a>
        </nav>

        <div class="mt-auto rounded-2xl bg-slate-950 p-4 text-white shadow-xl shadow-slate-900/10">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-300">SIAKAD GQ</p>
            <p class="mt-2 text-xs font-medium leading-5 text-slate-300">Satu tempat untuk mengawal nilai, hafalan, dan agenda santri.</p>
        </div>
    </aside>

    <div class="lg:pl-56">
        <nav class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl" aria-label="Navigasi utama">
            <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                <a href="{{ url('/') }}" class="flex items-center gap-3 lg:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-700 text-xs font-black text-white shadow-md">GQ</span>
                    <span>
                        <strong class="block text-sm font-black leading-tight text-ink">Griya Qur'an</strong>
                        <span class="block text-[9px] font-black uppercase tracking-[.14em] text-amber-600">SIAKAD</span>
                    </span>
                </a>
                <span class="hidden text-xs font-bold text-slate-500 lg:block">Sistem Informasi Akademik Terpadu</span>

                <div class="flex items-center gap-2 sm:gap-3">
                    @auth
                        <a href="{{ $dashboardUrl }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-xs font-black text-white shadow-lg shadow-slate-900/10 transition hover:-translate-y-0.5 hover:bg-slate-800">
                            Dashboard
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="hidden min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-50 sm:inline-flex">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('auth.google') }}" class="hidden min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 sm:inline-flex">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>
                            Masuk Google
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-xs font-black text-white shadow-lg shadow-slate-900/10 transition hover:-translate-y-0.5 hover:bg-slate-800">Masuk Portal <span aria-hidden="true">→</span></a>
                    @endauth
                </div>
            </div>
        </nav>

        <main>
            <section class="mx-auto grid max-w-7xl gap-10 px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:px-10 lg:pb-28 lg:pt-24">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-amber-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Sistem aktif untuk sekolah
                    </span>
                    <h1 class="mt-6 text-5xl font-black leading-[.98] tracking-[-.045em] text-ink sm:text-6xl lg:text-7xl">
                        Semua progres santri,
                        <span class="relative mt-2 inline-block text-amber-600">
                            satu alur kerja.
                            <span class="absolute -bottom-1 left-0 h-2 w-full -rotate-1 rounded-full bg-amber-200/80" aria-hidden="true"></span>
                        </span>
                    </h1>
                    <p class="mt-7 max-w-xl text-base font-medium leading-7 text-slate-500 sm:text-lg">
                        Portal akademik yang menghubungkan wali santri, guru, dan manajemen untuk memantau rapor, presensi, jurnal, serta hafalan secara lebih sederhana.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-black text-white shadow-xl shadow-slate-900/15 transition hover:-translate-y-0.5 hover:bg-slate-800">Masuk ke Portal <span aria-hidden="true">→</span></a>
                        <a href="#portal" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">Lihat modul</a>
                    </div>
                    <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs font-bold text-slate-400">
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Rapor digital</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Tahfidz terpantau</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Agenda sekolah</span>
                    </div>
                </div>

                <div class="relative min-h-[420px] lg:min-h-[540px]" aria-label="Ilustrasi alur aplikasi">
                    <div class="absolute inset-x-5 top-8 rounded-[28px] border border-slate-200 bg-white/75 p-4 shadow-2xl shadow-slate-900/10 backdrop-blur sm:inset-x-12 sm:top-12 sm:p-6">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Ringkasan hari ini</p><p class="mt-1 text-sm font-black text-ink">Portal akademik</p></div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700">Live</span>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-950 p-4 text-white"><p class="text-[10px] font-bold text-slate-400">Santri aktif</p><p class="mt-2 text-2xl font-black">248</p></div>
                            <div class="rounded-2xl bg-amber-50 p-4 text-amber-950"><p class="text-[10px] font-bold text-amber-700">Rapor terbit</p><p class="mt-2 text-2xl font-black">92%</p></div>
                            <div class="col-span-2 rounded-2xl bg-emerald-50 p-4 text-emerald-950 sm:col-span-1"><p class="text-[10px] font-bold text-emerald-700">Presensi</p><p class="mt-2 text-2xl font-black">98%</p></div>
                        </div>
                        <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-center justify-between"><p class="text-xs font-black text-ink">Progres hafalan</p><span class="text-[10px] font-bold text-slate-400">Minggu ini</span></div>
                            <div class="mt-4 space-y-3"><div><div class="mb-1 flex justify-between text-[10px] font-bold text-slate-500"><span>Halaqah A</span><span>82%</span></div><div class="h-2 rounded-full bg-slate-200"><div class="h-2 w-[82%] rounded-full bg-amber-500"></div></div></div><div><div class="mb-1 flex justify-between text-[10px] font-bold text-slate-500"><span>Halaqah B</span><span>68%</span></div><div class="h-2 rounded-full bg-slate-200"><div class="h-2 w-[68%] rounded-full bg-emerald-500"></div></div></div></div>
                        </div>
                    </div>
                    <div class="absolute -bottom-2 left-0 w-56 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 sm:left-4 sm:w-64">
                        <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">↗</span><div><p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">Aksi cepat</p><p class="mt-1 text-xs font-black text-ink">Input jurnal hari ini</p></div></div>
                    </div>
                    <div class="absolute -right-1 bottom-16 hidden w-48 rounded-2xl bg-amber-500 p-4 text-amber-950 shadow-2xl shadow-amber-500/25 sm:block lg:right-0">
                        <p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-900/70">Prioritas</p><p class="mt-2 text-sm font-black leading-5">3 tugas perlu diselesaikan hari ini.</p>
                    </div>
                </div>
            </section>

            <section id="portal" class="border-y border-slate-200/80 bg-white/70 py-20 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
                    <div class="max-w-2xl"><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-600">Product line</p><h2 class="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Masuk dari peran Anda.</h2><p class="mt-3 text-sm font-medium leading-6 text-slate-500">Setiap portal dirancang untuk pekerjaan yang berbeda, tetapi memakai data akademik yang sama.</p></div>
                    <div class="mt-10 grid gap-4 md:grid-cols-3">
                        <a href="{{ route('wali.dashboard') }}" class="group rounded-[22px] border border-emerald-100 bg-emerald-50/70 p-6 transition hover:-translate-y-1 hover:border-emerald-200 hover:bg-white hover:shadow-xl hover:shadow-emerald-900/10"><div class="flex items-center justify-between"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500 text-xl text-white shadow-lg shadow-emerald-500/20">⌂</span><span class="text-xl text-emerald-500 transition group-hover:translate-x-1">→</span></div><p class="mt-8 text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">Portal keluarga</p><h3 class="mt-2 text-xl font-black text-ink">Wali Santri</h3><p class="mt-2 text-sm font-medium leading-6 text-slate-500">Pantau rapor, hafalan, presensi, dan agenda sekolah anak.</p></a>
                        <a href="{{ route('guru.diniyyah-scores.index') }}" class="group rounded-[22px] border border-indigo-100 bg-indigo-50/70 p-6 transition hover:-translate-y-1 hover:border-indigo-200 hover:bg-white hover:shadow-xl hover:shadow-indigo-900/10"><div class="flex items-center justify-between"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500 text-xl text-white shadow-lg shadow-indigo-500/20">▣</span><span class="text-xl text-indigo-500 transition group-hover:translate-x-1">→</span></div><p class="mt-8 text-[10px] font-black uppercase tracking-[.14em] text-indigo-700">Portal pengajar</p><h3 class="mt-2 text-xl font-black text-ink">Guru Pengajar</h3><p class="mt-2 text-sm font-medium leading-6 text-slate-500">Input nilai, presensi, jurnal, dan setoran hafalan lebih cepat.</p></a>
                        <a href="{{ url('/admin') }}" class="group rounded-[22px] border border-slate-200 bg-slate-950 p-6 text-white transition hover:-translate-y-1 hover:bg-slate-900 hover:shadow-xl hover:shadow-slate-900/20"><div class="flex items-center justify-between"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-xl text-amber-300">⌘</span><span class="text-xl text-amber-300 transition group-hover:translate-x-1">→</span></div><p class="mt-8 text-[10px] font-black uppercase tracking-[.14em] text-amber-300">Pusat kendali</p><h3 class="mt-2 text-xl font-black">Manajemen</h3><p class="mt-2 text-sm font-medium leading-6 text-slate-300">Kelola data sekolah, validasi rapor, dan monitor aktivitas.</p></a>
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-start"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-600">Cara kerja</p><h2 class="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Dari input harian menjadi gambaran utuh.</h2></div><div class="grid gap-4 sm:grid-cols-3"><div class="rounded-2xl border border-slate-200 bg-white p-5"><span class="text-xs font-black text-amber-600">01</span><h3 class="mt-10 text-base font-black text-ink">Catat</h3><p class="mt-2 text-sm leading-6 text-slate-500">Guru mengisi nilai, presensi, jurnal, dan hafalan dari satu portal.</p></div><div class="rounded-2xl border border-slate-200 bg-white p-5"><span class="text-xs font-black text-amber-600">02</span><h3 class="mt-10 text-base font-black text-ink">Validasi</h3><p class="mt-2 text-sm leading-6 text-slate-500">Manajemen memantau kelengkapan dan memvalidasi alur akademik.</p></div><div class="rounded-2xl border border-slate-200 bg-white p-5"><span class="text-xs font-black text-amber-600">03</span><h3 class="mt-10 text-base font-black text-ink">Pantau</h3><p class="mt-2 text-sm leading-6 text-slate-500">Wali melihat perkembangan anak dengan bahasa yang mudah dipahami.</p></div></div></div>
            </section>

            <section id="fitur" class="bg-slate-950 py-20 text-white sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-300">Dibangun untuk aktivitas sekolah</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Kerja yang penting tetap terlihat.</h2></div><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-500 px-4 text-xs font-black text-amber-950 transition hover:bg-amber-400">Mulai sekarang →</a></div><div class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-2xl">◌</p><p class="mt-8 text-sm font-black">Presensi harian</p><p class="mt-2 text-xs leading-5 text-slate-400">Input cepat dengan status yang jelas.</p></div><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-2xl">▤</p><p class="mt-8 text-sm font-black">Rapor digital</p><p class="mt-2 text-xs leading-5 text-slate-400">Hasil belajar siap dibaca dan diunduh.</p></div><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-2xl">⌁</p><p class="mt-8 text-sm font-black">Tahfidz</p><p class="mt-2 text-xs leading-5 text-slate-400">Progres hafalan terlihat dari waktu ke waktu.</p></div><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-2xl">◫</p><p class="mt-8 text-sm font-black">Agenda sekolah</p><p class="mt-2 text-xs leading-5 text-slate-400">Semua peran mendapat konteks yang sama.</p></div></div></div></section>
        </main>

        <footer class="border-t border-slate-200 bg-white px-4 py-7 text-center text-xs font-medium text-slate-400 sm:px-6 lg:px-10">&copy; {{ date('Y') }} Griya Qur'an Tunas Ilmu. Hak Cipta Dilindungi.</footer>
    </div>
</body>
</html>
