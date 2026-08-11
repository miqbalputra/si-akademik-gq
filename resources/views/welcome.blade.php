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
    <title>SIAKAD - Griya Qur'an Tunas Ilmu</title>
    <meta name="description" content="Sistem Informasi Akademik Griya Qur'an untuk guru, wali santri, dan manajemen sekolah.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="v-shell overflow-x-hidden">
    <div class="v-announcement" role="status">
        <span class="rounded bg-ink px-1.5 py-0.5 text-[10px] text-neon">SIAKAD</span>
        Satu alur akademik untuk seluruh peran sekolah.
    </div>

    <aside class="v-rail" aria-label="Jelajahi sistem">
        <a href="{{ url('/') }}" class="v-rail-brand">
            <span class="v-mark">GQ</span>
            <span>
                <strong class="block text-sm leading-none tracking-tight">Griya Qur'an</strong>
                <span class="mt-1 block font-mono text-[9px] font-bold tracking-[.14em] text-slate-500">SIAKAD</span>
            </span>
        </a>

        <p class="v-rail-heading">Product line</p>
        <nav aria-label="Bagian landing page">
            <a href="#alur" class="v-rail-card" data-scroll-link><span class="v-rail-dot"></span><span>Alur Akademik</span></a>
            <a href="#peran" class="v-rail-card" data-scroll-link><span class="v-rail-dot"></span><span>Portal Peran</span></a>
            <a href="#modul" class="v-rail-card" data-scroll-link><span class="v-rail-dot"></span><span>Modul Utama</span></a>
            <a href="#cara-kerja" class="v-rail-card" data-scroll-link><span class="v-rail-dot"></span><span>Cara Kerja</span></a>
        </nav>

        <div class="v-rail-foot">
            <p class="m-0 font-mono text-[10px] font-bold tracking-[.12em] text-neon">DEMO AMAN</p>
            <p class="mb-0 mt-2 text-xs leading-5 text-slate-300">Preview ini tidak menampilkan data akademik atau data santri nyata.</p>
        </div>
    </aside>

    <div class="v-stage">
        <nav class="v-topnav" aria-label="Navigasi utama">
            <div class="v-topnav-inner">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-mono text-xs font-black tracking-tight lg:hidden">
                    <span class="v-mark !h-7 !w-7 !text-[10px]">GQ</span> SIAKAD
                </a>
                <div class="hidden items-center gap-5 md:flex">
                    <a href="#alur" class="v-toplink" data-scroll-link>Alur</a>
                    <a href="#peran" class="v-toplink" data-scroll-link>Peran</a>
                    <a href="#modul" class="v-toplink" data-scroll-link>Modul</a>
                    <a href="#cara-kerja" class="v-toplink" data-scroll-link>Panduan</a>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    @auth
                        <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-sm">Buka dashboard <span aria-hidden="true">↗</span></a>
                        <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf<button type="submit" class="btn btn-outline btn-sm">Keluar</button></form>
                    @else
                        <a href="{{ route('auth.google') }}" class="hidden sm:inline-flex v-toplink rounded-md border border-line px-3 py-2">Masuk Google</a>
                        <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Masuk portal <span aria-hidden="true">→</span></a>
                    @endauth
                </div>
            </div>
        </nav>

        <main>
            <section id="alur" class="v-hero" data-factory>
                <div>
                    <p class="eyebrow"><span class="mr-2 inline-block h-2 w-2 rounded-full bg-neon"></span> Sistem informasi akademik</p>
                    <h1 class="v-hero-title">Progres santri,<br>terhubung dalam <span class="v-highlight">satu alur kerja.</span></h1>
                    <p class="v-hero-copy">SIAKAD Griya Qur'an membantu guru mencatat kegiatan, manajemen memvalidasi proses, dan wali santri memantau perkembangan dengan konteks yang sama.</p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Masuk ke portal <span aria-hidden="true">→</span></a>
                        <a href="#cara-kerja" class="btn btn-outline btn-lg" data-scroll-link>Lihat alurnya</a>
                    </div>
                    <p class="mt-6 font-mono text-[11px] font-semibold text-slate-500">Rapor digital · Tahfidz · Diniyyah · Presensi · Agenda</p>
                </div>

                <div class="v-factory" aria-label="Demo alur akademik">
                    <div class="v-stack" role="group" aria-label="Pilih tahap alur akademik">
                        <button type="button" class="v-stack-button" aria-pressed="true" data-factory-stage data-label="TAHAP 01 - PORTAL GURU" data-title="Input Guru" data-copy="Guru mencatat nilai, presensi, jurnal, dan hafalan dari halaman kerja yang sesuai dengan penugasannya." data-cta="Masuk sebagai guru" data-href="{{ route('guru.dashboard') }}" data-metrics="Jurnal|Nilai|Tahfidz">
                            <span>INPUT GURU</span><span aria-hidden="true">01</span>
                        </button>
                        <button type="button" class="v-stack-button" aria-pressed="false" data-factory-stage data-label="TAHAP 02 - PORTAL MANAJEMEN" data-title="Validasi Manajemen" data-copy="Kepala bagian dan admin memeriksa kelengkapan, melakukan koreksi, lalu menjaga alur akademik tetap siap diterbitkan." data-cta="Buka manajemen" data-href="{{ url('/admin') }}" data-metrics="Tinjau|Validasi|Rekap">
                            <span>VALIDASI MANAJEMEN</span><span aria-hidden="true">02</span>
                        </button>
                        <button type="button" class="v-stack-button" aria-pressed="false" data-factory-stage data-label="TAHAP 03 - PORTAL WALI" data-title="Ringkasan Wali" data-copy="Wali santri membaca progres anak, agenda, rapor yang telah dibuka, dan informasi sekolah dalam bahasa yang jelas." data-cta="Masuk sebagai wali" data-href="{{ route('wali.dashboard') }}" data-metrics="Rapor|Agenda|Progres">
                            <span>RINGKASAN WALI</span><span aria-hidden="true">03</span>
                        </button>
                        <button type="button" class="v-stack-button" aria-pressed="false" data-factory-stage data-label="TAHAP 04 - ARSIP AKADEMIK" data-title="Arsip Rapor" data-copy="Rapor yang disahkan disimpan sebagai snapshot yang dapat ditinjau, diunduh, dan dijadikan arsip resmi sekolah." data-cta="Lihat pengelolaan rapor" data-href="{{ url('/admin') }}" data-metrics="Snapshot|PDF|Excel">
                            <span>ARSIP RAPOR</span><span aria-hidden="true">04</span>
                        </button>
                    </div>
                    <article class="v-preview" aria-live="polite">
                        <p class="v-preview-label" data-factory-label>TAHAP 01 - PORTAL GURU</p>
                        <h2 class="v-preview-title" data-factory-title>Input Guru</h2>
                        <p class="v-preview-copy" data-factory-copy>Guru mencatat nilai, presensi, jurnal, dan hafalan dari halaman kerja yang sesuai dengan penugasannya.</p>
                        <div class="v-preview-metrics" aria-label="Contoh modul">
                            <div class="v-preview-metric" data-factory-metric><span>Modul</span><strong>Jurnal</strong></div>
                            <div class="v-preview-metric" data-factory-metric><span>Modul</span><strong>Nilai</strong></div>
                            <div class="v-preview-metric" data-factory-metric><span>Modul</span><strong>Tahfidz</strong></div>
                        </div>
                        <a href="{{ route('guru.dashboard') }}" class="mt-5 inline-flex font-mono text-xs font-black underline decoration-neon decoration-4 underline-offset-4" data-factory-cta>Masuk sebagai guru</a>
                    </article>
                </div>
            </section>

            <section id="peran" class="v-section">
                <div class="v-section-inner">
                    <p class="eyebrow">Portal peran</p>
                    <h2 class="v-section-title">Setiap pekerjaan memiliki ruang kerja yang tepat.</h2>
                    <div class="v-role-grid">
                        <a href="{{ route('wali.dashboard') }}" class="v-role-card">
                            <span class="v-role-index">01 / KELUARGA</span>
                            <div><h3>Wali Santri</h3><p>Rapor, tahfidz, presensi, agenda, dan informasi perkembangan anak.</p></div>
                            <span class="font-mono text-xs font-black">BUKA PORTAL →</span>
                        </a>
                        <a href="{{ route('guru.dashboard') }}" class="v-role-card">
                            <span class="v-role-index">02 / PENGAJAR</span>
                            <div><h3>Guru</h3><p>Input terarah untuk jurnal, nilai, presensi, tahfidz, dan tasmi'.</p></div>
                            <span class="font-mono text-xs font-black">BUKA PORTAL →</span>
                        </a>
                        <a href="{{ url('/admin') }}" class="v-role-card">
                            <span class="v-role-index">03 / MANAJEMEN</span>
                            <div><h3>Manajemen</h3><p>Kelola master data, validasi akademik, laporan, dan penerbitan rapor.</p></div>
                            <span class="font-mono text-xs font-black">BUKA PORTAL →</span>
                        </a>
                    </div>
                </div>
            </section>

            <section id="modul" class="v-section bg-[#f4f6f3]">
                <div class="v-section-inner">
                    <p class="eyebrow">Modul akademik</p>
                    <h2 class="v-section-title">Informasi penting tetap dapat ditemukan tanpa mencari jauh.</h2>
                    <div class="v-module-grid">
                        <article class="v-module-card"><span class="v-role-index">01</span><div><h3>Presensi</h3><p>Status harian dan rekap keterisian untuk kelas yang diampu.</p></div></article>
                        <article class="v-module-card"><span class="v-role-index">02</span><div><h3>Diniyyah</h3><p>Nilai, jurnal mengajar, monitoring, dan leger terhubung.</p></div></article>
                        <article class="v-module-card"><span class="v-role-index">03</span><div><h3>Tahfidz</h3><p>Progres hafalan, setoran pekanan, UAS, dan tasmi'.</p></div></article>
                        <article class="v-module-card"><span class="v-role-index">04</span><div><h3>Rapor</h3><p>Snapshot yang siap dibaca, diunduh, serta diarsipkan.</p></div></article>
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="v-section">
                <div class="v-section-inner" data-flow-explorer>
                    <p class="eyebrow">Cara kerja</p>
                    <h2 class="v-section-title">Pilih sebuah tahap untuk melihat perannya dalam proses.</h2>
                    <div class="v-flow" role="group" aria-label="Tahapan proses akademik">
                        <button type="button" class="v-flow-button" aria-pressed="true" data-flow-step data-detail="Guru memasukkan aktivitas dan nilai sesuai kelas, mapel, atau halaqah yang menjadi penugasannya."><span class="v-flow-number">01</span><span><strong>Catat kegiatan</strong><br><small class="text-slate-500">Data dicatat dekat dengan aktivitas belajar.</small></span><span aria-hidden="true">↗</span></button>
                        <button type="button" class="v-flow-button" aria-pressed="false" data-flow-step data-detail="Manajemen melihat keterisian, memeriksa ketidaksesuaian, dan menjalankan validasi sebelum informasi diterbitkan."><span class="v-flow-number">02</span><span><strong>Periksa dan validasi</strong><br><small class="text-slate-500">Status proses terlihat tanpa membuka banyak halaman.</small></span><span aria-hidden="true">↗</span></button>
                        <button type="button" class="v-flow-button" aria-pressed="false" data-flow-step data-detail="Wali santri membaca hasil yang relevan untuk anaknya sendiri, setelah sekolah membuka informasi tersebut."><span class="v-flow-number">03</span><span><strong>Pantau perkembangan</strong><br><small class="text-slate-500">Informasi disajikan dengan konteks keluarga.</small></span><span aria-hidden="true">↗</span></button>
                    </div>
                    <p class="v-flow-detail" data-flow-detail aria-live="polite">Guru memasukkan aktivitas dan nilai sesuai kelas, mapel, atau halaqah yang menjadi penugasannya.</p>
                </div>
            </section>
        </main>

        <footer class="border-t border-line px-4 py-8 text-center font-mono text-[10px] font-bold tracking-[.08em] text-slate-500">© {{ date('Y') }} GRIYA QUR'AN TUNAS ILMU - SIAKAD</footer>
    </div>
</body>
</html>
