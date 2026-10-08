<x-layouts.portal title="Jurnal Guru Pengganti" portalLabel="Portal Guru" breadcrumb="Jurnal Pengganti">
    <x-slot name="navLinks">
        <a href="{{ route('guru.diniyyah-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-journals.index') ? 'bg-surface-muted border-line-strong text-heading' : 'text-muted hover:bg-surface-subtle' }} text-theme-sm font-medium">Jurnal Kelas</a>
        <a href="{{ route('guru.diniyyah-substitute-tafsir-journals.index') }}" class="btn btn-outline btn-sm {{ request()->routeIs('guru.diniyyah-substitute-tafsir-journals.index') ? 'bg-surface-muted border-line-strong text-heading' : 'text-muted hover:bg-surface-subtle' }} text-theme-sm font-medium">Pengganti Tafsir</a>
    </x-slot>

    <div class="mb-6 flex justify-between items-center glass-card p-4 rounded-2xl">
        <div>
            <h1 class="text-heading ui-page-title">Jurnal Guru Pengganti</h1>
            <p class="text-theme-xs font-normal text-muted mt-1">Catat jurnal KBM diniyyah saat Anda menggantikan guru lain yang berhalangan. JP tercatat ke Anda (untuk penghitungan gaji).</p>
            @if($isSchedulelessSubstitute)
                <p class="mt-2 inline-flex rounded-lg border border-brand-line bg-brand-soft px-3 py-1.5 text-theme-xs font-normal text-brand-ink">Mode guru pengganti tanpa jadwal — hanya kelas sesuai gender Anda.</p>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-success-soft border border-success-line text-theme-sm font-medium text-success-ink">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-danger-soft border border-danger-line text-theme-sm font-medium text-danger-ink">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filter Kelas dan Tanggal -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <form method="GET" action="{{ route('guru.diniyyah-substitute-journals.index') }}" class="flex flex-col sm:flex-row gap-4 items-end" id="filter-form">
            <div class="flex-1">
                <label class="block text-body mb-1 ui-form-label">Kelas (yang ingin Anda gantikan)</label>
                <select name="classroom_term_id" class="w-full rounded-xl border-line-strong shadow-sm py-2 text-theme-sm font-normal" onchange="document.getElementById('filter-form').submit()">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($classes as $classTerm)
                        <option value="{{ $classTerm->id }}" {{ $selectedClassroomTermId == $classTerm->id ? 'selected' : '' }}>
                            {{ $classTerm->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-body mb-1 ui-form-label">Tanggal</label>
                <input type="date" name="date" value="{{ $selectedDate }}" class="w-full rounded-xl border-line-strong shadow-sm py-2 text-theme-sm font-normal" onchange="document.getElementById('filter-form').submit()">
                <p class="mt-1.5 text-theme-xs font-normal text-body">{{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}</p>
            </div>
            <div class="w-full sm:w-auto">
                <button type="submit" class="w-full sm:w-auto bg-warning-600 text-white rounded-xl px-6 py-2 shadow-sm hover:bg-warning-700 text-theme-sm font-medium">Pilih</button>
            </div>
        </form>
    </div>

    @if($selectedClassroomTermId)
        <!-- Tabel Jurnal -->
        <div class="glass-card rounded-2xl overflow-hidden mb-8 border border-line">
            <div class="bg-surface-subtle p-4 border-b border-line text-center">
                <h2 class="text-heading ui-card-title">Jurnal Kelas Pembelajaran Diniyyah</h2>
                <p class="text-theme-sm font-normal text-muted">Tanggal: {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}</p>
            </div>
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto w-full">
                <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-surface-muted text-theme-xs uppercase text-body font-normal border-b border-line">
                        <th class="p-3 border-r border-line w-16 text-center text-theme-xs font-medium">Jam</th>
                        <th class="p-3 border-r border-line text-theme-xs font-medium">Guru Asli</th>
                        <th class="p-3 border-r border-line text-theme-xs font-medium">Mapel</th>
                        <th class="p-3 border-r border-line w-1/3 text-theme-xs font-medium">Materi</th>
                        <th class="p-3 border-r border-line text-theme-xs font-medium">Tidak Hadir</th>
                        <th class="p-3 text-center text-theme-xs font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($existingJournals as $journal)
                        <tr class="border-b border-line {{ $journal->substitute_teacher_id === $teacher->id ? 'bg-warning-soft/40' : '' }}">
                            <td class="p-3 border-r border-line text-center font-medium text-body">
                                @php
                                    $slot = $sessionSlots->firstWhere('session_name', $journal->session_hour);
                                    $slotStart = $journal->session_starts_at ?: $slot?->starts_at;
                                    $slotEnd = $journal->session_ends_at ?: $slot?->ends_at;
                                @endphp
                                <div class="font-medium text-heading text-theme-sm">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : $journal->session_hour }}</div>
                                @if($slotStart)
                                    <div class="text-theme-xs text-muted whitespace-nowrap font-normal">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</div>
                                @endif
                            </td>
                            <td class="p-3 border-r border-line text-theme-sm text-body font-medium">
                                {{ $journal->teacherAssignment->teacher->name }}
                                @if($journal->substitute_teacher_id !== null)
                                    <span class="block mt-1 text-theme-xs font-normal text-warning-ink bg-warning-soft-strong border border-warning-line rounded px-1.5 py-0.5 w-fit">
                                        @if($journal->substitute_teacher_id === $teacher->id)
                                            Diisi Anda sebagai pengganti
                                        @else
                                            Digantikan oleh {{ $journal->substituteTeacher->name }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 border-r border-line text-theme-sm text-body">{{ $journal->teacherAssignment->classSubject->subject->name }}</td>
                            <td class="p-3 border-r border-line text-theme-sm text-heading">{{ $journal->material }}</td>
                            <td class="p-3 border-r border-line text-theme-sm">
                                @if($journal->absences->isEmpty())
                                    <span class="text-soft italic">Nihil</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($journal->absences as $abs)
                                            <span class="bg-warning-soft-strong text-warning-ink px-1.5 py-0.5 rounded font-medium">{{ $abs->classEnrollment->student->name }} ({{ $abs->status === 'skipped' ? 'Bolos Sesi' : \App\Support\UiLabel::absenceLabel($abs->status) }})</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($journal->substitute_teacher_id === $teacher->id)
                                    <form action="{{ route('guru.diniyyah-substitute-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal pengganti jam ke-{{ $journal->session_hour }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-danger-ink hover:text-danger-ink text-theme-sm font-medium">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-muted font-medium">Belum ada jurnal tercatat di hari ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="block md:hidden">
                @forelse($existingJournals as $journal)
                    <div class="border-b border-line p-4 {{ $journal->substitute_teacher_id === $teacher->id ? 'bg-warning-soft/40' : 'bg-surface' }} last:border-b-0">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center justify-center bg-surface-muted rounded-lg p-2 min-w-[3.5rem] border border-line">
                                    <span class="font-bold text-heading text-lg">{{ $journal->session_hour === 'tafsir' ? 'Tafsir' : $journal->session_hour }}</span>
                                    @php
                                        $slot = $sessionSlots->firstWhere('session_name', $journal->session_hour);
                                        $slotStart = $journal->session_starts_at ?: $slot?->starts_at;
                                        $slotEnd = $journal->session_ends_at ?: $slot?->ends_at;
                                    @endphp
                                    @if($slotStart)
                                        <span class="text-theme-xs text-muted whitespace-nowrap font-normal">{{ \Carbon\Carbon::parse($slotStart)->format('H:i') }} - {{ \Carbon\Carbon::parse($slotEnd)->format('H:i') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-heading text-theme-sm">{{ $journal->teacherAssignment->classSubject->subject->name }}</div>
                                    <div class="text-theme-xs text-body mt-0.5 font-normal">{{ $journal->teacherAssignment->teacher->name }}</div>
                                    @if($journal->substitute_teacher_id !== null)
                                        <span class="inline-block mt-1 text-theme-xs font-normal text-warning-ink bg-warning-soft-strong border border-warning-line rounded px-1.5 py-0.5">
                                            @if($journal->substitute_teacher_id === $teacher->id)
                                                Diisi Anda sebagai pengganti
                                            @else
                                                Digantikan oleh {{ $journal->substituteTeacher->name }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($journal->substitute_teacher_id === $teacher->id)
                                <form action="{{ route('guru.diniyyah-substitute-journals.destroy', $journal) }}" method="POST" onsubmit="return confirm('Hapus jurnal pengganti jam ke-{{ $journal->session_hour }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2.5 text-danger-500 hover:text-danger-ink hover:bg-danger-soft rounded-lg transition-colors" title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="mt-3">
                            <span class="text-theme-xs font-normal text-soft uppercase block mb-1">Materi</span>
                            <p class="text-theme-sm text-body bg-surface-subtle p-3 rounded-lg border border-line">{{ $journal->material }}</p>
                        </div>

                        <div class="mt-3">
                            <span class="text-theme-xs font-normal text-soft uppercase block mb-1">Santri Tidak Hadir</span>
                            @if($journal->absences->isEmpty())
                                <span class="text-theme-xs font-normal text-success-ink bg-success-soft px-2.5 py-1 rounded-md border border-success-line">Nihil (Hadir Semua)</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($journal->absences as $abs)
                                        <span class="bg-warning-soft-strong border border-warning-line text-warning-ink px-2 py-1 rounded-md text-theme-xs font-normal shadow-sm">
                                            {{ $abs->classEnrollment->student->name }}
                                            <span class="text-theme-xs font-normal opacity-80">({{ $abs->status === 'skipped' ? 'Bolos' : \App\Support\UiLabel::absenceLabel($abs->status) }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-muted font-medium">Belum ada jurnal tercatat di hari ini.</div>
                @endforelse
            </div>
        </div>

        <!-- Form Isi Jurnal Pengganti -->
        @if($classAssignments->isNotEmpty() && $sessionSlots->isNotEmpty() && $hasScheduleOnDay)
        <div class="glass-card rounded-2xl p-6 border border-line">
            <h3 class="text-heading mb-2 border-b border-line pb-2 ui-card-title">Isi Jurnal Pengganti</h3>
            <p class="text-theme-xs font-normal text-warning-ink bg-warning-soft border border-warning-line rounded-lg p-3 mb-4">
                @if($isSchedulelessSubstitute)
                    Pilih mapel dan sesi yang Anda gantikan di kelas ini. Semua mapel aktif dan sesi timetable kelas tersedia; Anda tercatat sebagai <strong>Guru Pengganti</strong> dan JP-nya dihitung ke Anda.
                @else
                    Pilih guru asli yang Anda gantikan. Anda akan tercatat sebagai <strong>Guru Pengganti</strong> untuk slot ini, dan JP-nya dihitung ke Anda.
                @endif
            </p>

            <form method="POST" action="{{ route('guru.diniyyah-substitute-journals.store') }}">
                @csrf
                <input type="hidden" name="classroom_term_id" value="{{ $selectedClassroomTermId }}">
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="sm:col-span-1 space-y-4">
                        <div>
                            <label for="schedule_slot" class="block text-body mb-1 ui-form-label">{{ $isSchedulelessSubstitute ? 'Mapel & Sesi yang Digantikan' : 'Jadwal Guru Asli yang Digantikan (Sesi & Mapel)' }}</label>
                            <select id="schedule_slot" name="schedule_slot" required class="w-full rounded-xl border-line-strong shadow-sm focus:ring-warning-500 focus:border-warning-500 text-theme-sm font-normal">
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
                                        {{ \App\Support\SessionTimetable::label($slot->session_name) }} — {{ $slot->subject_name }} — {{ $slot->teacher_name }}@if($slotStart) ({{ $slotStart }} - {{ $slotEnd }}) @endif{{ $slot->filled ? ' (sudah terisi)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <span id="session-time-hint" class="mt-1 block text-theme-xs font-normal text-muted"></span>
                        </div>
                        {{-- Kontrak field lama dipertahankan: dua hidden ini diisi oleh skrip
                             dari pilihan "Jadwal Guru Asli" di atas. --}}
                        <input type="hidden" name="diniyyah_teacher_assignment_id" id="assignment_id_input">
                        <input type="hidden" name="session_hour" id="session_hour_input">
                        <div>
                            <label class="block text-body mb-1 ui-form-label">Materi</label>
                            <textarea name="material" rows="5" required class="w-full rounded-xl border-line-strong shadow-sm focus:ring-warning-500 focus:border-warning-500 text-theme-sm font-normal" placeholder="Tuliskan materi yang diajarkan..."></textarea>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="bg-surface-subtle border border-line rounded-xl p-4">
                            <h4 class="text-theme-sm font-medium text-heading mb-3 border-b border-line pb-2">Presensi Sesi Ini</h4>
                            <p class="text-theme-xs text-muted mb-3 font-normal">Centang santri yang tidak hadir. Santri yang absen harian oleh wali kelas otomatis tercatat.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] sm:max-h-60 overflow-y-auto pr-2">
                                @foreach($students as $enrollment)
                                    @php
                                        $dailyStatus = $dailyAbsences[$enrollment->id] ?? null;
                                        $isAbsent = $dailyStatus !== null;
                                    @endphp
                                    <div class="flex items-center p-3 border {{ $isAbsent ? 'border-warning-line bg-warning-soft' : 'border-line bg-surface hover:border-line-strong' }} rounded-xl transition-colors cursor-pointer" onclick="document.getElementById('sub_student_{{ $enrollment->id }}').click()">
                                        <div class="flex items-center h-5">
                                            @if($isAbsent)
                                                <input type="hidden" name="absences[{{ $enrollment->id }}]" value="{{ $dailyStatus }}">
                                                <input type="checkbox" checked disabled class="h-5 w-5 text-warning-ink rounded border-line-strong pointer-events-none">
                                            @else
                                                <input id="sub_student_{{ $enrollment->id }}" type="checkbox" name="absences[{{ $enrollment->id }}]" value="skipped" class="h-5 w-5 text-warning-ink rounded border-line-strong focus:ring-warning-500 cursor-pointer" onclick="event.stopPropagation()">
                                            @endif
                                        </div>
                                        <div class="ml-3 flex-1 flex justify-between items-center text-theme-sm">
                                            <label for="sub_student_{{ $enrollment->id }}" title="{{ $enrollment->student->name }}" class="text-body truncate cursor-pointer select-none w-full ui-form-label" onclick="event.stopPropagation()">{{ $enrollment->student->name }}</label>
                                            @if($isAbsent)
                                                <span class="text-theme-xs font-normal text-warning-ink uppercase bg-warning-soft-strong px-2 py-0.5 rounded ml-2">{{ $dailyStatus }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-start">
                    <button type="submit" class="w-full sm:w-auto rounded-xl bg-warning-600 px-6 py-3 text-white hover:bg-warning-700 shadow-sm transition-colors text-theme-sm font-medium">
                        Simpan Jurnal Pengganti
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
        @elseif($classAssignments->isEmpty())
            <div class="glass-card rounded-2xl p-8 border border-line text-center text-muted font-medium">
                Tidak ada guru asli yang dapat digantikan untuk kelas ini (mungkin Anda adalah satu-satunya guru diniyyah di kelas ini, atau belum ada assignment diniyyah aktif).
            </div>
        @elseif($sessionSlots->isEmpty())
            <div class="glass-card rounded-2xl p-8 border border-line text-center text-muted font-medium">
                Tidak ada sesi diniyyah di hari ini ({{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l') }}) untuk kelas ini.
            </div>
        @else
            <div class="glass-card rounded-2xl p-8 border border-warning-line bg-warning-soft text-center">
                @if($isSchedulelessSubstitute)
                    <p class="text-theme-sm font-normal text-warning-ink">
                        Tidak ada kombinasi mapel dan sesi yang tersedia di kelas {{ $selectedTerm?->name }} pada hari {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}.
                    </p>
                    <p class="text-theme-xs text-warning-ink mt-1 font-normal">Pastikan timetable kelas dan assignment guru asli sudah tersedia.</p>
                @else
                    <p class="text-theme-sm font-normal text-warning-ink">
                        Tidak ada jadwal mengajar guru asli di kelas {{ $selectedTerm?->name }} pada hari {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}.
                    </p>
                    <p class="text-theme-xs text-warning-ink mt-1 font-normal">Pilih tanggal yang jatuh di hari mengajar guru asli pada kelas ini.</p>
                @endif
            </div>
        @endif

    @else
        <div class="text-center p-16 glass-card rounded-2xl text-muted font-medium border border-line">
            <svg class="mx-auto h-12 w-12 text-soft mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Pilih Kelas dan Tanggal di atas untuk mulai mengisi jurnal pengganti.
        </div>
    @endif
</x-layouts.portal>
