@php
    $query = ['classroom_term_id' => $classroomTerm->id, 'month' => $month, 'year' => $year];
    $stats = $recap['stats'];
@endphp

<x-layouts.portal title="Rekap JP Kelas" portalLabel="Portal Guru" breadcrumb="Rekap JP Kelas">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="badge badge-amber">Rekap Penggajian</span>
                    <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Rekap JP Kelas</h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">Periksa total JP dan kelengkapan jurnal guru sebelum mengirimkan rekap secara manual.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('wali.jp-recap.export-pdf', $query) }}" class="btn min-h-11 border border-white/25 bg-white/10 text-white hover:bg-white/20">Unduh PDF</a>
                    <a href="{{ route('wali.jp-recap.export-excel', $query) }}" class="btn min-h-11 border border-emerald-300 bg-emerald-400 text-emerald-950 hover:bg-emerald-300">Unduh Excel</a>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        <section class="card-lg p-5 sm:p-6">
            <form method="GET" action="{{ route('wali.jp-recap.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Kelas</span>
                    <select name="classroom_term_id" class="form-input min-h-11 w-full">
                        @foreach($classroomTerms as $term)
                            <option value="{{ $term->id }}" @selected($term->id === $classroomTerm->id)>{{ $term->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Bulan</span>
                    <select name="month" class="form-input min-h-11 w-full">
                        @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $number => $name)
                            <option value="{{ $number + 1 }}" @selected($month === $number + 1)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Tahun</span>
                    <select name="year" class="form-input min-h-11 w-full">
                        @for($value = now('Asia/Jakarta')->year; $value >= now('Asia/Jakarta')->year - 2; $value--)
                            <option value="{{ $value }}" @selected($year === $value)>{{ $value }}</option>
                        @endfor
                    </select>
                </label>
                <div class="flex items-end"><button class="btn btn-primary min-h-11 w-full" type="submit">Tampilkan Rekap</button></div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan rekap JP">
            <article class="metric-card"><p class="metric-label">Guru ditampilkan</p><p class="metric-value">{{ $stats['total_teachers'] }}</p></article>
            <article class="metric-card border-slate-800 bg-slate-900"><p class="metric-label text-slate-300">Total JP</p><p class="metric-value text-white">{{ $stats['total_jp'] }}</p></article>
            <article class="metric-card {{ $stats['missing_slots'] ? 'border-rose-300 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }}"><p class="metric-label {{ $stats['missing_slots'] ? 'text-rose-700' : 'text-emerald-700' }}">Jurnal kosong</p><p class="metric-value {{ $stats['missing_slots'] ? 'text-rose-800' : 'text-emerald-800' }}">{{ $stats['missing_slots'] }}</p></article>
            <article class="metric-card border-sky-200 bg-sky-50"><p class="metric-label text-sky-700">Sudah diverifikasi</p><p class="metric-value text-sky-800">{{ $stats['confirmed_teachers'] }}</p></article>
        </section>

        <section class="card-lg overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                <p class="text-xs font-black uppercase tracking-[.14em] text-slate-500">{{ $classroomTerm->name }} · {{ $periodStart->translatedFormat('F Y') }}</p>
                <h2 class="mt-1 text-lg font-black text-slate-900">Rekap JP per Guru</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">Status kosong hanya berasal dari slot jadwal yang perlu diisi. Tidak ada aksi untuk mengubah atau menghapus jurnal pada halaman ini.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1240px] w-full text-left text-sm">
                    <thead class="bg-white"><tr class="border-b border-slate-200">
                        <th class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Guru &amp; Tugas</th>
                        <th class="px-4 py-3 text-right text-xs font-black uppercase tracking-wider text-slate-500">JP</th>
                        <th class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Jurnal kosong</th>
                        <th class="px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-500">Ceklist wali kelas</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recap['teachers'] as $row)
                            @php($confirmation = $row['confirmation'])
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-900">{{ $row['name'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['niy'] ?: 'NIY belum tercatat' }} · {{ collect($row['subjects'])->implode(', ') ?: 'Guru pengganti / tanpa jadwal aktif' }}</p>
                                    @if($row['pengganti_dari'])<p class="mt-1 text-xs font-bold text-indigo-700">JP pengganti dari: {{ collect($row['pengganti_dari'])->implode(', ') }}</p>@endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <p class="text-2xl font-black text-slate-900">{{ $row['total_jp'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['sesi_asli'] }} asli · {{ $row['sesi_pengganti'] }} ganti · {{ $row['sesi_tafsir'] }} tafsir</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if($row['missing_count'] === 0)
                                        <span class="status-badge status-badge-success">Lengkap</span>
                                    @else
                                        <span class="status-badge status-badge-danger">{{ $row['missing_count'] }} slot kosong</span>
                                        <ul class="mt-2 space-y-1 text-xs font-semibold text-rose-800">@foreach($row['missing_slots'] as $slot)<li>{{ $slot['label'] }}</li>@endforeach</ul>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if(in_array($confirmation['status'], ['lengkap', 'override'], true))
                                        <span class="status-badge {{ $confirmation['status'] === 'lengkap' ? 'status-badge-success' : 'border border-amber-200 bg-amber-50 text-amber-800' }}">{{ $confirmation['label'] }}</span>
                                        @if(!empty($confirmation['reason']))<p class="mt-2 max-w-xs text-xs font-semibold text-amber-800">{{ $confirmation['reason'] }}</p>@endif
                                    @elseif($confirmation['status'] === 'perlu_cek_ulang')
                                        <span class="status-badge status-badge-danger">Perlu cek ulang</span>
                                        <p class="mt-2 max-w-xs text-xs font-semibold text-rose-800">Data JP atau slot kosong berubah setelah ceklist sebelumnya.</p>
                                    @endif
                                    <form method="POST" action="{{ route('wali.jp-recap.confirm') }}" class="mt-3 space-y-2">
                                        @csrf
                                        <input type="hidden" name="classroom_term_id" value="{{ $classroomTerm->id }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="teacher_id" value="{{ $row['teacher_id'] }}">
                                        @if($row['missing_count'] === 0)
                                            <input type="hidden" name="mode" value="normal"><button class="btn min-h-10 border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white" type="submit">✓ Tandai lengkap</button>
                                        @else
                                            <input type="hidden" name="mode" value="override"><label class="block text-xs font-bold text-slate-600">Alasan override<textarea required name="override_reason" rows="2" class="form-input mt-1 w-full" placeholder="Contoh: guru izin dan pengganti belum tersedia"></textarea></label><button class="btn min-h-10 border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white" type="submit">Simpan override</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">Belum ada tugas mengajar atau jurnal untuk kelas ini pada periode tersebut.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.portal>
