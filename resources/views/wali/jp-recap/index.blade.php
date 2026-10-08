@php
    $query = ['classroom_term_id' => $classroomTerm->id, 'month' => $month, 'year' => $year];
    $stats = $recap['stats'];
@endphp

<x-layouts.portal title="Rekap JP Kelas" portalLabel="Portal Guru" breadcrumb="Rekap JP Kelas">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber text-theme-xs font-medium">Rekap JP</span>
                    <h1 class="mt-3 text-on-primary ui-page-title">Rekap JP Kelas</h1>
                    <p class="mt-2 max-w-2xl text-theme-sm font-normal text-on-primary/80">Periksa JP terealisasi dari jurnal yang terisi serta kelengkapan jurnal guru sebelum mengirimkan rekap gaji.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('wali.jp-recap.export-pdf', $query) }}" class="btn min-h-11 border border-white/25 bg-surface/10 text-on-primary hover:bg-surface/20 text-theme-sm font-medium">Unduh PDF</a>
                    <a href="{{ route('wali.jp-recap.export-excel', $query) }}" class="btn min-h-11 border border-success-line bg-success-400 text-success-ink hover:bg-success-300 text-theme-sm font-medium">Unduh Excel</a>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="rounded-2xl border border-success-line bg-success-soft px-5 py-4 text-theme-sm font-medium text-success-ink">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-danger-line bg-danger-soft px-5 py-4 text-theme-sm font-medium text-danger-ink">{{ $errors->first() }}</div>
        @endif

        <section class="card-lg p-5 sm:p-6">
            <form method="GET" action="{{ route('wali.jp-recap.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label>
                    <span class="mb-1.5 block text-theme-xs font-normal text-body">Kelas</span>
                    <select name="classroom_term_id" class="form-input min-h-11 w-full text-theme-sm font-normal">
                        @foreach($classroomTerms as $term)
                            <option value="{{ $term->id }}" @selected($term->id === $classroomTerm->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-theme-xs font-normal text-body">Bulan</span>
                    <select name="month" class="form-input min-h-11 w-full text-theme-sm font-normal">
                        @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $number => $name)
                            <option value="{{ $number + 1 }}" @selected($month === $number + 1)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-theme-xs font-normal text-body">Tahun</span>
                    <select name="year" class="form-input min-h-11 w-full text-theme-sm font-normal">
                        @for($value = now('Asia/Jakarta')->year; $value >= now('Asia/Jakarta')->year - 2; $value--)
                            <option value="{{ $value }}" @selected($year === $value)>{{ $value }}</option>
                        @endfor
                    </select>
                </label>
                <div class="flex items-end"><button class="btn btn-primary min-h-11 w-full text-theme-sm font-medium" type="submit">Tampilkan Rekap</button></div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan rekap JP">
            <article class="metric-card"><p class="metric-label ui-metric-label">Guru ditampilkan</p><p class="metric-value">{{ $stats['total_teachers'] }}</p></article>
            <article class="metric-card border-gray-800 bg-gray-900"><p class="metric-label text-soft ui-metric-label">JP Terealisasi</p><p class="metric-value text-white">{{ $stats['jp_terealisasi'] }}</p></article>
            <article class="metric-card {{ $stats['missing_slots'] ? 'border-danger-line bg-danger-soft' : 'border-success-line bg-success-soft' }}"><p class="metric-label {{ $stats['missing_slots'] ? 'text-danger-ink' : 'text-success-ink' }} ui-metric-label">Jurnal kosong</p><p class="metric-value {{ $stats['missing_slots'] ? 'text-danger-ink' : 'text-success-ink' }}">{{ $stats['missing_slots'] }}</p></article>
            <article class="metric-card border-info-line bg-info-soft"><p class="metric-label text-info-ink ui-metric-label">Sudah diverifikasi</p><p class="metric-value text-info-ink">{{ $stats['confirmed_teachers'] }}</p></article>
        </section>

        <section class="card-lg overflow-hidden">
            <div class="border-b border-line bg-surface-subtle px-5 py-4 sm:px-6">
                <p class="text-theme-xs font-normal uppercase text-muted">{{ $classroomTerm->name }} · {{ $periodStart->translatedFormat('F Y') }}</p>
                <h2 class="mt-1 text-heading ui-card-title">Rekap JP Terealisasi per Guru</h2>
                <p class="mt-1 text-theme-xs font-normal text-muted">JP terealisasi hanya menghitung jurnal pada kelas yang dipilih. Jurnal kosong dan ceklist wali juga khusus kelas ini.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-left text-theme-sm">
                    <thead class="bg-surface text-theme-xs font-medium"><tr class="border-b border-line">
                        <th class="px-4 py-3 text-muted text-theme-xs font-medium">Guru &amp; Tugas</th>
                        <th class="px-4 py-3 text-right text-muted text-theme-xs font-medium">JP Terealisasi</th>
                        <th class="px-4 py-3 text-muted text-theme-xs font-medium">Ceklist wali kelas</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse($recap['teachers'] as $row)
                            @php($confirmation = $row['confirmation'])
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-medium text-heading">{{ $row['name'] }}</p>
                                    <p class="mt-1 text-theme-xs font-normal text-muted">{{ $row['niy'] ?: 'NIY belum tercatat' }} · {{ collect($row['subjects'])->implode(', ') ?: 'Guru pengganti / tanpa jadwal aktif' }}</p>
                                    @if($row['pengganti_dari'])<p class="mt-1 text-theme-xs font-normal text-brand-ink">JP pengganti dari: {{ collect($row['pengganti_dari'])->implode(', ') }}</p>@endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <p class="text-heading text-theme-sm font-medium">{{ $row['jp_terealisasi'] }}</p>
                                    <p class="mt-1 text-theme-xs font-normal text-muted">{{ $row['sesi_asli'] }} asli · {{ $row['sesi_pengganti'] }} ganti · {{ $row['sesi_tafsir'] }} tafsir</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if(in_array($confirmation['status'], ['lengkap', 'override'], true))
                                        <span class="status-badge {{ $confirmation['status'] === 'lengkap' ? 'status-badge-success' : 'border border-warning-line bg-warning-soft text-warning-ink' }} text-theme-xs font-medium">{{ $confirmation['label'] }}</span>
                                        @if(!empty($confirmation['reason']))<p class="mt-2 max-w-xs text-theme-xs font-normal text-warning-ink">{{ $confirmation['reason'] }}</p>@endif
                                    @elseif($confirmation['status'] === 'perlu_cek_ulang')
                                        <span class="status-badge status-badge-danger text-theme-xs font-medium">Perlu cek ulang</span>
                                        <p class="mt-2 max-w-xs text-theme-xs font-normal text-danger-ink">Data JP atau slot kosong berubah setelah ceklist sebelumnya.</p>
                                    @endif
                                    @if($row['missing_count'] > 0)
                                        <p class="mt-2 max-w-xs text-theme-xs font-normal text-danger-ink">Ada {{ $row['missing_count'] }} jurnal kosong. Rinciannya ada pada laporan terpisah di bawah.</p>
                                    @endif
                                    <form method="POST" action="{{ route('wali.jp-recap.confirm') }}" class="mt-3 space-y-2">
                                        @csrf
                                        <input type="hidden" name="classroom_term_id" value="{{ $classroomTerm->id }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="teacher_id" value="{{ $row['teacher_id'] }}">
                                        @if($row['missing_count'] === 0)
                                            <input type="hidden" name="mode" value="normal"><button class="btn min-h-10 border border-success-line bg-success-soft text-success-ink hover:bg-success-600 hover:text-white text-theme-sm font-medium" type="submit">✓ Tandai lengkap</button>
                                        @else
                                            <input type="hidden" name="mode" value="override"><label class="block text-body ui-form-label">Alasan override<textarea required name="override_reason" rows="2" class="form-input mt-1 w-full text-theme-sm font-normal" placeholder="Contoh: guru izin dan pengganti belum tersedia"></textarea></label><button class="btn min-h-10 border border-warning-line bg-warning-soft text-warning-ink hover:bg-warning-600 hover:text-white text-theme-sm font-medium" type="submit">Simpan override</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-10 text-center text-theme-sm font-medium text-muted">Belum ada tugas mengajar atau jurnal untuk kelas ini pada periode tersebut.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if(collect($recap['missing_journal_rows'] ?? [])->isNotEmpty())
            <section class="card-lg overflow-hidden border-danger-line" aria-labelledby="empty-journal-heading">
                <div class="border-b border-danger-line bg-danger-soft px-5 py-4 sm:px-6">
                    <p class="text-theme-xs font-normal uppercase text-danger-ink">Laporan terpisah</p>
                    <h2 id="empty-journal-heading" class="mt-1 text-heading ui-card-title">Daftar Jurnal Kosong</h2>
                    <p class="mt-1 text-theme-xs font-normal text-danger-ink">Slot berikut tidak termasuk JP terealisasi dan perlu ditindaklanjuti wali kelas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[900px] w-full text-left text-theme-sm">
                        <thead class="bg-surface text-theme-xs font-medium"><tr class="border-b border-line">
                            <th class="px-4 py-3 text-muted text-theme-xs font-medium">Guru</th>
                            <th class="px-4 py-3 text-muted text-theme-xs font-medium">Kelas &amp; Mapel</th>
                            <th class="px-4 py-3 text-muted text-theme-xs font-medium">Tanggal</th>
                            <th class="px-4 py-3 text-muted text-theme-xs font-medium">Sesi</th>
                            <th class="px-4 py-3 text-muted text-theme-xs font-medium">Jam</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach($recap['missing_journal_rows'] as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-heading">{{ $row['teacher_name'] }}<span class="block mt-0.5 text-theme-xs font-normal text-muted">{{ $row['niy'] ?: 'NIY belum tercatat' }}</span></td>
                                    <td class="px-4 py-3"><span class="font-medium text-heading">{{ $row['classroom_name'] }}</span><span class="block mt-0.5 text-theme-xs font-normal text-muted">{{ $row['subject_name'] }}</span></td>
                                    <td class="px-4 py-3">{{ $row['date_label'] }}</td>
                                    <td class="px-4 py-3">{{ $row['session_name'] }}</td>
                                    <td class="px-4 py-3">{{ $row['session_time'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.portal>
