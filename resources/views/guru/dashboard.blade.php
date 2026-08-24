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
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 font-mono text-[11px] font-black uppercase tracking-[.16em] text-neon">
                        <span class="h-1.5 w-1.5 rounded-full bg-neon"></span>
                        Papan Kegiatan Guru
                    </span>
                    <h1 class="mt-4 text-3xl font-black leading-tight tracking-tight sm:text-4xl">
                        Kelas hari ini, {{ $teacher->name ?? auth()->user()->name }}
                    </h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">
                        Mulai dari kegiatan yang perlu diselesaikan: catatan kelas, presensi, penilaian, dan agenda mengajar.
                    </p>
                </div>

                <div class="school-today-note w-full sm:w-auto sm:min-w-64">
                    <p class="font-mono text-[10px] font-black uppercase tracking-[.16em] text-slate-200">Hari ini</p>
                    <p class="mt-1 text-sm font-bold text-white">{{ $today->locale('id')->translatedFormat('l, d F Y') }}</p>
                    <a href="{{ route('guru.calendar') }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-black text-slate-900 transition-colors hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
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
                    <h2 id="quick-actions-heading" class="mt-2 text-xl font-black text-slate-900">Papan Kegiatan</h2>
                </div>
                <p class="text-sm font-medium text-slate-500">Pilih catatan atau agenda yang ingin Anda selesaikan.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ $singleJournalLink }}" class="group rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">✎</span>
                    <span class="mt-3 block text-sm font-black text-slate-900">Isi jurnal Diniyyah</span>
                    <span class="mt-1 block text-xs font-medium text-slate-500">Catat materi dan kehadiran kelas.</span>
                </a>
                <a href="{{ route('guru.performa', ['month' => $performaMonth, 'year' => $performaYear]) }}" class="group rounded-2xl border border-amber-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-700" aria-hidden="true">▥</span>
                    <span class="mt-3 block text-sm font-black text-slate-900">Lihat performa</span>
                    <span class="mt-1 block text-xs font-medium text-slate-500">Cek jurnal kosong dan download laporan.</span>
                </a>
                @if($teacher)
                    <a href="{{ route('guru.attendance-report.index') }}" class="group rounded-2xl border border-sky-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-700" aria-hidden="true">✓</span>
                        <span class="mt-3 block text-sm font-black text-slate-900">Presensi saya</span>
                        <span class="mt-1 block text-xs font-medium text-slate-500">Lihat rekap GeoPresensi dan unduh laporan.</span>
                    </a>
                @endif
                <a href="{{ route('guru.calendar') }}" class="group rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700" aria-hidden="true">◷</span>
                    <span class="mt-3 block text-sm font-black text-slate-900">Buka kalender</span>
                    <span class="mt-1 block text-xs font-medium text-slate-500">Lihat jadwal dan agenda terdekat.</span>
                </a>
            </div>
        </section>

        {{-- Highest-priority task --}}
        @if($performa !== null)
            <section class="rounded-[1.75rem] border {{ $performa['stats']['kosong'] > 0 ? 'border-rose-200 bg-rose-50/70' : 'border-emerald-200 bg-emerald-50/70' }} p-5 shadow-sm sm:p-6" aria-labelledby="performa-heading">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $performa['stats']['kosong'] > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2Zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m0 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v14Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[.16em] {{ $performa['stats']['kosong'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Performa mengajar</p>
                            <h2 id="performa-heading" class="mt-1 text-lg font-black text-slate-900">Performa Mengajar Saya <span class="font-semibold text-slate-500">— Jurnal {{ $performa['month_label'] }}</span></h2>
                            <p class="mt-1 text-sm text-slate-600">
                                @if($performa['stats']['kosong'] > 0)
                                    Ada jurnal yang perlu dilengkapi agar rekap mengajar tetap rapi.
                                @else
                                    Semua jurnal yang sudah lewat pada bulan ini telah tercatat.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-5 rounded-2xl bg-white/70 px-4 py-3 lg:min-w-64 lg:justify-center">
                        <div>
                            <p class="text-2xl font-black {{ $performa['stats']['kosong'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $performa['stats']['kosong'] }}</p>
                            <p class="text-[11px] font-bold text-slate-500">slot belum diisi</p>
                        </div>
                        <div class="h-9 w-px bg-slate-200"></div>
                        <div>
                            <p class="text-2xl font-black text-slate-800">{{ $performa['stats']['sudah_diisi'] }}</p>
                            <p class="text-[11px] font-bold text-slate-500">slot selesai</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 border-t border-black/5 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <form method="GET" action="{{ route('guru.dashboard') }}" class="flex items-center gap-2">
                        <label for="performa-month" class="text-xs font-bold text-slate-600">Tampilkan bulan</label>
                        <select id="performa-month" name="month" class="form-input w-auto min-w-36 bg-white py-2 text-xs" onchange="var o=this.options[this.selectedIndex]; this.form.year.value=o.dataset.year; this.form.submit()">
                            @foreach($performaMonthOptions as $opt)
                                <option value="{{ $opt['value']['month'] }}" data-year="{{ $opt['value']['year'] }}" @if((int) $performaMonth === $opt['value']['month'] && (int) $performaYear === $opt['value']['year']) selected @endif>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="year" value="{{ $performaYear }}">
                        <noscript><button type="submit" class="btn btn-sm">Tampilkan</button></noscript>
                    </form>
                    <a href="{{ route('guru.performa', ['month' => $performaMonth, 'year' => $performaYear]) }}" class="inline-flex items-center gap-2 text-sm font-black {{ $performa['stats']['kosong'] > 0 ? 'text-rose-700 hover:text-rose-800' : 'text-emerald-700 hover:text-emerald-800' }} focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                        {{ $performa['stats']['kosong'] > 0 ? 'Isi jurnal yang kosong' : 'Lihat detail performa' }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </section>
        @endif

        {{-- Role-based entry points --}}
        <section aria-labelledby="task-heading">
            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[.16em] text-amber-600">Akses utama</p>
                    <h2 id="task-heading" class="mt-1 text-2xl font-black tracking-tight text-slate-900">Tugas dan kelas Anda</h2>
                </div>
                <p class="text-sm font-medium text-slate-500">Pilih area kerja sesuai peran mengajar Anda.</p>
            </div>

            @if($hasTeachingTasks)
                <div class="grid gap-5 lg:grid-cols-12">
                    @if($homeroomClassroomTerms->isNotEmpty())
                        <section class="rounded-[1.75rem] border border-blue-100 bg-white p-5 shadow-sm sm:p-6 {{ $diniyyahAssignments->isNotEmpty() || $diniyyahAssessmentSets->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="homeroom-heading">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a6 6 0 0 0-9-5.197M9 20H4v-1a6 6 0 0 1 12 0v1m-3-9a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm6-3a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="homeroom-heading" class="text-lg font-black text-slate-900">Wali Kelas</h3>
                                            <span class="badge badge-blue">{{ $homeroomClassroomTerms->count() }} kelas</span>
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-slate-500">Kelola presensi harian dan pantau jurnal kelas yang Anda dampingi.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                @foreach($homeroomClassroomTerms->take(4) as $term)
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $term->name }}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold text-slate-400">{{ $term->academicTerm->name ?? 'Periode aktif' }}</p>
                                    </div>
                                @endforeach
                                @if($homeroomClassroomTerms->count() > 4)
                                    <p class="self-center px-1 text-xs font-bold text-slate-400">+ {{ $homeroomClassroomTerms->count() - 4 }} kelas lainnya</p>
                                @endif
                            </div>

                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                <a href="{{ route('attendance.index') }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-3 text-sm font-black text-white shadow-sm transition-colors hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                                    Input presensi
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                                <a href="{{ route('wali.diniyyah-journals.index') }}" class="inline-flex flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                                    Pantau jurnal kelas
                                </a>
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('guru.tasmi-wali.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    <span class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                        Tasmi&#039; Kelas Saya <span class="text-[10px] font-bold text-slate-400">(read-only)</span>
                                    </span>
                                    <span class="text-slate-400">→</span>
                                </a>
                            </div>
                        </section>
                    @endif

                    @if($diniyyahAssessmentSets->isNotEmpty() || $diniyyahAssignments->isNotEmpty())
                        <section class="rounded-[1.75rem] border border-emerald-100 bg-white p-5 shadow-sm sm:p-6 {{ $homeroomClassroomTerms->isNotEmpty() ? 'lg:col-span-5' : 'lg:col-span-12' }}" aria-labelledby="diniyyah-heading">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a3 3 0 0 1 6 0M9 5a3 3 0 0 0 6 0m-6 7h6m-6 4h4" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 id="diniyyah-heading" class="text-lg font-black text-slate-900">Guru Diniyyah</h3>
                                        <span class="badge badge-green">{{ $diniyyahAssignments->count() }} penugasan</span>
                                        @if($diniyyahAssignments->isNotEmpty())
                                            <span class="badge badge-indigo">Jadwal Mengajar</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm leading-5 text-slate-500">Input nilai dan jurnal untuk mapel Diniyyah yang Anda ampu.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                    <p class="text-2xl font-black text-emerald-700">{{ $diniyyahAssessmentSets->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-emerald-800">tugas nilai aktif</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-2xl font-black text-slate-800">{{ $diniyyahClasses->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-slate-500">kelas diajar</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-2">
                                <a href="{{ route('guru.diniyyah-scores.index') }}" class="group flex items-center justify-between rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    Input nilai Diniyyah
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                                <a href="{{ $singleJournalLink }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    Jurnal kelas
                                    <span class="text-slate-400">→</span>
                                </a>
                                @if($hasTafsirAssignment ?? false)
                                    <a href="{{ route('guru.diniyyah-tafsir-journals.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                        Jurnal Tafsir
                                        <span class="text-slate-400">→</span>
                                    </a>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if($tahfidzHalaqahs->isNotEmpty())
                        <section class="rounded-[1.75rem] border border-violet-100 bg-white p-5 shadow-sm sm:p-6 lg:col-span-5" aria-labelledby="tahfidz-heading">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13c-1.168-.776-2.754-1.253-4.5-1.253-1.746 0-3.332.477-4.5 1.253m0-13v13" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="tahfidz-heading" class="text-lg font-black text-slate-900">Guru Tahfidz</h3>
                                            <span class="badge badge-purple">{{ $tahfidzHalaqahs->count() }} halaqah</span>
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-slate-500">Catat setoran hafalan santri pada halaqah Anda.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 space-y-2">
                                @foreach($tahfidzHalaqahs->take(3) as $halaqah)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                                        <p class="text-sm font-bold text-slate-800">{{ $halaqah->name ?: 'Halaqah Tahfidz' }}</p>
                                        <span class="text-[11px] font-bold text-slate-400">{{ $halaqah->activeMembers->count() }} santri</span>
                                    </div>
                                @endforeach
                                @if($tahfidzHalaqahs->count() > 3)
                                    <p class="px-1 text-xs font-bold text-slate-400">+ {{ $tahfidzHalaqahs->count() - 3 }} halaqah lainnya</p>
                                @endif
                            </div>

                            <a href="{{ route('guru.tahfidz.index') }}" class="mt-5 inline-flex w-full items-center justify-between rounded-xl bg-violet-700 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-violet-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-700">
                                Buka jurnal Tahfidz
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </section>
                    @endif

                    @if($tasmiExaminerAssignment ?? null)
                        <section class="rounded-[1.75rem] border border-emerald-100 bg-white p-5 shadow-sm sm:p-6 {{ $tahfidzHalaqahs->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="tasmi-heading">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="tasmi-heading" class="text-lg font-black text-slate-900">PJ Tasmi&#039;</h3>
                                            <span class="badge badge-green">{{ $tasmiEligibleClassrooms->count() }} kelas</span>
                                            @if($tasmiGenderScope === 'male')
                                                <span class="badge badge-blue">Ikhwan</span>
                                            @elseif($tasmiGenderScope === 'female')
                                                <span class="badge badge-pink" style="background:#fce7f3;color:#9f1239;">Akwat</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-slate-500">Catat setoran ujian tasmi&#039; santri (1 juz / 5 juz).</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                    <p class="text-2xl font-black text-emerald-700">{{ $tasmiRecordsCount }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-emerald-800">record tasmi&#039;</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-2xl font-black text-slate-800">{{ $tasmiEligibleClassrooms->count() }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold text-slate-500">kelas bisa diuji</p>
                                </div>
                            </div>

                            @if($tasmiEligibleClassrooms->isNotEmpty())
                                <div class="mt-5 space-y-2">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($tasmiEligibleClassrooms->take(5) as $ct)
                                            <a href="{{ route('guru.tasmi.create', ['classroom_term_id' => $ct->id]) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800">
                                                {{ $ct->classroom->name ?? $ct->name }}
                                            </a>
                                        @endforeach
                                        @if($tasmiEligibleClassrooms->count() > 5)
                                            <span class="self-center px-1 text-xs font-bold text-slate-400">+ {{ $tasmiEligibleClassrooms->count() - 5 }} kelas</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="mt-5 space-y-2">
                                <a href="{{ route('guru.tasmi.create') }}" class="group flex items-center justify-between rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white transition-colors hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    Input tasmi&#039; baru
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </a>
                                <a href="{{ route('guru.tasmi.records') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    Riwayat &amp; laporan tasmi&#039;
                                    <span class="text-slate-400">→</span>
                                </a>
                            </div>
                        </section>
                    @endif

                    @if($teacher)
                        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 {{ $tahfidzHalaqahs->isNotEmpty() ? 'lg:col-span-7' : 'lg:col-span-12' }}" aria-labelledby="other-tasks-heading">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 id="other-tasks-heading" class="text-lg font-black text-slate-900">Tugas lainnya</h3>
                                    <p class="mt-1 text-sm leading-5 text-slate-500">Akses jurnal pengganti dan riwayat perubahan jadwal.</p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                <a href="{{ route('guru.diniyyah-substitute-journals.index') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-amber-200 hover:bg-amber-50 hover:text-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                                    Jurnal guru pengganti
                                    <span class="text-slate-400">→</span>
                                </a>
                                <a href="{{ route('guru.jadwal.riwayat') }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-700 transition-colors hover:border-amber-200 hover:bg-amber-50 hover:text-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                                    Riwayat perubahan jadwal
                                    <span class="text-slate-400">→</span>
                                </a>
                            </div>
                        </section>
                    @endif
                </div>
            @else
                <div class="rounded-[1.75rem] border-2 border-dashed border-slate-200 bg-white p-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M4.93 19h14.14a2 2 0 0 0 1.73-3L13.73 4a2 2 0 0 0-3.46 0L3.2 16a2 2 0 0 0 1.73 3Z" /></svg>
                    </div>
                    <p class="mt-3 text-sm font-bold text-slate-600">Belum ada penugasan mengajar aktif.</p>
                    <p class="mt-1 text-xs text-slate-400">Hubungi admin jika data penugasan Anda belum sesuai.</p>
                </div>
            @endif
        </section>

        {{-- Upcoming schedule / announcements --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="agenda-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[.16em] text-slate-400">Informasi sekolah</p>
                    <h2 id="agenda-heading" class="mt-1 text-xl font-black text-slate-900">Agenda terdekat</h2>
                    <p class="mt-1 text-sm text-slate-500">Ringkasan kegiatan dan libur yang relevan untuk Anda.</p>
                </div>
                <a href="{{ route('guru.calendar') }}" class="inline-flex items-center gap-2 text-sm font-black text-slate-700 hover:text-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                    Buka kalender lengkap
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>

            @if($upcomingAlerts->isNotEmpty())
                <div class="mt-5 divide-y divide-slate-100 border-y border-slate-100">
                    @foreach($upcomingAlerts as $alert)
                        <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                            <div class="flex min-w-0 items-start gap-3">
                                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $alert['kind'] === 'holiday' ? 'bg-amber-50 text-amber-700' : (($alert['is_no_kbm'] ?? false) ? 'bg-sky-50 text-sky-700' : 'bg-indigo-50 text-indigo-700') }}">
                                    @if($alert['kind'] === 'holiday')
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg>
                                    @else
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 4v4l2.5 1.5" /></svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider {{ $alert['kind'] === 'holiday' ? 'text-amber-700' : (($alert['is_no_kbm'] ?? false) ? 'text-sky-700' : 'text-indigo-700') }}">{{ $alert['kind_label'] }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold {{ $alert['countdown_label'] === 'Hari ini' ? 'text-rose-700' : 'text-slate-500' }}">{{ $alert['countdown_label'] }}</span>
                                    </div>
                                    <h3 class="mt-1 truncate text-sm font-black text-slate-900 sm:text-base">{{ $alert['title'] }}</h3>
                                    <p class="mt-0.5 truncate text-xs font-semibold text-slate-500">{{ $alert['date_label'] }}@if($alert['meta']) · {{ $alert['meta'] }}@endif</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-5 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/70 p-8 text-center">
                    <p class="text-sm font-bold text-slate-500">Belum ada agenda sekolah terdekat.</p>
                    <p class="mt-1 text-xs font-medium text-slate-400">Agenda baru akan muncul di sini saat sudah dibagikan admin.</p>
                </div>
            @endif
        </section>
    </div>
</x-layouts.portal>
