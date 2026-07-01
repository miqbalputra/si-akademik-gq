<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presensi Diniyyah {{ $classSubject->subject->name }} - {{ $classroomTerm->name }} - SIAKAD Griya Qur'an</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fafafa; }
        .bg-grid {
            background-size: 40px 40px;
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }
    </style>
    @include("partials.pwa-head")
</head>
<body class="min-h-screen text-slate-800 antialiased overflow-x-hidden selection:bg-amber-200 selection:text-amber-900">

    <!-- Background Elements -->
    <div class="fixed inset-0 z-[-1] pointer-events-none">
        <div class="absolute inset-0 bg-grid"></div>
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-amber-400/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-brand-500/10 rounded-full mix-blend-multiply filter blur-[80px]"></div>
    </div>

    <!-- Top Navigation -->
    <nav class="sticky top-0 z-50 glass-card border-b-0 border-white/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 font-bold text-white shadow-md">
                        GQ
                    </span>
                    <div>
                        <span class="block text-sm font-bold text-slate-800 leading-tight">Griya Qur'an</span>
                        <span class="block text-[9px] font-semibold uppercase tracking-wider text-slate-500">Presensi Diniyyah</span>
                    </div>
                </a>
                <a href="{{ route('guru.diniyyah-scores.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    Kembali
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        
        <!-- Header -->
        <header class="mb-6 rounded-3xl glass-card p-6 sm:p-8 animate-fade-in-up">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 mb-2">
                        Jurnal Presensi
                    </span>
                    <h1 class="text-3xl font-black text-slate-900 leading-tight">{{ $classSubject->subject->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-500">
                        Kelas {{ $classroomTerm->name }} &middot; {{ $classroomTerm->academicTerm?->academicYear?->name }}
                    </p>
                    <p class="mt-2 text-xs text-slate-400 font-medium max-w-lg">
                        Rekap presensi dilakukan per pertemuan (P1, P2, dst). Anda cukup mengisi absensi saat Anda mengajar di kelas ini.
                    </p>
                </div>
                <div class="grid grid-cols-4 gap-3 text-center text-xs sm:min-w-[400px]">
                    <div class="rounded-2xl bg-red-50/80 border border-red-150 p-3 text-red-800">
                        <p class="text-[10px] font-bold uppercase tracking-wider">Sakit</p>
                        <p class="mt-1 text-2xl font-black" id="header-sick">{{ $classTotals['sick'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-sky-50/80 border border-sky-150 p-3 text-sky-800">
                        <p class="text-[10px] font-bold uppercase tracking-wider">Izin</p>
                        <p class="mt-1 text-2xl font-black" id="header-permission">{{ $classTotals['permission'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-100/80 border border-slate-200 p-3 text-slate-850">
                        <p class="text-[10px] font-bold uppercase tracking-wider">Alpa</p>
                        <p class="mt-1 text-2xl font-black" id="header-absent">{{ $classTotals['absent'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-blue-50/80 border border-blue-150 p-3 text-blue-800">
                        <p class="text-[10px] font-bold uppercase tracking-wider">Libur</p>
                        <p class="mt-1 text-2xl font-black" id="header-holiday">{{ $classTotals['holiday'] }}</p>
                    </div>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm animate-fade-in-up">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700 shadow-sm animate-fade-in-up">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Legends -->
        <section class="mb-6 flex items-center gap-4 rounded-3xl glass-card p-5 animate-fade-in-up" style="animation-delay: 50ms;">
            <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider">
                <span class="rounded-lg bg-white px-2 py-1.5 border border-slate-200 text-slate-700">H Hadir</span>
                <span class="rounded-lg bg-amber-100 px-2 py-1.5 border border-amber-300 text-amber-800">S Sakit</span>
                <span class="rounded-lg bg-emerald-100 px-2 py-1.5 border border-emerald-300 text-emerald-800">I Izin</span>
                <span class="rounded-lg bg-red-100 px-2 py-1.5 border border-red-300 text-red-800">A Alpa</span>
                <span class="rounded-lg bg-blue-100 px-2 py-1.5 border border-blue-300 text-blue-800">L Libur</span>
            </div>
        </section>

        <!-- Student Search -->
        <div class="mb-6 rounded-3xl glass-card p-4 animate-fade-in-up" style="animation-delay: 100ms;">
            <label for="student-filter" class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Cari santri</label>
            <input
                id="student-filter"
                type="search"
                placeholder="Ketik nama atau NIS santri..."
                class="mt-2 w-full rounded-2xl border-2 border-slate-100 bg-white/50 px-4 py-2.5 text-sm font-semibold shadow-sm outline-none transition-all placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10"
            >
            @csrf
        </div>

        <!-- Attendance Grid -->
        <div x-data="attendanceManager('{{ route('guru.diniyyah-attendance.update-single', $assessmentSet) }}')" class="rounded-[2rem] glass-card shadow-sm overflow-hidden animate-fade-in-up relative" style="animation-delay: 150ms;">
            
            <div class="overflow-x-auto pb-20">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="sticky left-0 z-20 bg-slate-50 px-6 py-4 border-r border-slate-100">Santri</th>
                            @foreach ($meetings as $meeting)
                                <th class="px-3 py-3 text-center min-w-[60px] border-r border-slate-100/50">
                                    <span class="block font-black text-slate-700 text-sm">P{{ $meeting }}</span>
                                </th>
                            @endforeach
                            <th class="px-4 py-2 text-center bg-slate-50">S</th>
                            <th class="px-4 py-2 text-center bg-slate-50">I</th>
                            <th class="px-4 py-2 text-center bg-slate-50">A</th>
                            <th class="px-4 py-2 text-center bg-slate-50">L</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-rows" class="divide-y divide-slate-100">
                        @foreach ($enrollments as $enrollment)
                            @php
                                $totals = $studentTotals[$enrollment->id] ?? ['sick' => 0, 'permission' => 0, 'absent' => 0];
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors" data-student="{{ \Illuminate\Support\Str::lower($enrollment->student?->name.' '.$enrollment->student?->nis) }}">
                                <td class="sticky left-0 z-10 bg-white px-6 py-4 font-bold border-r border-slate-100">
                                    <div class="text-slate-900 text-sm font-extrabold">{{ $enrollment->student?->name }}</div>
                                    <div class="text-xs font-semibold text-slate-400 mt-0.5">NIS {{ $enrollment->student?->nis }}</div>
                                </td>
                                @foreach ($meetings as $meeting)
                                    @php
                                        $attendance = $attendances->get($enrollment->id.'-'.$meeting);
                                        $code = old('attendance.'.$enrollment->id.'.'.$meeting, \App\Models\StudentAttendance::codeFromStatus($attendance?->status));
                                    @endphp
                                    <td class="px-2 py-3 text-center border-r border-slate-50">
                                        <select
                                            x-model="attendances['{{ $enrollment->id }}_{{ $meeting }}']"
                                            x-init="attendances['{{ $enrollment->id }}_{{ $meeting }}'] = '{{ $code }}'"
                                            @change="updateAttendance('{{ $enrollment->id }}', '{{ $meeting }}')"
                                            class="h-9 w-12 rounded-xl border-2 text-center text-sm font-extrabold outline-none transition-all focus:ring-4 cursor-pointer"
                                            :class="{
                                                'bg-white border-slate-100 text-slate-700 focus:border-slate-300 focus:ring-slate-100': !attendances['{{ $enrollment->id }}_{{ $meeting }}'] || attendances['{{ $enrollment->id }}_{{ $meeting }}'] === 'H',
                                                'bg-amber-100 border-amber-300 text-amber-800 focus:border-amber-400 focus:ring-amber-200': attendances['{{ $enrollment->id }}_{{ $meeting }}'] === 'S',
                                                'bg-emerald-100 border-emerald-300 text-emerald-800 focus:border-emerald-400 focus:ring-emerald-200': attendances['{{ $enrollment->id }}_{{ $meeting }}'] === 'I',
                                                'bg-red-100 border-red-300 text-red-800 focus:border-red-400 focus:ring-red-200': attendances['{{ $enrollment->id }}_{{ $meeting }}'] === 'A',
                                                'bg-blue-100 border-blue-300 text-blue-800 focus:border-blue-400 focus:ring-blue-200': attendances['{{ $enrollment->id }}_{{ $meeting }}'] === 'L'
                                            }"
                                            @disabled(! $canUpdate)
                                        >
                                            <option value="H">H</option>
                                            <option value="S">S</option>
                                            <option value="I">I</option>
                                            <option value="A">A</option>
                                            <option value="L">L</option>
                                        </select>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-center font-black text-red-700 text-base bg-white" x-text="studentTotals['{{ $enrollment->id }}']?.sick || {{ $totals['sick'] }}"></td>
                                <td class="px-4 py-3 text-center font-black text-sky-700 text-base bg-white" x-text="studentTotals['{{ $enrollment->id }}']?.permission || {{ $totals['permission'] }}"></td>
                                <td class="px-4 py-3 text-center font-black text-slate-700 text-base bg-white" x-text="studentTotals['{{ $enrollment->id }}']?.absent || {{ $totals['absent'] }}"></td>
                                <td class="px-4 py-3 text-center font-black text-blue-700 text-base bg-white" x-text="studentTotals['{{ $enrollment->id }}']?.holiday || {{ $totals['holiday'] }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Footer Action Block -->
            <div class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-slate-200/60 bg-white/90 p-5 backdrop-blur-md">
                <p class="text-xs font-semibold text-slate-500">Hanya isi absen untuk pertemuan yang sudah Anda laksanakan. Kolom kosong (-) tidak dihitung.</p>
                <div class="flex items-center gap-2">
                    <span x-show="isSaving" x-transition class="flex items-center gap-1.5 text-xs font-bold text-amber-600">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Menyimpan...
                    </span>
                    <span x-show="!isSaving && lastSaved" x-transition class="flex items-center gap-1.5 text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Tersimpan otomatis
                    </span>
                    @if(!$canUpdate)
                    <span class="rounded-xl px-4 py-2 text-xs font-bold text-white bg-slate-350">
                        Mode Baca Saja
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('attendanceManager', (endpointUrl) => ({
                attendances: {},
                studentTotals: {},
                isSaving: false,
                lastSaved: null,
                
                init() {
                    // Initialize totals on mount
                    setTimeout(() => this.recalculateTotals(), 100);
                },

                async updateAttendance(enrollmentId, meeting_number) {
                    const code = this.attendances[`${enrollmentId}_${meeting_number}`];
                    this.isSaving = true;
                    
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                                      || document.querySelector('input[name="_token"]')?.value;

                        const response = await fetch(endpointUrl, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                class_enrollment_id: enrollmentId,
                                meeting_number: meeting_number,
                                code: code
                            })
                        });

                        if (!response.ok) throw new Error('Gagal menyimpan');
                        
                        this.lastSaved = new Date();
                        this.recalculateTotals();
                        
                    } catch (error) {
                        console.error('Error saving attendance:', error);
                        alert('Gagal menyimpan data ke server. Silakan periksa koneksi internet Anda.');
                    } finally {
                        this.isSaving = false;
                    }
                },

                recalculateTotals() {
                    let newTotals = {};
                    let classTotals = { sick: 0, permission: 0, absent: 0, holiday: 0 };
                    
                    // Group by enrollment
                    Object.entries(this.attendances).forEach(([key, code]) => {
                        if (!code || code === '') return;
                        
                        const enrollmentId = key.split('_')[0];
                        if (!newTotals[enrollmentId]) {
                            newTotals[enrollmentId] = { sick: 0, permission: 0, absent: 0, holiday: 0 };
                        }
                        
                        if (code === 'S') { newTotals[enrollmentId].sick++; classTotals.sick++; }
                        if (code === 'I') { newTotals[enrollmentId].permission++; classTotals.permission++; }
                        if (code === 'A') { newTotals[enrollmentId].absent++; classTotals.absent++; }
                        if (code === 'L') { newTotals[enrollmentId].holiday++; classTotals.holiday++; }
                    });
                    
                    this.studentTotals = newTotals;
                    
                    document.getElementById('header-sick').textContent = classTotals.sick;
                    document.getElementById('header-permission').textContent = classTotals.permission;
                    document.getElementById('header-absent').textContent = classTotals.absent;
                    document.getElementById('header-holiday').textContent = classTotals.holiday;
                }
            }));
        });

        const filter = document.getElementById('student-filter');
        const rows = Array.from(document.querySelectorAll('#attendance-rows tr'));

        filter?.addEventListener('input', () => {
            const value = filter.value.trim().toLowerCase();

            rows.forEach((row) => {
                row.hidden = value.length > 0 && ! row.dataset.student.includes(value);
            });
        });
    </script>
</body>
</html>
