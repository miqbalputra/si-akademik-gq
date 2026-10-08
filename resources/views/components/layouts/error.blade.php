@props(['code', 'title'])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Ruang GQ</title>
    @include('partials.theme-init')
    @if(is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @include('partials.theme-palette')
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--ui-canvas, #f9fafb); color: var(--ui-text, #344054); font: 16px/24px Outfit, sans-serif; }
        .error-card { width: min(90vw, 34rem); padding: 2rem; border: 1px solid var(--ui-line, #e4e7ec); border-radius: 1rem; background: var(--ui-surface, #fff); text-align: center; }
        .error-code { margin: 0; color: var(--color-brand-500); font-size: 72px; font-weight: 700; line-height: 90px; }
        .error-card h1 { color: var(--ui-heading, #101828); font-size: 20px; line-height: 28px; font-weight: 600; }
        .error-card a { display: inline-flex; margin-top: 1rem; border-radius: .625rem; padding: .7rem 1rem; background: var(--color-brand-500); color: var(--color-neon-ink); text-decoration: none; font-size: 14px; line-height: 20px; font-weight: 500; }
    </style>
</head>
<body>
    <main class="error-card">
        <p class="error-code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $slot }}</p>
        <a href="{{ url('/') }}">Kembali ke halaman utama</a>
    </main>
</body>
</html>
