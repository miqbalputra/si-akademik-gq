<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lupa Kata Sandi - Ruang GQ</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="school-home text-ink">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-12">
        <section class="w-full rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-10">
            <a href="{{ route('login') }}" class="school-brand"><span class="school-mark">GQ</span><span><strong>Ruang GQ</strong><small>AKTIVITAS AKADEMIK</small></span></a>
            <p class="school-index mt-10">PEMULIHAN AKUN</p>
            <h1 class="mt-3 text-3xl font-semibold">Lupa kata sandi?</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Masukkan email akun Anda. Jika terdaftar, kami akan mengirim tautan reset yang berlaku selama 60 menit.</p>
            @if (session('status'))<p class="mt-5 rounded-lg bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
            @error('email')<p class="mt-5 rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block text-sm font-medium" for="email">Email akun</label>
                <input class="form-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                <button class="btn btn-primary btn-lg w-full" type="submit">Kirim tautan reset</button>
            </form>
            <a class="mt-6 inline-flex text-sm font-semibold text-school-700 underline" href="{{ route('login') }}">Kembali ke halaman masuk</a>
        </section>
    </main>
</body>
</html>
