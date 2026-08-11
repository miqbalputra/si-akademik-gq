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
    <meta name="description" content="Ruang akademik Griya Qur'an untuk guru, wali santri, dan manajemen sekolah.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pwa-head')
</head>
<body class="school-home overflow-x-hidden">
    <div class="school-announcement" role="status"><strong>SIAKAD GQ</strong> Ruang akademik untuk kegiatan belajar, catatan guru, dan perkembangan santri.</div>

    <header class="school-home-header">
        <nav class="school-home-header-inner" aria-label="Navigasi halaman depan">
            <a href="{{ url('/') }}" class="school-brand">
                <span class="school-mark">GQ</span>
                <span><strong>Griya Qur'an</strong><small>Tunas Ilmu · SIAKAD</small></span>
            </a>
            <div class="school-home-links">
                <a href="#peta" data-scroll-link>Peta Belajar</a>
                <a href="#ruang" data-scroll-link>Ruang Portal</a>
                <a href="#kegiatan" data-scroll-link>Kegiatan Akademik</a>
            </div>
            <div class="ml-auto flex items-center gap-2 sm:ml-0">
                @auth
                    <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-sm">Buka ruang saya <span aria-hidden="true">&rarr;</span></a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf<button type="submit" class="btn btn-outline btn-sm">Keluar</button></form>
                @else
                    <a href="{{ route('auth.google') }}" class="hidden text-xs font-bold text-slate-600 sm:inline">Masuk Google</a>
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Masuk portal <span aria-hidden="true">&rarr;</span></a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="school-campus" aria-labelledby="campus-title">
            <div>
                <p class="school-index">Ruang Akademik Griya Qur'an</p>
                <h1 id="campus-title" class="school-campus-title">Setiap kegiatan belajar punya <em>tempat untuk bertumbuh.</em></h1>
                <p class="school-campus-copy">SIAKAD menghubungkan kegiatan kelas, catatan guru, tinjauan sekolah, dan informasi perkembangan santri dalam satu ruang akademik yang dekat dengan keseharian sekolah.</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Masuk ke ruang akademik <span aria-hidden="true">&rarr;</span></a>
                    <a href="#peta" class="btn btn-outline btn-lg" data-scroll-link>Lihat perjalanan belajar</a>
                </div>
                <p class="mt-6 font-mono text-[10px] font-bold tracking-[.08em] text-slate-500">KELAS · HALAQAH · JURNAL · RAPOR · AGENDA</p>
            </div>

            <aside class="school-board" aria-label="Ilustrasi papan kegiatan sekolah">
                <div class="school-board-head"><h2>Papan Kegiatan Sekolah</h2><span>ILUSTRASI</span></div>
                <div class="school-board-list">
                    <div class="school-board-row"><time>07.30</time><div><strong>Kegiatan Kelas</strong><small>Materi dan kehadiran dicatat oleh guru.</small></div><span class="school-board-status">TERCATAT</span></div>
                    <div class="school-board-row"><time>10.00</time><div><strong>Halaqah Tahfidz</strong><small>Progres hafalan dibaca sesuai pendampingan.</small></div><span class="school-board-status">BERJALAN</span></div>
                    <div class="school-board-row"><time>SELESAI</time><div><strong>Rapor &amp; Arsip</strong><small>Informasi terbit setelah proses sekolah selesai.</small></div><span class="school-board-status">SIAP BACA</span></div>
                </div>
            </aside>
        </section>

        <section id="peta" class="school-section" data-learning-map aria-labelledby="map-title">
            <div class="school-section-inner">
                <p class="school-index">Peta perjalanan belajar</p>
                <h2 id="map-title" class="school-section-title">Satu kegiatan belajar, dipahami sesuai peran masing-masing.</h2>
                <div class="learning-map" role="group" aria-label="Tahap perjalanan belajar">
                    <div class="grid gap-2">
                        <button type="button" class="learning-map-step" aria-pressed="true" data-learning-map-step data-label="TAHAP 01 · KEGIATAN KELAS" data-title="Kegiatan Kelas" data-copy="Guru mencatat materi, kehadiran, nilai, dan setoran hafalan dari ruang kerja yang sesuai penugasannya." data-cta="Masuk ke Ruang Guru" data-href="{{ route('guru.dashboard') }}" data-notes="Jurnal|Presensi|Tahfidz"><span>01</span><span><strong>Kegiatan Kelas</strong><small>Belajar berlangsung dan guru mencatat prosesnya.</small></span><span aria-hidden="true">&rarr;</span></button>
                        <button type="button" class="learning-map-step" aria-pressed="false" data-learning-map-step data-label="TAHAP 02 · CATATAN GURU" data-title="Catatan Guru" data-copy="Catatan pembelajaran tersusun per kelas, mata pelajaran, halaqah, dan periode agar pekerjaan berikutnya jelas." data-cta="Buka Jurnal Guru" data-href="{{ route('guru.diniyyah-journals.index') }}" data-notes="Materi|Nilai|Kehadiran"><span>02</span><span><strong>Catatan Guru</strong><small>Jurnal dan penilaian menjadi rekam belajar yang rapi.</small></span><span aria-hidden="true">&rarr;</span></button>
                        <button type="button" class="learning-map-step" aria-pressed="false" data-learning-map-step data-label="TAHAP 03 · TINJAUAN SEKOLAH" data-title="Tinjauan Sekolah" data-copy="Manajemen melihat keterisian, melakukan validasi, dan menjaga arsip akademik siap diterbitkan." data-cta="Masuk ke Kendali Akademik" data-href="{{ url('/admin') }}" data-notes="Validasi|Leger|Arsip"><span>03</span><span><strong>Tinjauan Sekolah</strong><small>Proses akademik diperiksa sebelum informasi dibagikan.</small></span><span aria-hidden="true">&rarr;</span></button>
                        <button type="button" class="learning-map-step" aria-pressed="false" data-learning-map-step data-label="TAHAP 04 · PERKEMBANGAN SANTRI" data-title="Perkembangan Santri" data-copy="Wali membaca perkembangan anak, agenda sekolah, dan rapor yang telah dibuka dalam bahasa yang mudah dipahami." data-cta="Masuk ke Ruang Wali" data-href="{{ route('wali.dashboard') }}" data-notes="Rapor|Agenda|Progres"><span>04</span><span><strong>Perkembangan Santri</strong><small>Informasi yang tepat hadir untuk keluarga.</small></span><span aria-hidden="true">&rarr;</span></button>
                    </div>
                    <article class="learning-book" aria-live="polite" data-learning-preview>
                        <p class="learning-book-label" data-learning-label>TAHAP 01 · KEGIATAN KELAS</p>
                        <h3 data-learning-title>Kegiatan Kelas</h3>
                        <p data-learning-copy>Guru mencatat materi, kehadiran, nilai, dan setoran hafalan dari ruang kerja yang sesuai penugasannya.</p>
                        <div class="learning-book-notes" aria-label="Contoh catatan akademik">
                            <div data-learning-note><span>CATATAN</span><strong>Jurnal</strong></div>
                            <div data-learning-note><span>CATATAN</span><strong>Presensi</strong></div>
                            <div data-learning-note><span>CATATAN</span><strong>Tahfidz</strong></div>
                        </div>
                        <a href="{{ route('guru.dashboard') }}" class="mt-5 inline-flex font-mono text-xs font-black text-school-600 underline decoration-neon decoration-4 underline-offset-4" data-learning-cta>Masuk ke Ruang Guru</a>
                        <p class="mt-5 font-mono text-[9px] font-bold tracking-[.08em] text-slate-400">SIMULASI ALUR · TANPA DATA AKADEMIK NYATA</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="ruang" class="school-section bg-[#f0f3ed]" aria-labelledby="room-title">
            <div class="school-section-inner">
                <p class="school-index">Pintu ruang</p>
                <h2 id="room-title" class="school-section-title">Setiap warga sekolah memulai dari ruang yang tepat.</h2>
                <div class="school-door-grid">
                    <a href="{{ route('guru.dashboard') }}" class="school-door"><span class="school-index">Ruang 01</span><div><h3>Ruang Guru</h3><p>Kelas, jurnal, penilaian, presensi, tahfidz, dan tasmi' dalam ritme kerja mengajar.</p></div><span class="school-door-foot">BUKA RUANG GURU &rarr;</span></a>
                    <a href="{{ route('wali.dashboard') }}" class="school-door"><span class="school-index">Ruang 02</span><div><h3>Ruang Wali</h3><p>Perkembangan anak, agenda sekolah, tahfidz, serta rapor yang sudah diterbitkan.</p></div><span class="school-door-foot">BUKA RUANG WALI &rarr;</span></a>
                    <a href="{{ url('/admin') }}" class="school-door"><span class="school-index">Ruang 03</span><div><h3>Kendali Akademik</h3><p>Validasi, kalender, leger, arsip, dan pengelolaan proses akademik sekolah.</p></div><span class="school-door-foot">BUKA KENDALI AKADEMIK &rarr;</span></a>
                </div>
            </div>
        </section>

        <section id="kegiatan" class="school-section" aria-labelledby="activity-title">
            <div class="school-section-inner">
                <p class="school-index">Kegiatan akademik</p>
                <h2 id="activity-title" class="school-section-title">Dibangun dari aktivitas sekolah yang benar-benar berjalan setiap hari.</h2>
                <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <article class="school-work-card"><p class="school-index">01</p><h3 class="mt-4 text-lg font-black">Kelas &amp; Presensi</h3><p class="mt-2 text-sm leading-6 text-slate-600">Kehadiran dan kegiatan harian dekat dengan ruang kelas.</p></article>
                    <article class="school-work-card"><p class="school-index">02</p><h3 class="mt-4 text-lg font-black">Jurnal Diniyyah</h3><p class="mt-2 text-sm leading-6 text-slate-600">Materi pembelajaran tercatat per sesi dan guru.</p></article>
                    <article class="school-work-card"><p class="school-index">03</p><h3 class="mt-4 text-lg font-black">Halaqah Tahfidz</h3><p class="mt-2 text-sm leading-6 text-slate-600">Progres hafalan dan tasmi' terhubung dengan pendampingan.</p></article>
                    <article class="school-work-card"><p class="school-index">04</p><h3 class="mt-4 text-lg font-black">Rapor &amp; Arsip</h3><p class="mt-2 text-sm leading-6 text-slate-600">Ringkasan belajar diterbitkan dan disimpan dengan tertib.</p></article>
                </div>
            </div>
        </section>
    </main>

    <footer class="school-footer">&copy; {{ date('Y') }} GRIYA QUR'AN TUNAS ILMU · SISTEM INFORMASI AKADEMIK</footer>
</body>
</html>
