<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pilih Ruang Kerja - Ruang GQ</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="school-home text-ink">
    <main class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-10 sm:px-7">
        <section class="w-full rounded-[2rem] border border-line bg-white p-6 shadow-xl shadow-slate-950/10 sm:p-10">
            <a href="{{ url('/') }}" class="school-brand w-fit"><span class="school-mark">GQ</span><span><strong>Ruang GQ</strong><small>AKTIVITAS AKADEMIK</small></span></a>
            <p class="school-index mt-12">PILIH RUANG KERJA</p>
            <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Assalamu'alaikum, {{ auth()->user()->name }}.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Akun Anda memiliki lebih dari satu peran. Pilih kegiatan yang ingin dibuka sekarang.</p>

            @error('workspace')
                <div class="inline-feedback inline-feedback-error mt-6" role="alert">Pilihan ruang kerja tidak tersedia untuk akun Anda.</div>
            @enderror

            <form method="POST" action="{{ route('workspace.select') }}" class="mt-8 grid gap-4 md:grid-cols-2">
                @csrf
                @foreach($workspaces as $key => $workspace)
                    <button type="submit" name="workspace" value="{{ $key }}" class="group rounded-2xl border border-slate-200 bg-slate-50 p-6 text-left transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-lg font-black text-emerald-800" aria-hidden="true">→</span>
                        <span class="mt-5 block text-lg font-black text-slate-950">{{ $workspace['label'] }}</span>
                        <span class="mt-2 block text-sm leading-6 text-slate-600">{{ $workspace['description'] }}</span>
                        <span class="mt-5 block text-xs font-black uppercase tracking-[.12em] text-emerald-800">Buka ruang ini</span>
                    </button>
                @endforeach
            </form>
        </section>
    </main>
</body>
</html>
