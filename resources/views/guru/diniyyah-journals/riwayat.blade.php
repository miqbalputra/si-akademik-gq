<x-layouts.portal title="Riwayat Jurnal Saya" portalLabel="Portal Guru" breadcrumb="Riwayat Jurnal Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.report') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">Laporan &amp; Download</a>
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="btn btn-outline btn-sm text-theme-sm font-medium">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali<span class="hidden sm:inline"> ke Input Jurnal</span>
        </a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-heading ui-page-title">Riwayat Jurnal Saya</h1>
            <p class="text-theme-sm text-muted">Seluruh jurnal yang sudah Anda isi di semua kelas.</p>
        </div>
        <span class="text-theme-sm font-medium text-warning-ink bg-warning-soft-strong px-3 py-1 rounded-full whitespace-nowrap">
            {{ $myJournals->count() }} jurnal
        </span>
    </div>

    <div class="glass-card rounded-2xl p-6 border border-line">
        @if($myJournals->isEmpty())
            <p class="text-theme-sm text-muted italic text-center py-8">Belum ada jurnal yang Anda isi.</p>
        @else
            @php
                $grouped = $myJournals->groupBy(fn ($j) => $j->date->format('Y-m-d'));
            @endphp
            <div class="space-y-5">
                @foreach($grouped as $date => $journals)
                    <div>
                        <div class="flex items-baseline gap-2 mb-2 pb-1 border-b border-line">
                            <span class="text-theme-sm font-medium text-heading">
                                {{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') }}
                            </span>
                            <span class="text-theme-xs font-normal text-soft">· {{ $journals->count() }} jurnal</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($journals as $journal)
                                @php
                                    $slotStart = $journal->session_starts_at;
                                    $slotEnd = $journal->session_ends_at;
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl border border-line bg-surface">
                                    <div class="flex items-center gap-3 sm:w-40 shrink-0">
                                        <div class="flex flex-col items-center justify-center bg-surface-muted rounded-lg px-2 py-1 min-w-[4rem] border border-line">
                                            <span class="font-medium text-heading text-theme-sm">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : 'Sesi '.$journal->session_hour }}</span>
                                            @if($slotStart)
                                                <span class="text-theme-xs text-muted whitespace-nowrap font-normal">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-theme-sm font-medium text-heading">{{ $journal->teacherAssignment->classSubject->subject->name }}</span>
                                            <span class="text-theme-xs font-normal text-muted">· {{ $journal->teacherAssignment->classSubject->classroomTerm->name }}</span>
                                            @if($journal->substitute_teacher_id)
                                                <span class="text-theme-xs font-normal text-info-ink bg-info-soft-strong px-2 py-0.5 rounded">Jurnal Pengganti</span>
                                            @endif
                                        </div>
                                        <p class="text-theme-sm text-body mt-0.5 line-clamp-2" title="{{ $journal->material }}">{{ $journal->material }}</p>
                                        @if($journal->substitute_teacher_id)
                                            <p class="mt-1 text-theme-xs font-normal text-muted">Menggantikan {{ $journal->teacherAssignment->teacher->name }}</p>
                                        @endif
                                        <div class="mt-1">
                                            @if($journal->absences->isEmpty())
                                                <span class="text-theme-xs font-normal text-success-ink bg-success-soft-strong px-2 py-0.5 rounded">Hadir semua</span>
                                            @else
                                                <span class="text-theme-xs font-normal text-warning-ink bg-warning-soft-strong px-2 py-0.5 rounded">{{ $journal->absences->count() }} tidak hadir</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($journal->substitute_teacher_id)
                                            <form action="{{ route('guru.diniyyah-substitute-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal pengganti jam ke-{{ $journal->session_hour }}?');">
                                        @else
                                            <a href="{{ route('guru.diniyyah-journals.edit', $journal) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-warning-ink hover:bg-warning-soft transition-colors text-theme-sm font-medium">Edit</a>
                                            <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                        @endif
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-lg px-3 py-1.5 text-danger-ink hover:bg-danger-soft transition-colors text-theme-sm font-medium">Hapus</button>
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
        <section class="glass-card rounded-2xl border border-info-line bg-info-soft/60 p-6" aria-labelledby="agenda-history-heading">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-theme-xs font-normal uppercase text-info-ink">Status virtual</p>
                    <h2 id="agenda-history-heading" class="mt-1 text-info-ink ui-card-title">Agenda tanpa KBM</h2>
                    <p class="mt-1 text-theme-sm font-normal text-info-ink">Slot berikut tidak membuat record jurnal karena kegiatan khusus sekolah.</p>
                </div>
                <span class="rounded-full border border-info-line bg-surface px-3 py-1 text-info-ink text-theme-xs font-medium">{{ collect($agendaRows)->count() }} slot</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($agendaRows as $row)
                    <div class="flex flex-col gap-1 rounded-xl border border-info-line bg-surface px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-theme-sm font-normal text-info-ink">{{ $row['date_label'] }} · {{ $row['session_label'] }}</p>
                            <p class="text-theme-xs font-normal text-body">{{ $row['kelas'] }} · {{ $row['mapel'] }}</p>
                        </div>
                        <p class="text-theme-xs font-normal text-info-ink">{{ $row['material'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.portal>
