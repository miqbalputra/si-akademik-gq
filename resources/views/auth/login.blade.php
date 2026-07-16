<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - SIAKAD Griya Qur'an Tunas Ilmu</title>

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
                        },
                        colors: {
                            brand: {
                                50: '#fffbeb',
                                100: '#fef3c7',
                                200: '#fde68a',
                                300: '#fcd34d',
                                400: '#fbbf24',
                                500: '#f59e0b',
                                600: '#d97706',
                                700: '#b45309',
                                800: '#92400e',
                                900: '#78350f',
                            }
                        },
                        animation: {
                            'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                            'float': 'float 6s ease-in-out infinite',
                        },
                        keyframes: {
                            fadeInUp: {
                                '0%': { opacity: '0', transform: 'translateY(20px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' },
                            },
                            float: {
                                '0%, 100%': { transform: 'translateY(0)' },
                                '50%': { transform: 'translateY(-10px)' },
                            }
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #fafafa;
        }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
            mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
            -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        }
    </style>
    @include("partials.pwa-head")
</head>
<body class="min-h-screen text-slate-800 antialiased overflow-x-hidden selection:bg-brand-200 selection:text-brand-900 flex flex-col">

    <!-- Absolute Background Elements -->
    <div class="fixed inset-0 z-[-1] pointer-events-none">
        <div class="absolute inset-0 bg-grid"></div>
        <div class="absolute -top-[20%] -left-[10%] w-[600px] h-[600px] bg-brand-400/20 rounded-full mix-blend-multiply filter blur-[100px] opacity-70 animate-float" style="animation-duration: 10s;"></div>
        <div class="absolute -bottom-[20%] -right-[10%] w-[500px] h-[500px] bg-amber-500/20 rounded-full mix-blend-multiply filter blur-[100px] opacity-70 animate-float" style="animation-delay: 2s; animation-duration: 12s;"></div>
    </div>

    <!-- Navigation -->
    <nav class="absolute top-0 w-full z-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-24 items-center justify-between animate-fade-in-up">
                <a href="{{ url('/') }}" class="flex items-center gap-4 group">
                    <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-brand-500/30 transition-transform group-hover:scale-105">
                        <span class="font-bold text-lg tracking-tight">GQ</span>
                    </div>
                    <div>
                        <span class="block text-lg font-extrabold text-slate-900 leading-none group-hover:text-brand-600 transition-colors">Griya Qur'an</span>
                        <span class="block text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-1">Kembali Ke Beranda</span>
                    </div>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Form Section -->
    <main class="flex-grow flex items-center justify-center px-4 pt-24 pb-8 w-full relative z-10">
        <section class="w-full max-w-md animate-fade-in-up" style="animation-delay: 200ms;">
            <div class="glass-card rounded-[2.5rem] p-8 relative overflow-hidden">
                
                <!-- Decorative element inside card -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-brand-500/10 rounded-full blur-2xl -mr-10 -mt-10"></div>
                
                <div class="mb-6 relative z-10">
                    <span class="inline-flex items-center rounded-full bg-brand-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-brand-700 mb-4 border border-brand-200">
                        Portal Akademik
                    </span>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 leading-tight">Masuk ke akun</h1>
                    <p class="mt-2 text-sm font-medium text-slate-500">Silakan masuk menggunakan kredensial yang diberikan oleh admin sekolah.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="space-y-4 relative z-10">
                    @csrf

                    <div>
                        <label for="login" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Email atau Username</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input
                                id="login"
                                name="login"
                                type="text"
                                value="{{ old('login') }}"
                                required
                                autofocus
                                placeholder="nama@domain.com atau username"
                                autocomplete="username"
                                class="block w-full rounded-2xl border-2 border-slate-100 bg-white/50 pl-11 pr-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none transition-all placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10"
                            >
                        </div>
                        @error('login')
                            <p class="mt-2 text-xs font-bold text-red-500 flex items-center gap-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Kata Sandi</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                placeholder="••••••••"
                                autocomplete="current-password"
                                class="block w-full rounded-2xl border-2 border-slate-100 bg-white/50 pl-11 pr-4 py-3 text-sm font-medium text-slate-900 shadow-sm outline-none transition-all placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10"
                            >
                        </div>
                        @error('password')
                            <p class="mt-2 text-xs font-bold text-red-500 flex items-center gap-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <label class="group flex items-center gap-3 cursor-pointer select-none">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" name="remember" value="1" class="peer h-5 w-5 appearance-none rounded-md border-2 border-slate-200 bg-white transition-all checked:border-brand-500 checked:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                <svg class="absolute h-3 w-3 text-white opacity-0 transition-opacity peer-checked:opacity-100 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-slate-600 group-hover:text-slate-900 transition-colors">Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <button type="submit" class="group relative inline-flex w-full items-center justify-center overflow-hidden rounded-2xl bg-slate-900 py-3.5 text-sm font-bold text-white shadow-xl shadow-slate-900/10 transition-all hover:scale-[1.02] hover:shadow-slate-900/20 focus:outline-none focus:ring-4 focus:ring-slate-900/10">
                        <span class="relative z-10 flex items-center gap-2">
                            Masuk Ke Portal
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                        <div class="absolute inset-0 z-0 h-full w-full bg-gradient-to-r from-brand-500 to-brand-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                    </button>

                    <div class="relative my-5 flex py-1 items-center">
                        <div class="flex-grow border-t border-slate-200/60"></div>
                        <span class="mx-4 flex-shrink text-xs font-bold uppercase tracking-wider text-slate-400">atau</span>
                        <div class="flex-grow border-t border-slate-200/60"></div>
                    </div>

                    <a href="{{ route('auth.google') }}" class="group inline-flex w-full items-center justify-center gap-3 rounded-2xl border-2 border-slate-200 bg-white py-3 text-sm font-bold text-slate-700 shadow-sm transition-all hover:bg-slate-50 hover:border-slate-300 hover:scale-[1.02] focus:outline-none focus:ring-4 focus:ring-slate-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        Masuk dengan Google
                    </a>
                </form>
            </div>
            
            <p class="text-center mt-8 text-xs font-medium text-slate-500">
                &copy; 2026 Griya Qur'an Tunas Ilmu.
            </p>
        </section>
    </main>

</body>
</html>
