<x-layouts.portal title="Presensi Kelas" portalLabel="Portal Guru" breadcrumb="Presensi Kelas">
    {{-- Header --}}
    <header class="fade-up mb-8">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 rounded-full px-3 py-1 mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Presensi Harian</span>
                </div>
                <h1 class="text-3xl font-black text-slate-900 mb-2 tracking-tight">Presensi Kelas</h1>
                <p class="text-sm font-medium text-slate-500">Pilih kelas dan bulan untuk menginput kehadiran santri (H / S / I / A / L).</p>
            </div>
            
            <a href="{{ route('guru.dashboard') }}" class="text-sm font-bold text-slate-500 hover:text-indigo-600 bg-white border border-slate-200 px-4 py-2 rounded-xl text-center shadow-sm transition-all hover:shadow-md">
                Ke Dashboard
            </a>
        </div>
    </header>

    {{-- Filter --}}
    <div class="fade-up delay-1 mb-8">
        <form method="GET" class="glass-card p-5 rounded-3xl flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bulan Input</label>
                <input type="month" name="month" value="{{ $selectedMonth }}" class="rounded-xl border-slate-200 bg-white px-4 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 shadow-sm w-48">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-xl text-sm transition-colors shadow-sm">Terapkan</button>
        </form>
    </div>

    @include('partials.upcoming-school-events', [
        'schoolEvents'          => $schoolEvents ?? collect(),
        'heading'               => 'Agenda Sekolah untuk Guru',
        'subheading'            => 'Outdoor, ujian, atau event sekolah yang ditetapkan admin.',
    ])

    {{-- Classes Grid --}}
    <div class="fade-up delay-2 mt-8">
        <div class="flex items-center gap-4 mb-6">
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Daftar Kelas Anda</h2>
            <div class="h-px bg-slate-200 flex-grow"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($classroomTerms as $classroomTerm)
                <article class="glass-card bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-full blur-3xl -mr-10 -mt-10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-start justify-between gap-3 mb-5">
                            <div>
                                <h2 class="text-lg font-black text-slate-900 mb-1 leading-tight">{{ $classroomTerm->name }}</h2>
                                <p class="text-xs font-semibold text-slate-500">
                                    {{ $classroomTerm->academicTerm?->academicYear?->name }} &middot; {{ $classroomTerm->academicTerm?->name }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-wider whitespace-nowrap">
                                {{ $classroomTerm->enrollments_count }} Santri
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Wali Kelas</p>
                                <p class="text-xs font-bold text-slate-700 leading-tight">
                                    {{ $classroomTerm->homeroomAssignments->pluck('teacher.name')->filter()->implode(', ') ?: '—' }}
                                </p>
                            </div>
                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Status</p>
                                @if($classroomTerm->status === 'active')
                                    <p class="text-xs font-bold text-emerald-600 leading-tight flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        Aktif
                                    </p>
                                @else
                                    <p class="text-xs font-bold text-slate-600 leading-tight capitalize">{{ $classroomTerm->status }}</p>
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => $selectedMonth]) }}" class="w-full inline-flex items-center justify-center gap-2 bg-indigo-50 hover:bg-indigo-600 text-indigo-600 hover:text-white font-bold py-3 px-4 rounded-2xl text-sm transition-colors group/btn">
                            Buka Presensi
                            <svg class="w-4 h-4 transform group-hover/btn:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    </div>
                </article>
            @empty
                <div class="col-span-full border-2 border-dashed border-slate-200 rounded-3xl p-12 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                    </div>
                    <p class="text-slate-500 font-bold text-lg mb-1">Belum Ada Kelas</p>
                    <p class="text-slate-400 text-sm">Tidak ada kelas yang ditugaskan kepada Anda saat ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.portal>
