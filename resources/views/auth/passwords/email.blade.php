<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-init')
    <title>Lupa Kata Sandi - Ruang GQ</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-palette')
    @include('partials.pwa-head')
</head>
<body class="school-home text-ink">
    <div class="fixed end-4 top-4 z-30"><x-common.theme-toggle /></div>
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-12">
        <section class="w-full rounded-2xl border border-line bg-surface p-6 shadow-sm sm:p-10">
            <a href="{{ route('login') }}" class="school-brand"><span class="school-mark">GQ</span><span><strong>Ruang GQ</strong><small>AKTIVITAS AKADEMIK</small></span></a>
            <p class="school-index mt-10">PEMULIHAN AKUN</p>
            <h1 class="mt-3 ui-auth-title">Lupa kata sandi?</h1>
            <p class="mt-3 text-theme-sm text-body">Masukkan email akun Anda. Jika terdaftar, kami akan mengirim tautan reset yang berlaku selama 60 menit.</p>
            @if (session('status'))<p class="mt-5 rounded-lg bg-success-soft p-3 text-theme-sm text-success-ink" role="status">{{ session('status') }}</p>@endif
            @error('email')<p class="mt-5 rounded-lg bg-danger-soft p-3 text-theme-sm text-danger-ink" role="alert">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block ui-form-label" for="email">Email akun</label>
                <input class="form-input text-theme-sm font-normal" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                <button class="btn btn-primary btn-lg w-full text-theme-sm font-medium" type="submit">Kirim tautan reset</button>
            </form>
            <a class="mt-6 inline-flex text-school-700 underline text-theme-sm font-medium" href="{{ route('login') }}">Kembali ke halaman masuk</a>
        </section>
    </main>
</body>
</html>
