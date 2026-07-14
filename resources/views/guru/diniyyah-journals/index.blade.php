<x-layouts.portal title="Jurnal Kelas Diniyyah" portalLabel="Portal Guru" breadcrumb="Jurnal Kelas">
    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <h1 class="text-2xl font-black text-slate-900">Isi Jurnal Kelas</h1>
        <a href="{{ route('guru.dashboard') }}" class="text-sm font-bold text-slate-500 hover:text-amber-600">Ke Dashboard</a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm font-medium text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filter Kelas dan Tanggal -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <form method="GET" action="{{ route('guru.diniyyah-journals.index') }}" class="flex flex-col sm:flex-row gap-4 items-end" id="filter-form">
            <div class="flex-1">
                <label class="block text-sm font-bold text-slate-700 mb-1">Kelas</label>
                <select name="classroom_term_id" class="w-full rounded-xl border-slate-300 shadow-sm text-sm py-2" onchange="document.getElementById('filter-form').submit()">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classes as $classTerm)
                        <option value="{{ $classTerm->id }}" {{ $selectedClassroomTermId == $classTerm->id ? 'selected' : '' }}>
                            {{ $classTerm->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-bold text-slate-700 mb-1">Tanggal</label>
                <input type="date" name="date" value="{{ $selectedDate }}" class="w-full rounded-xl border-slate-300 shadow-sm text-sm py-2" onchange="document.getElementById('filter-form').submit()">
            </div>
            <div>
                <button type="submit" class="bg-amber-600 text-white rounded-xl px-6 py-2 text-sm font-bold shadow-sm hover:bg-amber-700">Pilih</button>
            </div>
        </form>
    </div>

    @if($selectedClassroomTermId)
        <!-- Tabel Jurnal (Seperti Excel) -->
        <div class="glass-card rounded-2xl overflow-hidden mb-8 border border-slate-200">
            <div class="bg-slate-50 p-4 border-b border-slate-200 text-center">
                <h2 class="font-black text-lg uppercase tracking-wider text-slate-800">Jurnal Kelas Pembelajaran Diniyyah</h2>
                <p class="text-sm font-bold text-slate-500">Tanggal: {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}</p>
            </div>
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto w-full">
                <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-slate-100 text-xs uppercase tracking-wider text-slate-600 font-bold border-b border-slate-200">
                        <th class="p-3 border-r border-slate-200 w-16 text-center">Jam</th>
                        <th class="p-3 border-r border-slate-200">Guru</th>
                        <th class="p-3 border-r border-slate-200">Mapel</th>
                        <th class="p-3 border-r border-slate-200 w-1/3">Materi</th>
                        <th class="p-3 border-r border-slate-200">Tidak Hadir</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($existingJournals as $journal)
                        <tr class="border-b border-slate-100 {{ $journal->teacherAssignment->teacher_id === $teacher->id ? 'bg-amber-50/30' : '' }}">
                            <td class="p-3 border-r border-slate-200 text-center font-bold text-slate-700">
                                @php
                                    $s = $classSessions->firstWhere('session_name', $journal->session_hour);
                                @endphp
                                <div class="font-bold text-slate-800 text-base">{{ $journal->session_hour }}</div>
                                @if($s && $s->starts_at)
                                    <div class="text-[10px] text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($s->starts_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($s->ends_at)->format('H:i') }}</div>
                                @endif
                            </td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-700 font-semibold">{{ $journal->teacherAssignment->teacher->name }}</td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-700">{{ $journal->teacherAssignment->classSubject->subject->name }}</td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-800">{{ $journal->material }}</td>
                            <td class="p-3 border-r border-slate-200 text-xs">
                                @if($journal->absences->isEmpty())
                                    <span class="text-slate-400 italic">Nihil</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($journal->absences as $abs)
                                            <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold">{{ $abs->classEnrollment->student->name }} ({{ $abs->status === 'skipped' ? 'Bolos Sesi' : ucfirst($abs->status) }})</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($journal->teacherAssignment->teacher_id === $teacher->id)
                                    <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-500 font-medium">Belum ada jurnal tercatat di hari ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="block md:hidden">
                @forelse($existingJournals as $journal)
                    <div class="border-b border-slate-200 p-4 {{ $journal->teacherAssignment->teacher_id === $teacher->id ? 'bg-amber-50/30' : 'bg-white' }} last:border-b-0">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center justify-center bg-slate-100 rounded-lg p-2 min-w-[3.5rem] border border-slate-200">
                                    <span class="font-bold text-slate-800 text-lg">{{ $journal->session_hour }}</span>
                                    @php
                                        $s = $classSessions->firstWhere('session_name', $journal->session_hour);
                                    @endphp
                                    @if($s && $s->starts_at)
                                        <span class="text-[9px] text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($s->starts_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($s->ends_at)->format('H:i') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 text-sm">{{ $journal->teacherAssignment->classSubject->subject->name }}</div>
                                    <div class="text-xs text-slate-600 mt-0.5">{{ $journal->teacherAssignment->teacher->name }}</div>
                                </div>
                            </div>
                            
                            @if($journal->teacherAssignment->teacher_id === $teacher->id)
                                <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                        
                        <div class="mt-3">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Materi</span>
                            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100">{{ $journal->material }}</p>
                        </div>
                        
                        <div class="mt-3">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Santri Tidak Hadir</span>
                            @if($journal->absences->isEmpty())
                                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100">Nihil (Hadir Semua)</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($journal->absences as $abs)
                                        <span class="bg-amber-100 border border-amber-200 text-amber-800 px-2 py-1 rounded-md text-xs font-bold shadow-sm">
                                            {{ $abs->classEnrollment->student->name }} 
                                            <span class="text-[10px] font-normal opacity-80">({{ $abs->status === 'skipped' ? 'Bolos' : ucfirst($abs->status) }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-500 font-medium">Belum ada jurnal tercatat di hari ini.</div>
                @endforelse
            </div>
        </div>

        <!-- Form Tambah Jurnal (Hanya untuk kelas/mapel yang diajarkan guru ini) -->
        @if($classAssignments->isNotEmpty())
        <div class="glass-card rounded-2xl p-6 border border-slate-200">
            <h3 class="text-lg font-black text-slate-800 mb-4 border-b border-slate-100 pb-2">Isi Jam Pelajaran Anda</h3>
            
            <form method="POST" action="{{ route('guru.diniyyah-journals.store') }}">
                @csrf
                <input type="hidden" name="classroom_term_id" value="{{ $selectedClassroomTermId }}">
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="sm:col-span-1 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Jam Ke-</label>
                            <select name="session_hour" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm">
                                <option value="" disabled selected>Pilih Jam Ke-</option>
                                @foreach($classSessions as $session)
                                    <option value="{{ $session->session_name }}" {{ $session->is_break ? 'disabled' : '' }}>
                                        Jam Ke-{{ $session->session_name }} 
                                        @if($session->starts_at)
                                            ({{ \Carbon\Carbon::parse($session->starts_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($session->ends_at)->format('H:i') }})
                                        @endif
                                        {{ $session->is_break ? '[Istirahat]' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Mata Pelajaran</label>
                            <select name="diniyyah_teacher_assignment_id" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm">
                                @foreach($classAssignments as $assignment)
                                    <option value="{{ $assignment->id }}">{{ $assignment->classSubject->subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Materi</label>
                            <textarea name="material" rows="5" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tuliskan materi yang diajarkan..."></textarea>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-slate-800 mb-3 border-b border-slate-200 pb-2">Presensi Sesi Ini</h4>
                            <p class="text-xs text-slate-500 mb-3">Centang santri yang tidak hadir. Santri yang absen harian oleh wali kelas otomatis tercatat.</p>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto pr-2">
                                @foreach($students as $enrollment)
                                    @php
                                        $dailyStatus = $dailyAbsences[$enrollment->id] ?? null;
                                        $isAbsent = $dailyStatus !== null;
                                    @endphp
                                    <div class="flex items-center p-3 border {{ $isAbsent ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white hover:border-slate-300' }} rounded-xl transition-colors cursor-pointer" onclick="document.getElementById('student_{{ $enrollment->id }}').click()">
                                        <div class="flex items-center h-5">
                                            @if($isAbsent)
                                                <input type="hidden" name="absences[{{ $enrollment->id }}]" value="{{ $dailyStatus }}">
                                                <input type="checkbox" checked disabled class="h-4.5 w-4.5 text-amber-600 rounded border-slate-300 pointer-events-none">
                                            @else
                                                <input id="student_{{ $enrollment->id }}" type="checkbox" name="absences[{{ $enrollment->id }}]" value="skipped" class="h-4.5 w-4.5 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" onclick="event.stopPropagation()">
                                            @endif
                                        </div>
                                        <div class="ml-3 flex-1 flex justify-between items-center text-sm">
                                            <label for="student_{{ $enrollment->id }}" class="font-bold text-slate-700 truncate cursor-pointer select-none w-full" onclick="event.stopPropagation()">{{ $enrollment->student->name }}</label>
                                            @if($isAbsent)
                                                <span class="text-[10px] font-bold text-amber-800 uppercase bg-amber-200 px-2 py-0.5 rounded ml-2">{{ $dailyStatus }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-start">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-colors">
                        Simpan Jurnal Jam Ini
                    </button>
                </div>
            </form>
        </div>
        @endif

    @else
        <div class="text-center p-16 glass-card rounded-2xl text-slate-500 font-medium border border-slate-200">
            <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Pilih Kelas dan Tanggal di atas untuk mulai mengisi jurnal KBM.
        </div>
    @endif
</x-layouts.portal>
