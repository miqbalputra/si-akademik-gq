<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-init')
    <title>Atur Ulang Kata Sandi - Ruang GQ</title>
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
            <h1 class="mt-3 ui-auth-title">Buat kata sandi baru</h1>
            <p class="mt-3 text-theme-sm text-body">Gunakan sedikitnya 12 karakter.</p>
            @if ($errors->any())<p class="mt-5 rounded-lg bg-danger-soft p-3 text-theme-sm text-danger-ink" role="alert">{{ $errors->first() }}</p>@endif
            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div><label class="mb-2 block ui-form-label" for="email">Email akun</label><input class="form-input text-theme-sm font-normal" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
                <div><label class="mb-2 block ui-form-label" for="password">Kata sandi baru</label><input class="form-input text-theme-sm font-normal" id="password" name="password" type="password" required minlength="12" autocomplete="new-password"></div>
                <div><label class="mb-2 block ui-form-label" for="password_confirmation">Ulangi kata sandi baru</label><input class="form-input text-theme-sm font-normal" id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div>
                <button class="btn btn-primary btn-lg w-full text-theme-sm font-medium" type="submit">Simpan kata sandi baru</button>
            </form>
        </section>
    </main>
</body>
</html>
