<x-layouts.portal title="Riwayat Jurnal Saya" portalLabel="Portal Guru" breadcrumb="Riwayat Jurnal Saya">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="text-sm font-bold text-slate-500 hover:text-amber-600">
            &larr; Kembali ke Input Jurnal
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
                                        <a href="{{ route('guru.diniyyah-journals.edit', $journal) }}" class="text-xs font-bold text-amber-700 hover:text-amber-900">Edit</a>
                                        <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-800">Hapus</button>
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
</x-layouts.portal>