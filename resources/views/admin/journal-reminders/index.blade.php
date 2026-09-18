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
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
        @endif

        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber">Koordinasi Diniyyah</span>
                <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Pengingat Pengisian Jurnal KBM</h1>
                <p class="mt-2 max-w-3xl text-sm font-medium text-slate-300">Bagikan rekap ini kepada guru yang masih memiliki jurnal KBM kosong. Libur, agenda tanpa KBM, serta izin dan sakit yang terverifikasi tidak dihitung.</p>
            </div>
        </header>

        <section class="card-lg p-5 sm:p-6">
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <label><span class="text-xs font-bold text-slate-600">Periode ajaran</span><select name="academic_term_id" class="form-input mt-1 bg-white">@foreach ($terms as $term)<option value="{{ $term->id }}" @selected($term->id === $report['term']->id)>{{ $term->academicYear?->name }} - {{ $term->name }}</option>@endforeach</select></label>
                <label><span class="text-xs font-bold text-slate-600">Dari tanggal</span><input name="date_from" type="date" value="{{ $report['start']->toDateString() }}" min="{{ $report['term']->starts_at?->toDateString() }}" max="{{ $dateMaximum }}" class="form-input mt-1 bg-white"></label>
                <label><span class="text-xs font-bold text-slate-600">Sampai tanggal</span><input name="date_until" type="date" value="{{ $report['end']->toDateString() }}" min="{{ $report['term']->starts_at?->toDateString() }}" max="{{ $dateMaximum }}" class="form-input mt-1 bg-white"></label>
                <div class="flex items-end"><button class="btn btn-primary w-full">Tampilkan laporan</button></div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2">
            <article class="metric-card border-rose-200 bg-rose-50"><p class="metric-label text-rose-700">Guru perlu diingatkan</p><p class="metric-value text-rose-900">{{ $report['stats']['teachers_to_remind'] }}</p></article>
            <article class="metric-card border-amber-200 bg-amber-50"><p class="metric-label text-amber-700">Total jurnal kosong</p><p class="metric-value text-amber-900">{{ $report['stats']['total_missing'] }}</p></article>
        </section>

        @if ($report['stats']['attendance_unverified_teachers'] > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">Catatan: data presensi untuk {{ $report['stats']['attendance_unverified_teachers'] }} guru belum dapat diverifikasi. Pastikan status izin atau sakitnya sebelum mengirim pengingat.</div>
        @endif

        <section class="card-lg overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><p class="text-xs font-black uppercase tracking-[.14em] text-rose-700">Siap dibagikan</p><h2 class="mt-1 text-lg font-black text-slate-900">Rekap jurnal kosong</h2><p class="mt-1 text-sm text-slate-500">{{ $report['start']->translatedFormat('d F Y') }} s.d. {{ $report['end']->translatedFormat('d F Y') }}</p></div>
                <div class="grid grid-cols-2 gap-2"><a href="{{ route('admin.journal-reminders.export', ['format' => 'png'] + $query) }}" class="btn min-h-11 border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white">Unduh Gambar</a><a href="{{ route('admin.journal-reminders.export', ['format' => 'pdf'] + $query) }}" class="btn min-h-11 border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white">Unduh PDF</a></div>
            </div>

            @forelse ($report['teachers'] as $teacher)
                <article class="border-b border-slate-100 p-5 last:border-b-0 sm:px-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="text-lg font-black text-slate-900">{{ $teacher['teacher_name'] }}</h3><p class="text-sm font-semibold text-slate-500">NIY: {{ $teacher['niy'] ?: '-' }}</p></div><span class="badge bg-rose-100 text-rose-800">{{ $teacher['missing_count'] }} jurnal kosong</span></div>
                    <div class="mt-4 overflow-x-auto"><table class="min-w-[720px] w-full text-left text-sm"><thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500"><tr><th class="px-3 py-3">Tanggal</th><th class="px-3 py-3">Sesi / Jam</th><th class="px-3 py-3">Kelas</th><th class="px-3 py-3">Mapel</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($teacher['rows'] as $row)<tr><td class="px-3 py-3 font-semibold text-slate-700">{{ $row['date_label'] }}</td><td class="px-3 py-3 text-slate-600">{{ $row['session'] }}<span class="block text-xs text-slate-500">{{ $row['session_time'] }}</span></td><td class="px-3 py-3 text-slate-700">{{ implode(', ', $row['classes']) }}</td><td class="px-3 py-3 text-slate-700">{{ implode(', ', $row['subjects']) }}</td></tr>@endforeach</tbody></table></div>
                </article>
            @empty
                <div class="p-10 text-center"><p class="text-lg font-black text-emerald-800">Semua jurnal pada rentang ini sudah lengkap.</p><p class="mt-2 text-sm font-semibold text-slate-500">Anda tetap dapat mengunduh gambar atau PDF sebagai bukti rekap.</p></div>
            @endforelse
        </section>
    </section>
</x-layouts.portal>
