<x-layouts.portal title="Dashboard Guru" portalLabel="Portal Guru">
    @php
        $today = \Illuminate\Support\Carbon::now('Asia/Jakarta');
        $diniyyahClasses = $diniyyahAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id');
        $singleJournalLink = $diniyyahClasses->count() === 1
            ? route('guru.diniyyah-journals.index', ['classroom_term_id' => $diniyyahClasses->first()->id])
            : route('guru.diniyyah-journals.index');
        $hasTeachingTasks = $homeroomClassroomTerms->isNotEmpty()
            || $diniyyahAssessmentSets->isNotEmpty()
            || $diniyyahAssignments->isNotEmpty()
            || $tahfidzHalaqahs->isNotEmpty()
            || ($tasmiExaminerAssignment ?? null) !== null;
    @endphp

    <div class="space-y-8">
        {{-- Welcome / orientation --}}
        <header class="school-dashboard-hero p-6 sm:p-8 lg:p-10">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface/10 px-3 py-1 font-mono text-[11px] font-semibold uppercase tracking-[.16em] text-on-primary">
                        <span class="h-1.5 w-1.5 rounded-full bg-on-primary"></span>
                        Papan Kegiatan Guru
                    </span>
                    <h1 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">
                        Kelas hari ini, {{ $teacher->name ?? auth()->user()->name }}
                    </h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-on-primary/80 sm:text-base">
                        Mulai dari kegiatan yang perlu diselesaikan: catatan kelas, presensi, penilaian, dan agenda mengajar.
                    </p>
                </div>

                <div class="school-today-note w-full sm:w-auto sm:min-w-64">
                    <p class="font-mono text-[10px] font-semibold uppercase tracking-[.16em] text-on-primary/80">Hari ini</p>
                    <p class="mt-1 text-sm font-bold text-on-primary">{{ $today->locale('id')->translatedFormat('l, d F Y') }}</p>
                    <a href="{{ route('guru.calendar') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-surface px-4 py-2.5 text-xs font-semibold text-heading transition-colors hover:bg-warning-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Lihat kalender mengajar
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <section aria-labelledby="quick-actions-heading">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="school-index">Kegiatan Hari Ini</p>
                    <h2 id="quick-actions-heading" class="mt-2 text-xl font-semibold text-heading">Papan Kegiatan</h2>
                </div>
                <p class="text-sm font-medium text-muted">Pilih catatan atau agenda yang ingin Anda selesaikan.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ $singleJournalLink }}" class="group rounded-2xl border border-success-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-success-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-success-soft text-success-ink" aria-hidden="true">✎</span>
                    <span class="mt-3 block text-sm font-semibold text-heading">Isi jurnal Diniyyah</span>
                    <span class="mt-1 block text-xs font-medium text-muted">Catat materi dan kehadiran kelas.</span>
                </a>
                @if($teacher)
                    <a href="{{ route('guru.diniyyah-substitute-journals.index') }}" class="group rounded-2xl border border-warning-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-warning-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning-soft text-warning-ink" aria-hidden="true">↺</span>
                        <span class="mt-3 block text-sm font-semibold text-heading">Jurnal guru pengganti</span>
                        <span class="mt-1 block text-xs font-medium text-muted">Isi jurnal saat menggantikan guru lain.</span>
                    </a>
                @endif
                @if($hasSimultaneousTafsirSchedule ?? false)
                    <a href="{{ route('guru.diniyyah-tafsir-journals.index') }}" class="group rounded-2xl border border-brand-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-brand-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-brand-ink" aria-hidden="true">☷</span>
                        <span class="mt-3 block text-sm font-semibold text-heading">Jurnal Tafsir serentak</span>
                        <span class="mt-1 block text-xs font-medium text-muted">Catat satu materi untuk beberapa kelas sekaligus.</span>
                    </a>
                @endif
                <a href="{{ route('guru.performa', ['month' => $performaMonth, 'year' => $performaYear]) }}" class="group rounded-2xl border border-warning-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-warning-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning-soft text-warning-ink" aria-hidden="true">▥</span>
                    <span class="mt-3 block text-sm font-semibold text-heading">Lihat performa</span>
                    <span class="mt-1 block text-xs font-medium text-muted">Cek jurnal kosong dan download laporan.</span>
                </a>
                @if($teacher)
                    <a href="{{ route('guru.attendance-report.index') }}" class="group rounded-2xl border border-info-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-info-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-info-soft text-info-ink" aria-hidden="true">✓</span>
                        <span class="mt-3 block text-sm font-semibold text-heading">Presensi saya</span>
                        <span class="mt-1 block text-xs font-medium text-muted">Lihat rekap GeoPresensi dan unduh laporan.</span>
                    </a>
                @endif
                <a href="{{ route('guru.calendar') }}" class="group rounded-2xl border border-brand-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-brand-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-brand-ink" aria-hidden="true">◷</span>
                    <span class="mt-3 block text-sm font-semibold text-heading">Buka kalender</span>
                    <span class="mt-1 block text-xs font-medium text-muted">Lihat jadwal dan agenda terdekat.</span>
                </a>
                @if(auth()->user()?->hasRole('kabag_tahfidz'))
                    <a href="{{ route('admin.tasmi-report.index') }}" class="group rounded-2xl border border-brand-line bg-surface p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-brand-line hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-soft text-brand-ink" aria-hidden="true">◫</span>
                        <span class="mt-3 block text-sm font-semibold text-heading">Monitoring Tasmi' Semua Kelas</span>
                        <span class="mt-1 block text-xs font-medium text-muted">Pantau hasil lintas kelas dan PJ Tasmi'.</span>
                    </a>
                @endif
            </div>
        </section>

        {{-- Highest-priority task --}}
        @if($performa !== null)
            <section class="rounded-2xl border {{ $performa['stats']['kosong'] > 0 ? 'border-danger-line bg-danger-soft/70' : 'border-success-line bg-success-soft/70' }} p-5 shadow-sm sm:p-6" aria-labelledby="performa-heading">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $performa['stats']['kosong'] > 0 ? 'bg-danger-soft-strong text-danger-ink' : 'bg-success-soft-strong text-success-ink' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2Zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m0 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v14Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[.16em] {{ $performa['stats']['kosong'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">Performa mengajar</p>
                            <h2 id="performa-heading" class="mt-1 text-lg font-semibold text-heading">Performa Mengajar Saya <span class="font-semibold text-muted">— Jurnal {{ $performa['month_label'] }}</span></h2>
                            <p class="mt-1 text-sm text-body">
                                @if($performa['stats']['kosong'] > 0)
                                    Ada jurnal yang perlu dilengkapi agar rekap mengajar tetap rapi.
                                @else
                                    Semua jurnal yang sudah lewat pada bulan ini telah tercatat.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-5 rounded-2xl bg-surface/70 px-4 py-3 lg:min-w-64 lg:justify-center">
                        <div>
                            <p class="text-2xl font-semibold {{ $performa['stats']['kosong'] > 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $performa['stats']['kosong'] }}</p>
                            <p class="text-[11px] font-bold text-muted">slot belum diisi</p>
                        </div>
                        <div class="h-9 w-px bg-surface-muted"></div>
                        <div>
                            <p class="text-2xl font-semibold text-heading">{{ $performa['stats']['sudah_diisi'] }}</p>
                            <p class="text-[11px] font-bold text-muted">slot selesai</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 border-t border-black/5 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <form method="GET" action="{{ route('guru.dashboard') }}" class="flex items-center gap-2">
                        <label for="performa-month" class="text-xs font-bold text-body">Tampilkan bulan</label>
                        <select id="performa-month" name="month" class="form-input w-auto min-w-36 bg-surface py-2 text-xs" onchange="var o=this.options[this.selectedIndex]; this.form.year.value=o.dataset.year; this.form.submit()">
                            @foreach($performaMonthOptions as $opt)
                                <option value="{{ $opt['value']['month'] }}" data-year="{{ $opt['value']['year'] }}" @if((int) $performaMonth === $opt['value']['month'] && (int) $performaYear === $opt['value']['year']) selected @endif>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="year" value="{{ $performaYear }}">
                        <noscript><button type="submit" class="btn btn-sm">Tampilkan</button></noscript>
                    </form>
                    <a href="{{ route('guru.performa', ['month' => $performaMonth, 'year' => $performaYear]) }}" class="inline-flex items-center gap-2 text-sm font-semibold {{ $performa['stats']['kosong'] > 0 ? 'text-danger-ink hover:text-danger-ink' : 'text-success-ink hover:text-success-ink' }} focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-500">
                        {{ $performa['stats']['kosong'] > 0 ? 'Isi jurnal yang kosong' : 'Lihat detail performa' }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </section>

            @php
                $reconciliation = $performa['reconciliation'] ?? [];
                $reconciliationStats = $reconciliation['stats'] ?? [];
                $reconciliationCount = array_sum($reconciliationStats);
            @endphp
            <section class="rounded-2xl border {{ ($reconciliation['available'] ?? false) ? (($reconciliationCount ?? 0) > 0 ? 'border-brand-line bg-brand-soft/70' : 'border-success-line bg-success-soft/60') : 'border-warning-line bg-warning-soft' }} p-5 shadow-sm sm:p-6" aria-labelledby="attendance-journal-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[.16em] {{ ($reconciliation['available'] ?? false) ? 'text-brand-ink' : 'text-warning-ink' }}">Sinkronisasi operasional</p>
                        <h2 id="attendance-journal-heading" class="mt-1 text-lg font-semibold text-heading">Kesesuaian Presensi &amp; Jurnal</h2>
                        @if(! ($reconciliation['available'] ?? false))
                            <p class="mt-1 text-sm text-warning-ink">{{ $reconciliation['message'] ?? 'Status presensi belum dapat diverifikasi.' }}</p>
                        @elseif($reconciliationCount > 0)
                            <p class="mt-1 text-sm text-brand-ink">Ada {{ $reconciliationCount }} slot yang perlu dicek setelah sesi berakhir.</p>
                        @else
                            <p class="mt-1 text-sm text-success-ink">Presensi dan jurnal yang sudah jatuh tempo pada periode ini sudah selaras.</p>
                        @endif
                    </div>
                    <a href="{{ route('guru.performa', ['month' => $performa['month'], 'year' => $performa['year']]) }}#attendance-journal-reconciliation" class="inline-flex shrink-0 items-center justify-center rounded-xl {{ ($reconciliation['available'] ?? false) && $reconciliationCount > 0 ? 'bg-brand-700 text-white hover:bg-brand-800' : 'border border-line-strong bg-surface text-body hover:border-brand-line hover:bg-brand-soft' }} px-4 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
                        Lihat rincian
                    </a>
                </div>
                @if(($reconciliation['available'] ?? false))
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-brand-line bg-surface/80 px-3 py-2.5"><p class="text-xl font-semibold text-brand-ink">{{ $reconciliationStats['hadir_tanpa_jurnal'] ?? 0 }}</p><p class="text-[11px] font-bold text-brand-ink">hadir tanpa jurnal</p></div>
                        <div class="rounded-xl border border-brand-line bg-surface/80 px-3 py-2.5"><p class="text-xl font-semibold text-brand-ink">{{ $reconciliationStats['presensi_belum_tercatat'] ?? 0 }}</p><p class="text-[11px] font-bold text-brand-ink">presensi belum tercatat</p></div>
                        <div class="rounded-xl border border-danger-line bg-surface/80 px-3 py-2.5"><p class="text-xl font-semibold text-danger-ink">{{ $reconciliationStats['presensi_dan_jurnal_belum_tercatat'] ?? 0 }}</p><p class="text-[11px] font-bold text-danger-ink">keduanya belum tercatat</p></div>
                    </div>
                @endif
            </section>
        @endif

        {{-- Role-based entry points --}}
        <section aria-labelledby="task-heading">
            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-warning-ink">Akses utama</p>
                    <h2 id="task-heading" class="mt-1 text-2xl font-semibold tracking-tight text-heading">Tugas dan kelas Anda</h2>
                </div>
                <p class="text-sm font-medium text-muted">Pilih area kerja sesuai peran mengajar Anda.</p>
            </div>

            @if($hasTeachingTasks)
                <div class="grid gap-5 lg:grid-cols-12">
                    @if($homeroomClassroomTerms->isNotEmpty())
                        <section class="rounded-2xl border border-info-line bg-surface p-5 shadow-sm sm:p-6 {{ $diniyyahAssignments->isNotEmpty() || $diniyyahAssessmentSets->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="homeroom-heading">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-info-soft text-info-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a6 6 0 0 0-9-5.197M9 20H4v-1a6 6 0 0 1 12 0v1m-3-9a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm6-3a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="homeroom-heading" class="text-lg font-semibold text-heading">Wali Kelas</h3>
                                            <span class="badge badge-blue">{{ $homeroomClassroomTerms->count() }} kelas</span>
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-muted">Kelola presensi harian dan pantau jurnal kelas yang Anda dampingi.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                @foreach($homeroomClassroomTerms->take(4) as $term)
                                    <div class="rounded-xl border border-line bg-surface-subtle px-3 py-2.5">
                                        <p class="truncate text-sm font-bold text-heading">{{ $term->name }}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold text-soft">{{ $term->academicTerm->name ?? 'Periode aktif' }}</p>
                                    </div>
                                @endforeach
                                @if($homeroomClassroomTerms->count() > 4)
                                    <p class="self-center px-1 text-xs font-bold text-soft">+ {{ $homeroomClassroomTerms->count() - 4 }} kelas lainnya</p>
                                @endif
                            </div>

                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                <a href="{{ route('attendance.index') }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-info-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-info-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info-700">
                                    Input presensi
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                                <a href="{{ route('wali.diniyyah-journals.index') }}" class="inline-flex flex-1 items-center justify-center rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-info-line hover:bg-info-soft hover:text-info-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info-700">
                                    Pantau jurnal kelas
                                </a>
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('guru.tasmi-wali.index') }}" class="flex items-center justify-between rounded-xl border border-line bg-surface-subtle px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-success-line hover:bg-success-soft hover:text-success-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-success-ink" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        Tasmi&#039; Kelas Saya <span class="text-[10px] font-bold text-soft">(read-only)</span>
                                    </span>
                                    <span class="text-soft">→</span>
                                </a>
                            </div>
                        </section>
                    @endif

                    @if($diniyyahAssessmentSets->isNotEmpty() || $diniyyahAssignments->isNotEmpty())
                        <section class="rounded-2xl border border-success-line bg-surface p-5 shadow-sm sm:p-6 {{ $homeroomClassroomTerms->isNotEmpty() ? 'lg:col-span-5' : 'lg:col-span-12' }}" aria-labelledby="diniyyah-heading">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-success-soft text-success-ink">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a3 3 0 0 1 6 0M9 5a3 3 0 0 0 6 0m-6 7h6m-6 4h4" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 id="diniyyah-heading" class="text-lg font-semibold text-heading">Guru Diniyyah</h3>
                                        <span class="badge badge-green">{{ $diniyyahAssignments->count() }} penugasan</span>
                                        @if($diniyyahAssignments->isNotEmpty())
                                            <span class="badge badge-indigo">Jadwal Mengajar</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm leading-5 text-muted">Input nilai dan jurnal untuk mapel Diniyyah yang Anda ampu.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-success-line bg-success-soft/60 p-3">
                                    <p class="text-2xl font-semibold text-success-ink">{{ $diniyyahAssessmentSets->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-success-ink">tugas nilai aktif</p>
                                </div>
                                <div class="rounded-xl border border-line bg-surface-subtle p-3">
                                    <p class="text-2xl font-semibold text-heading">{{ $diniyyahClasses->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-muted">kelas diajar</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-2">
                                <a href="{{ route('guru.diniyyah-scores.index') }}" class="group flex items-center justify-between rounded-xl bg-success-700 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-success-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                    Input nilai Diniyyah
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                                <a href="{{ $singleJournalLink }}" class="flex items-center justify-between rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-success-line hover:bg-success-soft hover:text-success-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                    Jurnal kelas
                                    <span class="text-soft">→</span>
                                </a>
                                @if($hasSimultaneousTafsirSchedule ?? false)
                                    <a href="{{ route('guru.diniyyah-tafsir-journals.index') }}" class="flex items-center justify-between rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-success-line hover:bg-success-soft hover:text-success-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                        Jurnal Tafsir
                                        <span class="text-soft">→</span>
                                    </a>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if($tahfidzHalaqahs->isNotEmpty())
                        <section class="rounded-2xl border border-brand-line bg-surface p-5 shadow-sm sm:p-6 lg:col-span-5" aria-labelledby="tahfidz-heading">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13c-1.168-.776-2.754-1.253-4.5-1.253-1.746 0-3.332.477-4.5 1.253m0-13v13" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="tahfidz-heading" class="text-lg font-semibold text-heading">Guru Tahfidz</h3>
                                            <span class="badge badge-purple">{{ $tahfidzHalaqahs->count() }} halaqah</span>
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-muted">Catat setoran hafalan santri pada halaqah Anda.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 space-y-2">
                                @foreach($tahfidzHalaqahs->take(3) as $halaqah)
                                    <div class="flex items-center justify-between rounded-xl border border-line bg-surface-subtle px-3 py-2.5">
                                        <p class="text-sm font-bold text-heading">{{ $halaqah->name ?: 'Halaqah Tahfidz' }}</p>
                                        <span class="text-[11px] font-bold text-soft">{{ $halaqah->activeMembers->count() }} santri</span>
                                    </div>
                                @endforeach
                                @if($tahfidzHalaqahs->count() > 3)
                                    <p class="px-1 text-xs font-bold text-soft">+ {{ $tahfidzHalaqahs->count() - 3 }} halaqah lainnya</p>
                                @endif
                            </div>

                            <a href="{{ route('guru.tahfidz.index') }}" class="mt-5 inline-flex w-full items-center justify-between rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
                                Buka jurnal Tahfidz
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </section>
                    @endif

                    @if($tasmiExaminerAssignment ?? null)
                        <section class="rounded-2xl border border-success-line bg-surface p-5 shadow-sm sm:p-6 {{ $tahfidzHalaqahs->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="tasmi-heading">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-success-soft text-success-ink">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="tasmi-heading" class="text-lg font-semibold text-heading">PJ Tasmi&#039;</h3>
                                            <span class="badge badge-green">{{ $tasmiEligibleClassrooms->count() }} kelas</span>
                                            @if($tasmiGenderScope === 'male')
                                                <span class="badge badge-blue">Ikhwan</span>
                                            @elseif($tasmiGenderScope === 'female')
                                                <span class="badge badge-pink" style="background:var(--ui-brand-soft-strong);color:var(--ui-brand-ink);">Akwat</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-muted">Catat setoran ujian tasmi&#039; santri (1 juz / 5 juz).</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-success-line bg-success-soft/60 p-3">
                                    <p class="text-2xl font-semibold text-success-ink">{{ $tasmiRecordsCount }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-success-ink">record tasmi&#039;</p>
                                </div>
                                <div class="rounded-xl border border-line bg-surface-subtle p-3">
                                    <p class="text-2xl font-semibold text-heading">{{ $tasmiEligibleClassrooms->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-muted">kelas bisa diuji</p>
                                </div>
                            </div>

                            @if($tasmiEligibleClassrooms->isNotEmpty())
                                <div class="mt-5 space-y-2">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($tasmiEligibleClassrooms->take(5) as $ct)
                                            <a href="{{ route('guru.tasmi.create', ['classroom_term_id' => $ct->id]) }}" class="rounded-lg border border-line bg-surface px-2.5 py-1 text-xs font-bold text-body transition-colors hover:border-success-line hover:bg-success-soft hover:text-success-ink">
                                                {{ $ct->classroom->name ?? $ct->name }}
                                            </a>
                                        @endforeach
                                        @if($tasmiEligibleClassrooms->count() > 5)
                                            <span class="self-center px-1 text-xs font-bold text-soft">+ {{ $tasmiEligibleClassrooms->count() - 5 }} kelas</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="mt-5 space-y-2">
                                <a href="{{ route('guru.tasmi.create') }}" class="group flex items-center justify-between rounded-xl bg-success-700 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-success-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                    Input tasmi&#039; baru
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </a>
                                <a href="{{ route('guru.tasmi.records') }}" class="flex items-center justify-between rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-success-line hover:bg-success-soft hover:text-success-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-700">
                                    Riwayat &amp; laporan tasmi&#039;
                                    <span class="text-soft">→</span>
                                </a>
                            </div>
                        </section>
                    @endif

                    @if($teacher)
                        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6 {{ $tahfidzHalaqahs->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="other-tasks-heading">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-warning-soft text-warning-ink">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 id="other-tasks-heading" class="text-lg font-semibold text-heading">Tugas lainnya</h3>
                                    <p class="mt-1 text-sm leading-5 text-muted">Akses jurnal pengganti dan riwayat perubahan jadwal.</p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                <a href="{{ route('guru.diniyyah-substitute-journals.index') }}" class="flex items-center justify-between rounded-xl border border-line bg-surface-subtle px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-warning-line hover:bg-warning-soft hover:text-warning-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-500">
                                    Jurnal guru pengganti
                                    <span class="text-soft">→</span>
                                </a>
                                <a href="{{ route('guru.jadwal.riwayat') }}" class="flex items-center justify-between rounded-xl border border-line bg-surface-subtle px-4 py-3 text-sm font-semibold text-body transition-colors hover:border-warning-line hover:bg-warning-soft hover:text-warning-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-500">
                                    Riwayat perubahan jadwal
                                    <span class="text-soft">→</span>
                                </a>
                            </div>
                        </section>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border-2 border-dashed border-line bg-surface p-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-surface-muted text-soft">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M4.93 19h14.14a2 2 0 0 0 1.73-3L13.73 4a2 2 0 0 0-3.46 0L3.2 16a2 2 0 0 0 1.73 3Z" /></svg>
                    </div>
                    <p class="mt-3 text-sm font-bold text-body">Belum ada penugasan mengajar aktif.</p>
                    <p class="mt-1 text-xs text-soft">Hubungi admin jika data penugasan Anda belum sesuai.</p>
                </div>
            @endif
        </section>

        {{-- Upcoming schedule / announcements --}}
        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="agenda-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-soft">Informasi sekolah</p>
                    <h2 id="agenda-heading" class="mt-1 text-xl font-semibold text-heading">Agenda terdekat</h2>
                    <p class="mt-1 text-sm text-muted">Ringkasan kegiatan dan libur yang relevan untuk Anda.</p>
                </div>
                <a href="{{ route('guru.calendar') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-body hover:text-warning-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-warning-500">
                    Buka kalender lengkap
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>

            @if($upcomingAlerts->isNotEmpty())
                <div class="mt-5 divide-y divide-line border-y border-line">
                    @foreach($upcomingAlerts as $alert)
                        <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                            <div class="flex min-w-0 items-start gap-3">
                                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $alert['kind'] === 'holiday' ? 'bg-warning-soft text-warning-ink' : (($alert['is_no_kbm'] ?? false) ? 'bg-info-soft text-info-ink' : 'bg-brand-soft text-brand-ink') }}">
                                    @if($alert['kind'] === 'holiday')
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg>
                                    @else
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 4v4l2.5 1.5" /></svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider {{ $alert['kind'] === 'holiday' ? 'text-warning-ink' : (($alert['is_no_kbm'] ?? false) ? 'text-info-ink' : 'text-brand-ink') }}">{{ $alert['kind_label'] }}</span>
                                        <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[10px] font-bold {{ $alert['countdown_label'] === 'Hari ini' ? 'text-danger-ink' : 'text-muted' }}">{{ $alert['countdown_label'] }}</span>
                                    </div>
                                    <h3 class="mt-1 truncate text-sm font-semibold text-heading sm:text-base">{{ $alert['title'] }}</h3>
                                    <p class="mt-0.5 truncate text-xs font-semibold text-muted">{{ $alert['date_label'] }}@if($alert['meta']) · {{ $alert['meta'] }}@endif</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-5 rounded-2xl border-2 border-dashed border-line bg-surface-subtle/70 p-8 text-center">
                    <p class="text-sm font-bold text-muted">Belum ada agenda sekolah terdekat.</p>
                    <p class="mt-1 text-xs font-medium text-soft">Agenda baru akan muncul di sini saat sudah dibagikan admin.</p>
                </div>
            @endif
        </section>
    </div>
</x-layouts.portal>
