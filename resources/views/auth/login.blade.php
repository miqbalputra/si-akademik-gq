<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - Ruang GQ</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="school-home text-ink">
    <main class="mx-auto grid min-h-screen max-w-7xl items-stretch lg:grid-cols-[1.05fr_.95fr]">
        <section class="hidden border-r border-line p-10 lg:flex lg:flex-col">
            <a href="{{ url('/') }}" class="school-brand w-fit">
                <span class="school-mark">GQ</span>
                <span><strong>Ruang GQ</strong><small>AKTIVITAS AKADEMIK</small></span>
            </a>

            <div class="my-auto max-w-xl">
                <p class="school-index">PINTU MASUK RUANG GQ</p>
                <h1 class="mt-5 text-6xl font-medium leading-[.94] tracking-[-.065em]">Masuk ke <span class="rounded bg-neon px-2 text-neon-ink">ruang</span> belajar Anda.</h1>
                <p class="mt-7 max-w-md text-base leading-7 text-slate-600">Setiap akun membuka kegiatan sekolah yang sesuai: mengajar, mendampingi perkembangan anak, atau mengelola akademik.</p>
            </div>

            <div class="school-board max-w-xl">
                <div class="school-board-head"><h2>Akses menurut peran</h2><span>TERJAGA</span></div>
                <div class="school-board-list">
                    <div class="school-board-row"><time>01</time><span><strong>Ruang Guru</strong><small>Catatan kelas, jurnal, dan penilaian.</small></span><span class="school-board-status">AKSES</span></div>
                    <div class="school-board-row"><time>02</time><span><strong>Ruang Wali</strong><small>Perkembangan anak dan arsip rapor.</small></span><span class="school-board-status">AKSES</span></div>
                    <div class="school-board-row"><time>03</time><span><strong>Kendali Akademik</strong><small>Validasi, kalender, dan arsip sekolah.</small></span><span class="school-board-status">AKSES</span></div>
                </div>
            </div>
        </section>

        <section class="flex items-center justify-center px-4 py-10 sm:px-7">
            <div class="w-full max-w-md">
                <a href="{{ url('/') }}" class="mb-10 flex lg:hidden"><span class="school-brand"><span class="school-mark !h-8 !w-8 !text-[10px]">GQ</span><span><strong>Ruang GQ</strong><small>Griya Qur'an Tunas Ilmu</small></span></span></a>
                <p class="school-index">AKSES AKUN</p>
                <h2 class="mt-4 text-4xl font-medium tracking-[-.06em]">Pintu Masuk Ruang GQ</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Gunakan email atau username yang diberikan oleh admin sekolah.</p>

                @if ($errors->any())
                    <div class="inline-feedback inline-feedback-error mt-6" role="alert">Periksa kembali data masuk Anda.</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="login" class="mb-2 block font-mono text-[10px] font-bold uppercase tracking-[.1em] text-slate-600">Email atau username</label>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="nama@domain.com atau username" class="form-input @error('login') border-red-400 @enderror">
                        @error('login')<p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="mb-2 block font-mono text-[10px] font-bold uppercase tracking-[.1em] text-slate-600">Kata sandi</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" class="form-input @error('password') border-red-400 @enderror">
                        @error('password')<p class="mt-2 text-xs font-bold text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 accent-brand-600"> Ingat saya di perangkat ini
                    </label>
                    <button type="submit" class="btn btn-primary btn-lg w-full">Masuk ke ruang saya <span aria-hidden="true">&rarr;</span></button>
                </form>

                <div class="my-7 flex items-center gap-3"><span class="h-px flex-1 bg-line"></span><span class="font-mono text-[10px] font-bold text-slate-500">ATAU</span><span class="h-px flex-1 bg-line"></span></div>
                <a href="{{ route('auth.google') }}" class="btn btn-outline btn-lg w-full">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>
                    Masuk dengan Google
                </a>
                <p class="mt-8 text-center font-mono text-[10px] font-bold tracking-[.08em] text-slate-500">&copy; {{ date('Y') }} GRIYA QUR'AN TUNAS ILMU</p>
            </div>
        </section>
    </main>
</body>
</html>
