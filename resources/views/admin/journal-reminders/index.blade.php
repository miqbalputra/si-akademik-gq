<x-layouts.portal title="Pengingat Jurnal KBM" portalLabel="Portal Kabag Diniyyah" breadcrumb="Pengingat Jurnal KBM">
    @php
        $query = [
            'academic_term_id' => $report['term']->id,
            'date_from' => $report['start']->toDateString(),
            'date_until' => $report['end']->toDateString(),
        ];
        $dateMaximum = min(now('Asia/Jakarta')->toDateString(), $report['term']->ends_at?->toDateString() ?? now('Asia/Jakarta')->toDateString());
    @endphp

    <section class="space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-danger-line bg-danger-soft p-4 text-theme-sm font-medium text-danger-ink">{{ $errors->first() }}</div>
        @endif

        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber text-theme-xs font-medium">Koordinasi Diniyyah</span>
                <h1 class="mt-3 text-on-primary ui-page-title">Pengingat Pengisian Jurnal KBM</h1>
                <p class="mt-2 max-w-3xl text-theme-sm font-normal text-on-primary/80">Bagikan rekap ini kepada guru yang masih memiliki jurnal KBM kosong. Libur, agenda tanpa KBM, serta izin dan sakit yang terverifikasi tidak dihitung.</p>
            </div>
        </header>

        <section class="card-lg p-5 sm:p-6">
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <label><span class="text-theme-xs font-normal text-body">Periode ajaran</span><select name="academic_term_id" class="form-input mt-1 bg-surface text-theme-sm font-normal">@foreach ($terms as $term)<option value="{{ $term->id }}" @selected($term->id === $report['term']->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>@endforeach</select></label>
                <label><span class="text-theme-xs font-normal text-body">Dari tanggal</span><input name="date_from" type="date" value="{{ $report['start']->toDateString() }}" min="{{ $report['term']->starts_at?->toDateString() }}" max="{{ $dateMaximum }}" class="form-input mt-1 bg-surface text-theme-sm font-normal"></label>
                <label><span class="text-theme-xs font-normal text-body">Sampai tanggal</span><input name="date_until" type="date" value="{{ $report['end']->toDateString() }}" min="{{ $report['term']->starts_at?->toDateString() }}" max="{{ $dateMaximum }}" class="form-input mt-1 bg-surface text-theme-sm font-normal"></label>
                <div class="flex items-end"><button class="btn btn-primary w-full text-theme-sm font-medium">Tampilkan laporan</button></div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2">
            <article class="metric-card border-danger-line bg-danger-soft"><p class="metric-label text-danger-ink ui-metric-label">Guru perlu diingatkan</p><p class="metric-value text-danger-ink">{{ $report['stats']['teachers_to_remind'] }}</p></article>
            <article class="metric-card border-warning-line bg-warning-soft"><p class="metric-label text-warning-ink ui-metric-label">Total jurnal kosong</p><p class="metric-value text-warning-ink">{{ $report['stats']['total_missing'] }}</p></article>
        </section>

        @if ($report['stats']['attendance_unverified_teachers'] > 0)
            <div class="rounded-2xl border border-warning-line bg-warning-soft p-4 text-theme-sm font-medium text-warning-ink">Catatan: data presensi untuk {{ $report['stats']['attendance_unverified_teachers'] }} guru belum dapat diverifikasi. Pastikan status izin atau sakitnya sebelum mengirim pengingat.</div>
        @endif

        <section class="card-lg overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-line p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><p class="text-theme-xs font-normal uppercase text-danger-ink">Siap dibagikan</p><h2 class="mt-1 text-heading ui-card-title">Rekap jurnal kosong</h2><p class="mt-1 text-theme-sm text-muted">{{ $report['start']->translatedFormat('d F Y') }} s.d. {{ $report['end']->translatedFormat('d F Y') }}</p></div>
                <div class="grid grid-cols-2 gap-2"><a href="{{ route('admin.journal-reminders.export', ['format' => 'png'] + $query) }}" class="btn min-h-11 border border-success-line bg-success-soft text-success-ink hover:bg-success-600 hover:text-white text-theme-sm font-medium">Unduh PNG Semua Guru</a><a href="{{ route('admin.journal-reminders.export', ['format' => 'pdf'] + $query) }}" class="btn min-h-11 border border-danger-line bg-danger-soft text-danger-ink hover:bg-danger-600 hover:text-white text-theme-sm font-medium">Unduh PDF</a></div>
            </div>

            @forelse ($report['teachers'] as $teacher)
                <article class="border-b border-line p-5 last:border-b-0 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="text-heading ui-card-title">{{ $teacher['teacher_name'] }}</h3><p class="text-theme-sm font-normal text-muted">NIY: {{ $teacher['niy'] ?: '-' }}</p></div><div class="flex flex-wrap items-center gap-2"><span class="badge bg-danger-soft-strong text-danger-ink text-theme-xs font-medium">{{ $teacher['missing_count'] }} jurnal kosong</span><a href="{{ route('admin.journal-reminders.export', ['format' => 'jpg', 'teacher_id' => $teacher['teacher_id']] + $query) }}" class="btn btn-sm border border-warning-line bg-warning-soft text-warning-ink hover:bg-warning-600 hover:text-white text-theme-sm font-medium">Unduh JPG</a><a href="{{ route('admin.journal-reminders.export', ['format' => 'png', 'teacher_id' => $teacher['teacher_id']] + $query) }}" class="btn btn-sm border border-success-line bg-success-soft text-success-ink hover:bg-success-600 hover:text-white text-theme-sm font-medium">Unduh PNG</a></div></div>
                    <div class="mt-4 overflow-x-auto"><table class="min-w-[720px] w-full text-left text-theme-sm"><thead class="bg-surface-subtle text-muted text-theme-xs font-medium"><tr><th class="px-3 py-3 text-theme-xs font-medium">Tanggal</th><th class="px-3 py-3 text-theme-xs font-medium">Sesi / Jam</th><th class="px-3 py-3 text-theme-xs font-medium">Kelas</th><th class="px-3 py-3 text-theme-xs font-medium">Mapel</th></tr></thead><tbody class="divide-y divide-line">@foreach ($teacher['rows'] as $row)<tr><td class="px-3 py-3 font-medium text-body">{{ $row['date_label'] }}</td><td class="px-3 py-3 text-body">{{ $row['session'] }}<span class="block text-theme-xs text-muted font-normal">{{ $row['session_time'] }}</span></td><td class="px-3 py-3 text-body">{{ implode(', ', $row['classes']) }}</td><td class="px-3 py-3 text-body">{{ implode(', ', $row['subjects']) }}</td></tr>@endforeach</tbody></table></div>
                </article>
            @empty
                <div class="p-10 text-center"><p class="text-lg font-semibold text-success-ink">Semua jurnal pada rentang ini sudah lengkap.</p><p class="mt-2 text-theme-sm font-normal text-muted">Anda tetap dapat mengunduh gambar atau PDF sebagai bukti rekap.</p></div>
            @endforelse
        </section>
    </section>
</x-layouts.portal>
