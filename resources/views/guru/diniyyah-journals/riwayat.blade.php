<x-layouts.portal title="Riwayat Jurnal Saya" portalLabel="Portal Guru" breadcrumb="Riwayat Jurnal Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.report') }}" class="btn btn-outline btn-sm">Laporan &amp; Download</a>
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="btn btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Input Jurnal</span>
        </a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Riwayat Jurnal Saya</h1>
            <p class="text-sm text-slate-500">Seluruh jurnal yang sudah Anda isi di semua kelas.</p>
        </div>
        <span class="text-sm font-bold text-amber-700 bg-amber-100 px-3 py-1 rounded-full whitespace-nowrap">
            {{ $myJournals->count() }} jurnal
        </span>
    </div>

    <div class="glass-card rounded-2xl p-6 border border-slate-200">
        @if($myJournals->isEmpty())
            <p class="text-sm text-slate-500 italic text-center py-8">Belum ada jurnal yang Anda isi.</p>
        @else
            @php
                $grouped = $myJournals->groupBy(fn ($j) => $j->date->format('Y-m-d'));
            @endphp
            <div class="space-y-5">
                @foreach($grouped as $date => $journals)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-slate-100">
                            <span class="text-sm font-black text-slate-800">
                                {{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') }}
                            </span>
                            <span class="text-xs font-medium text-slate-400">· {{ $journals->count() }} jurnal</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($journals as $journal)
                                @php
                                    $slotStart = $journal->session_starts_at;
                                    $slotEnd = $journal->session_ends_at;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white">
                                    <div class="flex items-center gap-3 sm:w-40 shrink-0">
                                        <div class="flex flex-col items-center justify-center bg-slate-100 rounded-lg px-2 py-1 min-w-[4rem] border border-slate-200">
                                            <span class="font-bold text-slate-800 text-sm">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : 'Sesi '.$journal->session_hour }}</span>
                                            @if($slotStart)
                                                <span class="text-[10px] text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm font-bold text-slate-800">{{ $journal->teacherAssignment->classSubject->subject->name }}</span>
                                            <span class="text-xs font-medium text-slate-500">· {{ $journal->teacherAssignment->classSubject->classroomTerm->name }}</span>
                                        </div>
                                        <p class="text-sm text-slate-600 mt-0.5 line-clamp-2" title="{{ $journal->material }}">{{ $journal->material }}</p>
                                        <div class="mt-1">
                                            @if($journal->absences->isEmpty())
                                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded">Hadir semua</span>
                                            @else
                                                <span class="text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded">{{ $journal->absences->count() }} tidak hadir</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('guru.diniyyah-journals.edit', $journal) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-50 transition-colors">Edit</a>
                                        <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if(collect($agendaRows ?? [])->isNotEmpty())
        <section class="glass-card rounded-2xl border border-sky-200 bg-sky-50/60 p-6" aria-labelledby="agenda-history-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[.16em] text-sky-700">Status virtual</p>
                    <h2 id="agenda-history-heading" class="mt-1 text-lg font-black text-sky-950">Agenda tanpa KBM</h2>
                    <p class="mt-1 text-sm font-medium text-sky-800">Slot berikut tidak membuat record jurnal karena kegiatan khusus sekolah.</p>
                </div>
                <span class="rounded-full border border-sky-200 bg-white px-3 py-1 text-xs font-black text-sky-800">{{ collect($agendaRows)->count() }} slot</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($agendaRows as $row)
                    <div class="flex flex-col gap-1 rounded-xl border border-sky-200 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-black text-sky-950">{{ $row['date_label'] }} · {{ $row['session_label'] }}</p>
                            <p class="text-xs font-semibold text-slate-600">{{ $row['kelas'] }} · {{ $row['mapel'] }}</p>
                        </div>
                        <p class="text-xs font-black text-sky-700">{{ $row['material'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.portal>
