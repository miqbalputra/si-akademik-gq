<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIAKAD &mdash; Griya Qur'an Tunas Ilmu</title>
    <meta name="description" content="Sistem Informasi Akademik Griya Qur'an. Portal rapor digital, pantauan tahfidz, dan kehadiran santri.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { sans: ['Outfit', 'sans-serif'] },
                        colors: {
                            brand: {
                                50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a',
                                300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b',
                                600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f'
                            }
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #fafaf8; color: #1e293b; margin: 0; -webkit-font-smoothing: antialiased; }

        /* ---- Background ---- */
        .bg-hero {
            background-color: #fafaf8;
            background-image:
                linear-gradient(to right,  rgba(0,0,0,.025) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ---- Nav ---- */
        .landing-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(241,245,249,.8);
            box-shadow: 0 1px 0 rgba(0,0,0,.04);
        }
        .nav-inner {
            max-width: 1100px; margin: 0 auto;
            padding: 0 24px;
            height: 68px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .logo-wrap { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .logo-badge {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 16px; color: #fff;
            box-shadow: 0 4px 12px rgba(217,119,6,.3);
            position: relative;
        }
        .logo-text { line-height: 1.2; }
        .logo-text strong { display: block; font-size: 15px; font-weight: 800; color: #0f172a; }
        .logo-text span   { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: #d97706; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .nav-btn-ghost {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 10px;
            border: 1.5px solid #e2e8f0; background: transparent;
            font-size: 13px; font-weight: 700; color: #475569;
            text-decoration: none; transition: all .2s; cursor: pointer;
        }
        .nav-btn-ghost:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
        .nav-btn-google {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; border-radius: 10px;
            border: 1.5px solid #e2e8f0; background: #fff;
            font-size: 13px; font-weight: 700; color: #374151;
            text-decoration: none; transition: all .2s;
        }
        .nav-btn-google:hover { border-color: #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .nav-btn-primary {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 20px; border-radius: 10px;
            background: #0f172a; color: #fff; border: none;
            font-size: 13px; font-weight: 700;
            text-decoration: none; transition: all .2s; cursor: pointer;
        }
        .nav-btn-primary:hover { background: #1e293b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,.2); }

        /* ---- Hero ---- */
        .hero {
            padding: 120px 24px 80px;
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
        }
        .hero-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: #fef3c7; border: 1px solid #fde68a;
            border-radius: 999px; padding: 5px 16px;
            font-size: 12px; font-weight: 700; color: #92400e;
            margin-bottom: 28px;
        }
        .hero-pill-dot { width: 6px; height: 6px; border-radius: 50%; background: #d97706; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }
        .hero h1 {
            font-size: clamp(36px, 6vw, 60px);
            font-weight: 900; line-height: 1.1;
            color: #0f172a; margin: 0 0 20px;
            letter-spacing: -.02em;
        }
        .hero h1 .brand-text {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero p { font-size: 17px; color: #64748b; font-weight: 500; line-height: 1.7; margin: 0 auto 40px; max-width: 520px; }

        /* ---- Portal Cards ---- */
        .portals-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
            max-width: 900px; margin: 0 auto 60px;
            padding: 0 24px;
        }
        @media (max-width: 768px) { .portals-grid { grid-template-columns: 1fr; max-width: 420px; } }

        .portal-card {
            background: #fff; border: 1px solid #f1f5f9;
            border-radius: 24px; padding: 32px 28px;
            text-decoration: none; color: inherit;
            transition: all .3s cubic-bezier(.16,1,.3,1);
            display: flex; flex-direction: column;
            position: relative; overflow: hidden;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 48px -12px rgba(0,0,0,.12);
            border-color: transparent;
        }
        .portal-card .arrow-icon {
            position: absolute; top: 24px; right: 24px;
            width: 28px; height: 28px;
            background: #f8fafc; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8;
            opacity: 0; transform: translateX(-4px);
            transition: all .25s;
        }
        .portal-card:hover .arrow-icon { opacity: 1; transform: translateX(0); }

        .portal-icon {
            width: 56px; height: 56px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px; flex-shrink: 0;
        }
        .portal-icon.emerald { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #059669; }
        .portal-icon.indigo  { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4f46e5; }
        .portal-icon.slate   { background: linear-gradient(135deg, #1e293b, #0f172a); color: #f1f5f9; }

        .portal-card h2 { font-size: 19px; font-weight: 800; color: #0f172a; margin: 0 0 8px; }
        .portal-card p  { font-size: 13px; color: #64748b; line-height: 1.6; margin: 0; }

        .portal-card.wali:hover  { box-shadow: 0 20px 48px -12px rgba(5,150,105,.15); }
        .portal-card.guru:hover  { box-shadow: 0 20px 48px -12px rgba(79,70,229,.15); }
        .portal-card.admin:hover { box-shadow: 0 20px 48px -12px rgba(15,23,42,.15); }

        /* ---- Demo Section ---- */
        .demo-section {
            max-width: 860px; margin: 0 auto;
            padding: 0 24px 80px;
        }
        .demo-card {
            background: #fff; border: 1px solid #f1f5f9;
            border-radius: 24px; overflow: hidden;
            box-shadow: 0 4px 24px -8px rgba(0,0,0,.06);
        }
        .demo-header {
            background: #fafaf8; border-bottom: 1px solid #f1f5f9;
            padding: 20px 28px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .demo-header-left { display: flex; align-items: center; gap: 14px; }
        .demo-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: #fef3c7;
            display: flex; align-items: center; justify-content: center; color: #d97706;
        }
        .demo-header h3 { font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 2px; }
        .demo-header p  { font-size: 12px; color: #64748b; font-weight: 500; margin: 0; }
        .demo-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 10px;
            background: #d97706; color: #fff; border: none;
            font-size: 12px; font-weight: 700;
            text-decoration: none; transition: all .2s; white-space: nowrap;
            box-shadow: 0 2px 8px rgba(217,119,6,.3);
        }
        .demo-btn:hover { background: #b45309; transform: translateY(-1px); }
        .demo-table { width: 100%; border-collapse: collapse; }
        .demo-table th { padding: 10px 20px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; background: #fafaf8; border-bottom: 1px solid #f1f5f9; }
        .demo-table td { padding: 12px 20px; font-size: 13px; font-weight: 600; color: #334155; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        .demo-table tr:last-child td { border-bottom: 0; }
        .demo-table tr:hover td { background: #fafaf8; }
        .demo-table td:first-child { font-weight: 700; color: #0f172a; }
        .code-chip {
            display: inline-flex; align-items: center;
            background: #f1f5f9; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 2px 10px;
            font-family: 'Courier New', monospace; font-size: 12px; color: #475569;
        }

        /* ---- Footer ---- */
        .landing-footer {
            border-top: 1px solid #f1f5f9; background: #fff;
            padding: 24px;
            text-align: center;
            font-size: 12px; font-weight: 500; color: #94a3b8;
        }

        /* ---- Animations ---- */
        @keyframes fadeInUp { 0% { opacity:0; transform:translateY(20px); } 100% { opacity:1; transform:translateY(0); } }
        .fade-up { animation: fadeInUp .6s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .2s; }
        .delay-3 { animation-delay: .25s; }
        .delay-4 { animation-delay: .3s; }
        .delay-5 { animation-delay: .35s; }
        .delay-6 { animation-delay: .4s; }
    </style>

    @include('partials.pwa-head')
</head>
<body>

    {{-- ===== NAVBAR ===== --}}
    <nav class="landing-nav">
        <div class="nav-inner">
            <a href="{{ url('/') }}" class="logo-wrap">
                <div class="logo-badge">GQ</div>
                <div class="logo-text">
                    <strong>Griya Qur'an</strong>
                  </div>
            </a>
            <div class="nav-actions">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-btn-ghost">
                            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                            Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('auth.google') }}" class="nav-btn-google">
                        <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        Masuk Google
                    </a>
                    <a href="{{ route('login') }}" class="nav-btn-primary">
                        Masuk Portal
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ===== HERO ===== --}}
    <div class="bg-hero" style="min-height: 100vh;">
        <section class="hero fade-up">
            <div class="hero-pill">
                <span class="hero-pill-dot"></span>
                Sistem Informasi Akademik Terpadu
            </div>
            <h1>
                Rapor Digital &amp; <br>
                <span class="brand-text">Pantauan Tahfidz</span>
            </h1>
            <p>
                Satu portal cerdas yang menghubungkan Orang Tua, Guru, dan Manajemen Sekolah dalam mengawal progres akademik dan hafalan santri secara real-time.
            </p>
        </section>

        {{-- ===== PORTAL CARDS ===== --}}
        <div class="portals-grid">
            <a href="{{ route('wali.dashboard') }}" class="portal-card wali fade-up delay-2">
                <div class="arrow-icon">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </div>
                <div class="portal-icon emerald">
                    <svg style="width:26px;height:26px;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                </div>
                <h2>Wali Santri</h2>
                <p>Pantau rapor digital, progres hafalan, dan agenda sekolah anak Anda kapan saja.</p>
            </a>

            <a href="{{ route('guru.diniyyah-scores.index') }}" class="portal-card guru fade-up delay-3">
                <div class="arrow-icon">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </div>
                <div class="portal-icon indigo">
                    <svg style="width:26px;height:26px;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </div>
                <h2>Guru Pengajar</h2>
                <p>Input nilai, kehadiran siswa, dan setoran hafalan dengan cepat dan mudah.</p>
            </a>

            <a href="{{ url('/admin') }}" class="portal-card admin fade-up delay-4">
                <div class="arrow-icon">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </div>
                <div class="portal-icon slate">
                    <svg style="width:26px;height:26px;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                </div>
                <h2>Manajemen</h2>
                <p>Dasbor admin untuk kelola data, validasi rapor, dan monitor aktivitas akademik.</p>
            </a>
        </div>

        {{-- ===== DEMO SECTION ===== --}}
        <div class="demo-section">
            <div class="demo-card fade-up delay-5">
                <div class="demo-header">
                    <div class="demo-header-left">
                        <div class="demo-icon">
                            <svg style="width:22px;height:22px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21a48.278 48.278 0 0 1-8.132-.687c-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" /></svg>
                        </div>
                        <div>
                            <h3>Akun Demo Sistem</h3>
                            <p>Gunakan kredensial ini untuk mencoba semua fitur SIAKAD.</p>
                        </div>
                    </div>
                    <a href="{{ url('/admin/demo-flow') }}" class="demo-btn">
                        Panduan Demo
                        <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
                <table class="demo-table">
                    <thead>
                        <tr>
                            <th>Peran</th>
                            <th>Email / Username</th>
                            <th>Kata Sandi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $accounts = [
                                ['role' => 'Admin Utama',    'email' => 'admin@example.com'],
                                ['role' => 'PJ Diniyyah',    'email' => 'kabag@example.com'],
                                ['role' => 'Kepala Sekolah', 'email' => 'kepala@example.com'],
                                ['role' => 'Guru Pengajar',  'email' => 'guru@example.com'],
                                ['role' => 'Wali Kelas',     'email' => 'walikelas@example.com'],
                                ['role' => 'Wali Santri',    'email' => 'wali@example.com'],
                            ];
                        @endphp
                        @foreach ($accounts as $acc)
                            <tr>
                                <td>{{ $acc['role'] }}</td>
                                <td><span class="code-chip">{{ $acc['email'] }}</span></td>
                                <td><span class="code-chip">password</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== FOOTER ===== --}}
    <footer class="landing-footer">
        &copy; {{ date('Y') }} Griya Qur'an Tunas Ilmu. Hak Cipta Dilindungi.
    </footer>

</body>
</html>
