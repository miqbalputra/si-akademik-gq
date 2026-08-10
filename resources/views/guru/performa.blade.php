<x-layouts.portal title="Performa Saya" portalLabel="Portal Guru" breadcrumb="Performa Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.dashboard') }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Dashboard</span>
        </a>
    </x-slot>

    {{-- Header + pilih bulan --}}
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Performa Mengajar Saya</h1>
            <p class="text-sm text-slate-500">Rekap jurnal mengajar Diniyyah Anda bulan {{ $performa['month_label'] }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <form method="GET" action="{{ route('guru.performa') }}" class="flex items-end gap-2">
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Bulan</label>
                    <select name="month" class="form-input py-1.5 text-sm" onchange="var o=this.options[this.selectedIndex]; this.form.year.value=o.dataset.year; this.form.submit()">
                        @foreach($monthOptions as $opt)
                            <option value="{{ $opt['value']['month'] }}" data-year="{{ $opt['value']['year'] }}" @if((int) $performa['month'] === $opt['value']['month'] && (int) $performa['year'] === $opt['value']['year']) selected @endif>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="year" value="{{ $performa['year'] }}">
                <noscript>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </noscript>
            </form>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
                <p class="px-1 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Download laporan</p>
                <div class="flex gap-2">
                    <a href="{{ route('guru.performa.export', ['format' => 'xlsx', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50" aria-label="Download Excel performa {{ $performa['month_label'] }}">Excel</a>
                    <a href="{{ route('guru.performa.export', ['format' => 'pdf', 'month' => $performa['month'], 'year' => $performa['year']]) }}" class="btn btn-sm border border-rose-200 bg-white text-rose-700 hover:bg-rose-50" aria-label="Download PDF performa {{ $performa['month_label'] }}">PDF</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $stats = $performa['stats'];
        $emptySlots = $performa['empty_slots'];
        $grouped = collect($emptySlots)->groupBy('date');
    @endphp

    {{-- 3 stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Sudah Diisi</p>
            <p class="mt-1 text-4xl font-black text-emerald-700">{{ $stats['sudah_diisi'] }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-600">jam diisi jurnal Anda sendiri</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-rose-700">Kosong</p>
            <p class="mt-1 text-4xl font-black text-rose-700">{{ $stats['kosong'] }}</p>
            <p class="mt-1 text-xs font-semibold text-rose-600">jam belum diisi (tanggal lewat)</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Digantikan</p>
            <p class="mt-1 text-4xl font-black text-indigo-700">{{ $stats['digantikan'] }}</p>
            <p class="mt-1 text-xs font-semibold text-indigo-600">diisi guru pengganti</p>
        </div>
    </div>

    {{-- Daftar slot kosong --}}
    <div class="glass-card rounded-2xl p-6 border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-black text-slate-800">Slot Jurnal Kosong</h2>
            <span class="text-xs font-bold text-rose-700 bg-rose-100 px-3 py-1 rounded-full">{{ $stats['kosong'] }} slot</span>
        </div>

        @if($stats['total'] === 0)
            <p class="text-sm text-slate-500 italic text-center py-8">Anda belum memiliki jadwal mengajar Diniyyah yang sudah lewat pada bulan {{ $performa['month_label'] }}.</p>
        @elseif($emptySlots === [])
            <p class="text-sm text-slate-500 italic text-center py-8">Semua jurnal sudah terisi bulan {{ $performa['month_label'] }}. Kerja bagus!</p>
        @else
            <div class="space-y-5">
                @foreach($grouped as $date => $slots)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-slate-100">
                            <span class="text-sm font-black text-slate-800">{{ $slots[0]['date_label'] }}</span>
                            <span class="text-xs font-medium text-slate-400">· {{ count($slots) }} slot</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($slots as $slot)
                                @php
                                    $timeLabel = $slot['starts_at']
                                        ? \Carbon\Carbon::parse($slot['starts_at'])->format('H:i').' - '.\Carbon\Carbon::parse($slot['ends_at'])->format('H:i')
                                        : null;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white">
                                    <div class="flex items-center gap-3 sm:w-44 shrink-0">
                                        <div class="flex flex-col items-center justify-center bg-slate-100 rounded-lg px-2 py-1 min-w-[5rem] border border-slate-200 {{ $slot['is_tafsir'] ? 'bg-cyan-50 border-cyan-200' : '' }}">
                                            <span class="font-bold text-slate-800 text-sm">{{ $slot['session_label'] }}</span>
                                            @if($timeLabel)
                                                <span class="text-[10px] text-slate-500 whitespace-nowrap">{{ $timeLabel }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm font-bold text-slate-800">{{ $slot['subject_name'] }}</span>
                                            @if($slot['is_tafsir'])
                                                <span class="text-[10px] font-bold text-cyan-700 bg-cyan-100 px-2 py-0.5 rounded">Tafsir Serentak</span>
                                            @endif
                                        </div>
                                        <p class="text-xs font-medium text-slate-500 mt-0.5">Kelas: {{ $slot['classroom_names'] }}</p>
                                    </div>
                                    <div class="shrink-0">
                                        <a href="{{ $slot['fill_url'] }}" class="inline-flex items-center gap-1 rounded-lg {{ $slot['is_tafsir'] ? 'bg-cyan-600 hover:bg-cyan-700' : 'bg-teal-600 hover:bg-teal-700' }} px-3 py-2 text-xs font-bold text-white transition-colors">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                            {{ $slot['is_tafsir'] ? 'Isi Jurnal Tafsir' : 'Isi Jurnal' }}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.portal>
