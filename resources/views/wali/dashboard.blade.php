<x-layouts.portal title="Dashboard Wali Santri" portalLabel="Portal Wali Santri" breadcrumb="Beranda">
    <div class="space-y-8">
        <header class="school-dashboard-hero p-6 sm:p-8 lg:p-10">
            <div class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface/10 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[.16em] text-warning-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-success-400"></span>
                        Ruang Perkembangan Anak
                    </span>
                    <h1 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Kabar belajar untuk keluarga, {{ $guardian?->name ?? auth()->user()->name }}</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-on-primary/80 sm:text-base">Ikuti perkembangan ananda melalui catatan sekolah, hafalan, agenda, dan arsip rapor yang sudah diterbitkan.</p>
                </div>
                <a href="{{ route('wali.tahfidz') }}" class="btn btn-primary relative z-10 min-h-12 px-5">Buka progres Tahfidz <span aria-hidden="true">&rarr;</span></a>
            </div>
        </header>

        <section aria-label="Ringkasan akademik" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="metric-card"><div class="flex items-center justify-between"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-soft">Anak Terhubung</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-muted text-body">◎</span></div><p class="mt-5 text-3xl font-semibold text-ink">{{ $students->count() }}</p><p class="mt-1 text-xs font-medium text-muted">Profil santri dalam akun Anda</p></div>
            <div class="metric-card border-success-line bg-success-soft/70"><div class="flex items-center justify-between"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-success-ink">Rapor Terbit</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-success-soft-strong text-success-ink">✓</span></div><p class="mt-5 text-3xl font-semibold text-success-ink">{{ $reportCards->count() }}</p><p class="mt-1 text-xs font-medium text-success-ink/70">Siap dibaca dan diunduh</p></div>
            <div class="metric-card border-warning-line bg-warning-soft/70"><div class="flex items-center justify-between"><p class="text-[10px] font-semibold uppercase tracking-[.14em] text-warning-ink">Perlu dipantau</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning-soft-strong text-warning-ink">!</span></div><p class="mt-5 text-3xl font-semibold text-warning-ink">{{ max($students->count() - $reportCardsByStudent->count(), 0) }}</p><p class="mt-1 text-xs font-medium text-warning-ink/70">Anak belum memiliki rapor terbit</p></div>
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
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p class="school-index">Papan Perkembangan</p><h2 id="children-heading" class="mt-2 text-2xl font-semibold tracking-tight text-ink">Anak Terhubung</h2></div><a href="{{ route('wali.tahfidz') }}" class="text-xs font-semibold text-school-600 hover:text-school-800">Lihat semua progres <span aria-hidden="true">&rarr;</span></a></div>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($students as $student)
                    @php($latestReport = $reportCardsByStudent->get($student->id)?->sortByDesc('published_at')->first())
                    <article class="action-card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3"><div class="flex items-center gap-3"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-950 text-lg font-semibold text-white">{{ substr($student->name, 0, 1) }}</span><div><h3 class="text-lg font-semibold text-ink">{{ $student->name }}</h3><p class="mt-1 text-xs font-bold text-soft">NIS {{ $student->nis }}</p></div></div><span class="status-badge {{ $latestReport ? 'status-badge-success' : 'status-badge-neutral' }}">{{ $latestReport ? 'Ada rapor' : 'Belum ada' }}</span></div>
                        @if ($latestReport)
                            <div class="mt-6 grid grid-cols-3 gap-2"><div class="rounded-xl bg-surface-subtle p-3 text-center"><p class="text-[9px] font-semibold uppercase text-soft">Rata-rata</p><p class="mt-1 text-lg font-semibold text-ink">{{ $latestReport->average_score ?? '-' }}</p></div><div class="rounded-xl bg-warning-soft p-3 text-center"><p class="text-[9px] font-semibold uppercase text-warning-ink">Peringkat</p><p class="mt-1 text-lg font-semibold text-warning-ink">#{{ $latestReport->rank_in_class ?? '-' }}</p></div><div class="rounded-xl bg-surface-subtle p-3 text-center"><p class="text-[9px] font-semibold uppercase text-soft">Periode</p><p class="mt-1 text-lg font-semibold text-ink">{{ $latestReport->academicTerm?->semester ?? '-' }}</p></div></div>
                            <div class="mt-5 grid gap-2 sm:grid-cols-2"><a href="{{ route('report-cards.show', $latestReport) }}" class="btn btn-primary min-h-11">Buka Rapor Terbaru <span aria-hidden="true">→</span></a><a href="{{ route('report-cards.download-pdf', $latestReport) }}" class="btn btn-outline min-h-11">Unduh PDF</a></div>
                        @else
                            <div class="empty-state mt-5 p-6"><p>Rapor anak ini belum dipublikasikan.</p><p class="mt-1 text-xs font-medium text-soft">Silakan cek kembali pada periode penerbitan berikutnya.</p></div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state md:col-span-2"><div class="text-3xl text-soft">◎</div><p>Belum ada data anak yang terhubung ke akun ini.</p><p class="mt-1 text-xs font-medium text-soft">Hubungi admin sekolah untuk menghubungkan profil santri.</p></div>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="history-heading">
            <div class="mb-4"><p class="school-index">Arsip Rapor</p><h2 id="history-heading" class="mt-2 text-2xl font-semibold tracking-tight text-ink">Riwayat rapor</h2></div>
            <div class="space-y-3">
                @forelse ($reportCards as $reportCard)
                    <a href="{{ route('report-cards.show', $reportCard) }}" class="group flex flex-col gap-4 rounded-2xl border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-warning-line hover:shadow-lg hover:shadow-gray-900/5 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success-soft text-success-ink">▤</span><div><h3 class="text-sm font-semibold text-ink group-hover:text-warning-ink">{{ $reportCard->student?->name }}</h3><p class="mt-1 text-xs font-medium text-muted">{{ $reportCard->classroomTerm?->name }} · {{ $reportCard->academicTerm?->name }} {{ $reportCard->academicTerm?->academicYear?->name }}</p><p class="mt-1 text-[10px] font-bold text-soft">Diterbitkan {{ $reportCard->published_at?->locale('id')->translatedFormat('d M Y H:i') ?? '-' }}</p></div></div><div class="flex items-center gap-4 rounded-xl bg-surface-subtle px-4 py-3"><div><p class="text-[9px] font-semibold uppercase text-soft">Rata-rata</p><p class="font-semibold text-ink">{{ $reportCard->average_score ?? '-' }}</p></div><div class="h-8 w-px bg-surface-muted"></div><div><p class="text-[9px] font-semibold uppercase text-soft">Peringkat</p><p class="font-semibold text-warning-ink">#{{ $reportCard->rank_in_class ?? '-' }}</p></div></div></a>
                @empty
                    <div class="empty-state"><p>Belum ada rapor yang dipublikasikan.</p><p class="mt-1 text-xs font-medium text-soft">Rapor akan muncul di sini setelah diterbitkan sekolah.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.portal>
