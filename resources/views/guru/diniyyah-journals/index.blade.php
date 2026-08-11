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

    <!-- Akses cepat ke Riwayat Jurnal Saya (halaman terpisah, on-demand) -->
    <div class="mb-6 flex justify-end">
        <a href="{{ route('guru.diniyyah-journals.riwayat') }}" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 text-white px-5 py-2.5 text-sm font-bold shadow-sm hover:bg-amber-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            Riwayat Jurnal Saya
        </a>
    </div>

    <!-- Filter Kelas dan Tanggal -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <form method="GET" action="{{ route('guru.diniyyah-journals.index') }}" class="flex flex-col sm:flex-row gap-4 items-end" id="filter-form">
            <div class="flex-1 w-full">
                <label for="classroom_term_id" class="block text-sm font-bold text-slate-700 mb-1.5">Kelas</label>
                <select id="classroom_term_id" name="classroom_term_id" class="w-full rounded-xl border-slate-300 shadow-sm text-sm py-2.5 focus:ring-amber-500 focus:border-amber-500" onchange="document.getElementById('filter-form').submit()">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classes as $classTerm)
                        <option value="{{ $classTerm->id }}" {{ $selectedClassroomTermId == $classTerm->id ? 'selected' : '' }}>
                            {{ $classTerm->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 w-full">
                <label for="date" class="block text-sm font-bold text-slate-700 mb-1.5">Tanggal</label>
                <input id="date" type="date" name="date" value="{{ $selectedDate }}" class="w-full rounded-xl border-slate-300 shadow-sm text-sm py-2.5 focus:ring-amber-500 focus:border-amber-500" onchange="document.getElementById('filter-form').submit()">
                <p class="mt-1.5 text-xs font-bold text-slate-600">{{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}</p>
            </div>
            <div class="w-full sm:w-auto">
                <button type="submit" class="w-full sm:w-auto bg-amber-600 text-white rounded-xl px-6 py-2.5 text-sm font-bold shadow-sm hover:bg-amber-700 transition-colors">Pilih</button>
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
                                    $slot = $sessionSlots->firstWhere('session_name', $journal->session_hour);
                                    $slotStart = $journal->session_starts_at ?: $slot?->starts_at;
                                    $slotEnd = $journal->session_ends_at ?: $slot?->ends_at;
                                @endphp
                                <div class="font-bold text-slate-800 text-base">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : $journal->session_hour }}</div>
                                @if($slotStart)
                                    <div class="text-[10px] text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</div>
                                @endif
                            </td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-700 font-semibold">
                                {{ $journal->teacherAssignment->teacher->name }}
                                @if($journal->substitute_teacher_id !== null)
                                    <span class="block mt-1 text-[10px] font-bold {{ $journal->teacherAssignment->teacher_id === $teacher->id ? 'text-red-700 bg-red-100 border-red-200' : 'text-amber-700 bg-amber-100 border-amber-200' }} border rounded px-1.5 py-0.5 w-fit">
                                        @if($journal->teacherAssignment->teacher_id === $teacher->id)
                                            Anda sudah digantikan oleh {{ $journal->substituteTeacher->name }}
                                        @else
                                            Digantikan oleh {{ $journal->substituteTeacher->name }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-700">{{ $journal->teacherAssignment->classSubject->subject->name }}</td>
                            <td class="p-3 border-r border-slate-200 text-sm text-slate-800">{{ $journal->material }}</td>
                            <td class="p-3 border-r border-slate-200 text-xs">
                                @if($journal->absences->isEmpty())
                                    <span class="text-slate-400 italic">Nihil</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($journal->absences as $abs)
                                            <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold">{{ $abs->classEnrollment->student->name }} ({{ $abs->status === 'skipped' ? 'Bolos Sesi' : \App\Support\UiLabel::absenceLabel($abs->status) }})</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($journal->substitute_teacher_id === null && $journal->teacherAssignment->teacher_id === $teacher->id)
                                    <div class="flex items-center justify-center gap-3">
                                        <a href="{{ route('guru.diniyyah-journals.edit', $journal) }}" class="text-xs font-bold text-amber-700 hover:text-amber-900">Edit</a>
                                        <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-800">Hapus</button>
                                        </form>
                                    </div>
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
                                    <span class="font-bold text-slate-800 text-lg">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : $journal->session_hour }}</span>
                                    @php
                                        $slot = $sessionSlots->firstWhere('session_name', $journal->session_hour);
                                        $slotStart = $journal->session_starts_at ?: $slot?->starts_at;
                                        $slotEnd = $journal->session_ends_at ?: $slot?->ends_at;
                                    @endphp
                                    @if($slotStart)
                                        <span class="text-[9px] text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 text-sm">{{ $journal->teacherAssignment->classSubject->subject->name }}</div>
                                    <div class="text-xs text-slate-600 mt-0.5">{{ $journal->teacherAssignment->teacher->name }}</div>
                                    @if($journal->substitute_teacher_id !== null)
                                        <span class="inline-block mt-1 text-[10px] font-bold {{ $journal->teacherAssignment->teacher_id === $teacher->id ? 'text-red-700 bg-red-100 border-red-200' : 'text-amber-700 bg-amber-100 border-amber-200' }} border rounded px-1.5 py-0.5">
                                            @if($journal->teacherAssignment->teacher_id === $teacher->id)
                                                Anda sudah digantikan oleh {{ $journal->substituteTeacher->name }}
                                            @else
                                                Digantikan oleh {{ $journal->substituteTeacher->name }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            @if($journal->substitute_teacher_id === null && $journal->teacherAssignment->teacher_id === $teacher->id)
                                <div class="flex flex-col items-end gap-2">
                                    <a href="{{ route('guru.diniyyah-journals.edit', $journal) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-50 transition-colors">Edit</a>
                                    <form action="{{ route('guru.diniyyah-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal jam ke-{{ $journal->session_hour }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" title="Hapus">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
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
                                            <span class="text-[10px] font-normal opacity-80">({{ $abs->status === 'skipped' ? 'Bolos' : \App\Support\UiLabel::absenceLabel($abs->status) }})</span>
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

        <!-- Form Tambah Jurnal (Hanya untuk kelas/mapel yang diajarkan guru ini,
             dan hanya di hari yang guru benar-benar dijadwalkan mengajar kelas itu) -->
        @if($classAssignments->isNotEmpty() && $sessionSlots->isNotEmpty() && $hasScheduleOnDay)
        <div class="glass-card rounded-2xl p-6 border border-slate-200">
            <h3 class="text-lg font-black text-slate-800 mb-1">Isi Jam Pelajaran Anda</h3>
            <p class="text-sm text-slate-500 mb-5">Lengkapi sesi, mata pelajaran, materi, dan presensi santri untuk satu jam pelajaran.</p>

            <form method="POST" action="{{ route('guru.diniyyah-journals.store') }}">
                @csrf
                <input type="hidden" name="classroom_term_id" value="{{ $selectedClassroomTermId }}">
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div>
                    <label for="schedule_slot" class="block text-sm font-bold text-slate-700 mb-1.5">Jadwal Mengajar (Sesi & Mapel)</label>
                    <select id="schedule_slot" name="schedule_slot" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500">
                        <option value="" disabled selected>Pilih jadwal...</option>
                        @foreach($scheduledSlots as $slot)
                            @php
                                $slotStart = $slot->starts_at ? \Carbon\Carbon::parse($slot->starts_at)->format('H:i') : '';
                                $slotEnd = $slot->ends_at ? \Carbon\Carbon::parse($slot->ends_at)->format('H:i') : '';
                            @endphp
                            <option value="{{ $slot->assignment_id }}|{{ $slot->session_name }}"
                                    data-assignment="{{ $slot->assignment_id }}"
                                    data-session="{{ $slot->session_name }}"
                                    data-start="{{ $slotStart }}"
                                    data-end="{{ $slotEnd }}"
                                    {{ $slot->filled ? 'disabled' : '' }}>
                                {{ \App\Support\SessionTimetable::label($slot->session_name) }} — {{ $slot->subject_name }}@if($slotStart) ({{ $slotStart }} - {{ $slotEnd }}) @endif{{ $slot->filled ? ' (sudah terisi)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span id="session-time-hint" class="mt-1 block text-xs font-medium text-slate-500"></span>
                </div>
                {{-- Kontrak field lama dipertahankan: dua hidden ini diisi oleh skrip
                     dari pilihan "Jadwal Mengajar" di atas. --}}
                <input type="hidden" name="diniyyah_teacher_assignment_id" id="assignment_id_input">
                <input type="hidden" name="session_hour" id="session_hour_input">

                <div class="mt-5">
                    <label for="material" class="block text-sm font-bold text-slate-700 mb-1.5">Materi</label>
                    <textarea id="material" name="material" rows="3" required class="w-full rounded-xl border-slate-300 shadow-sm text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Tuliskan materi yang diajarkan..."></textarea>
                </div>

                <div class="mt-5 bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Presensi Sesi Ini</h4>
                    <p class="text-xs text-slate-500 mb-3">Centang santri yang tidak hadir. Santri yang sudah absen harian oleh wali kelas otomatis tercatat.</p>

                    @include('guru.diniyyah-journals.partials._absence-grid')
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="w-full sm:w-auto rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-colors">
                        Simpan Jurnal Jam Ini
                    </button>
                </div>
            </form>
        </div>
        <script>
            (function () {
                const s = document.getElementById('schedule_slot'),
                    a = document.getElementById('assignment_id_input'),
                    h = document.getElementById('session_hour_input'),
                    t = document.getElementById('session-time-hint');
                if (!s || !a || !h) return;
                function apply() {
                    const o = s.options[s.selectedIndex];
                    if (!o || !o.dataset.assignment) {
                        a.value = '';
                        h.value = '';
                        if (t) t.textContent = '';
                        return;
                    }
                    a.value = o.dataset.assignment;
                    h.value = o.dataset.session;
                    if (t) t.textContent = o.dataset.start ? (o.dataset.start + ' - ' + o.dataset.end) : '';
                }
                s.addEventListener('change', apply);
                apply();
            })();
        </script>
        @elseif($classAssignments->isNotEmpty() && $sessionSlots->isNotEmpty())
            {{-- Matrix kelas punya sesi di hari ini, tapi guru tidak dijadwalkan mengajar
                 kelas ini di hari tsb → matikan form, beri peringatan berkonteks kelas. --}}
            <div class="glass-card rounded-2xl p-8 border border-amber-200 bg-amber-50 text-center">
                <p class="text-sm font-bold text-amber-800">
                    Hari {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }} tidak ada jadwal mengajar untuk Anda di kelas {{ $selectedTerm?->name }}.
                </p>
                <p class="text-xs text-amber-700 mt-1">Pilih tanggal yang jatuh di hari mengajar Anda pada kelas ini.</p>
            </div>
        @elseif($classAssignments->isNotEmpty())
            <div class="glass-card rounded-2xl p-8 border border-slate-200 text-center text-slate-500 font-medium">
                Tidak ada sesi diniyyah di hari ini ({{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l') }}) untuk kelas ini.
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
