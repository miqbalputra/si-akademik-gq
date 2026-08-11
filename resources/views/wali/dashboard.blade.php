<x-layouts.portal title="Dashboard Wali Santri" portalLabel="Portal Wali Santri" breadcrumb="Beranda">
    <div class="space-y-8">
        <header class="vantis-hero p-6 sm:p-8 lg:p-10">
            <div class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-amber-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Portal Wali Santri
                    </span>
                    <h1 class="mt-4 text-3xl font-black leading-tight tracking-tight sm:text-4xl">Ahlan wa sahlan, {{ $guardian?->name ?? auth()->user()->name }}</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">Pantau perkembangan ananda dari satu tempat: rapor, hafalan, presensi, dan agenda sekolah.</p>
                </div>
                <a href="{{ route('wali.tahfidz') }}" class="relative z-10 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 text-sm font-black text-amber-950 shadow-xl shadow-amber-900/20 transition hover:bg-amber-400">Buka progres Tahfidz <span aria-hidden="true">→</span></a>
            </div>
        </header>

        <section aria-label="Ringkasan akademik" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="metric-card"><div class="flex items-center justify-between"><p class="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Anak Terhubung</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600">◎</span></div><p class="mt-5 text-3xl font-black text-ink">{{ $students->count() }}</p><p class="mt-1 text-xs font-medium text-slate-500">Profil santri dalam akun Anda</p></div>
            <div class="metric-card border-emerald-100 bg-emerald-50/70"><div class="flex items-center justify-between"><p class="text-[10px] font-black uppercase tracking-[.14em] text-emerald-700">Rapor Terbit</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">✓</span></div><p class="mt-5 text-3xl font-black text-emerald-950">{{ $reportCards->count() }}</p><p class="mt-1 text-xs font-medium text-emerald-800/70">Siap dibaca dan diunduh</p></div>
            <div class="metric-card border-amber-100 bg-amber-50/70"><div class="flex items-center justify-between"><p class="text-[10px] font-black uppercase tracking-[.14em] text-amber-700">Perlu dipantau</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700">!</span></div><p class="mt-5 text-3xl font-black text-amber-950">{{ max($students->count() - $reportCardsByStudent->count(), 0) }}</p><p class="mt-1 text-xs font-medium text-amber-800/70">Anak belum memiliki rapor terbit</p></div>
        </section>

        @if (session('status'))
            <div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>
        @endif

        @include('partials.upcoming-school-alerts', [
            'upcomingAlerts' => $upcomingAlerts ?? collect(),
            'heading' => 'Pengingat 7 Hari ke Depan',
            'subheading' => 'Ringkasan libur sekolah dan agenda terdekat.',
        ])

        @include('partials.upcoming-school-events', [
            'schoolEvents' => $schoolEvents,
            'guardianEventResponses' => $guardianEventResponses ?? collect(),
            'heading' => 'Agenda Sekolah untuk Wali Santri',
            'subheading' => 'Informasi event yang dibagikan admin untuk wali santri dan keluarga.',
        ])

        <section id="rapor" aria-labelledby="children-heading">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-600">Perkembangan ananda</p><h2 id="children-heading" class="mt-1 text-2xl font-black tracking-tight text-ink">Anak Terhubung</h2></div><a href="{{ route('wali.tahfidz') }}" class="text-xs font-black text-amber-700 hover:text-amber-900">Lihat semua progres →</a></div>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($students as $student)
                    @php($latestReport = $reportCardsByStudent->get($student->id)?->sortByDesc('published_at')->first())
                    <article class="action-card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3"><div class="flex items-center gap-3"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-lg font-black text-white">{{ substr($student->name, 0, 1) }}</span><div><h3 class="text-lg font-black text-ink">{{ $student->name }}</h3><p class="mt-1 text-xs font-bold text-slate-400">NIS {{ $student->nis }}</p></div></div><span class="status-badge {{ $latestReport ? 'status-badge-success' : 'status-badge-neutral' }}">{{ $latestReport ? 'Ada rapor' : 'Belum ada' }}</span></div>
                        @if ($latestReport)
                            <div class="mt-6 grid grid-cols-3 gap-2"><div class="rounded-xl bg-slate-50 p-3 text-center"><p class="text-[9px] font-black uppercase text-slate-400">Rata-rata</p><p class="mt-1 text-lg font-black text-ink">{{ $latestReport->average_score ?? '-' }}</p></div><div class="rounded-xl bg-amber-50 p-3 text-center"><p class="text-[9px] font-black uppercase text-amber-700">Peringkat</p><p class="mt-1 text-lg font-black text-amber-800">#{{ $latestReport->rank_in_class ?? '-' }}</p></div><div class="rounded-xl bg-slate-50 p-3 text-center"><p class="text-[9px] font-black uppercase text-slate-400">Periode</p><p class="mt-1 text-lg font-black text-ink">{{ $latestReport->academicTerm?->semester ?? '-' }}</p></div></div>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2"><a href="{{ route('report-cards.show', $latestReport) }}" class="btn btn-primary min-h-11">Buka Rapor Terbaru <span aria-hidden="true">→</span></a><a href="{{ route('report-cards.download-pdf', $latestReport) }}" class="btn btn-outline min-h-11">Unduh PDF</a></div>
                        @else
                            <div class="empty-state mt-5 p-6"><p>Rapor anak ini belum dipublikasikan.</p><p class="mt-1 text-xs font-medium text-slate-400">Silakan cek kembali pada periode penerbitan berikutnya.</p></div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state md:col-span-2"><div class="text-3xl text-slate-300">◎</div><p>Belum ada data anak yang terhubung ke akun ini.</p><p class="mt-1 text-xs font-medium text-slate-400">Hubungi admin sekolah untuk menghubungkan profil santri.</p></div>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="history-heading">
            <div class="mb-4"><p class="text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Arsip akademik</p><h2 id="history-heading" class="mt-1 text-2xl font-black tracking-tight text-ink">Riwayat rapor</h2></div>
            <div class="space-y-3">
                @forelse ($reportCards as $reportCard)
                    <a href="{{ route('report-cards.show', $reportCard) }}" class="group flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-lg hover:shadow-slate-900/5 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">▤</span><div><h3 class="text-sm font-black text-ink group-hover:text-amber-700">{{ $reportCard->student?->name }}</h3><p class="mt-1 text-xs font-medium text-slate-500">{{ $reportCard->classroomTerm?->name }} · {{ $reportCard->academicTerm?->name }} {{ $reportCard->academicTerm?->academicYear?->name }}</p><p class="mt-1 text-[10px] font-bold text-slate-400">Diterbitkan {{ $reportCard->published_at?->locale('id')->translatedFormat('d M Y H:i') ?? '-' }}</p></div></div><div class="flex items-center gap-4 rounded-xl bg-slate-50 px-4 py-3"><div><p class="text-[9px] font-black uppercase text-slate-400">Rata-rata</p><p class="font-black text-ink">{{ $reportCard->average_score ?? '-' }}</p></div><div class="h-8 w-px bg-slate-200"></div><div><p class="text-[9px] font-black uppercase text-slate-400">Peringkat</p><p class="font-black text-amber-700">#{{ $reportCard->rank_in_class ?? '-' }}</p></div></div></a>
                @empty
                    <div class="empty-state"><p>Belum ada rapor yang dipublikasikan.</p><p class="mt-1 text-xs font-medium text-slate-400">Rapor akan muncul di sini setelah diterbitkan sekolah.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.portal>
