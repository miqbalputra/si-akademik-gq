<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pantau Jurnal Kelas - SIAKAD Griya Qur'an</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>body { font-family: 'Outfit', sans-serif; background-color: #fafafa; } .glass-card { background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05); }</style>
</head>
<body class="min-h-screen text-slate-800 antialiased p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center glass-card p-5 rounded-3xl gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Pemantauan Jurnal Kelas Diniyyah</h1>
                <p class="text-sm text-slate-500">Monitor pengisian jurnal oleh Guru Diniyyah berdasarkan jadwal.</p>
            </div>
            <a href="{{ route('guru.dashboard') }}" class="text-sm font-bold text-slate-500 hover:text-indigo-600 bg-slate-100 px-4 py-2 rounded-xl text-center">Ke Dashboard Wali Kelas</a>
        </div>
        
        <!-- Filter Bulan/Tahun -->
        <form method="GET" class="mb-6 glass-card p-5 rounded-3xl flex flex-col gap-4">
            <!-- Row 1: Month & Year & Buttons -->
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bulan</label>
                    <select name="month" class="rounded-xl border-slate-200 bg-white px-4 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $m)
                            <option value="{{ $index + 1 }}" {{ $month == ($index + 1) ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tahun</label>
                    <select name="year" class="rounded-xl border-slate-200 bg-white px-4 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                
                <div class="flex items-center gap-2 ml-auto flex-wrap justify-end">
                    <!-- Export PDF -->
                    <button type="submit" formaction="{{ route('wali.diniyyah-journals.export-pdf') }}" formtarget="_blank" class="bg-red-50 hover:bg-red-600 text-red-600 hover:text-white border border-red-200 hover:border-red-600 font-bold py-2 px-4 rounded-xl text-sm transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        PDF
                    </button>
                    <!-- Export Excel -->
                    <button type="submit" formaction="{{ route('wali.diniyyah-journals.export-excel') }}" class="bg-green-50 hover:bg-green-600 text-green-600 hover:text-white border border-green-200 hover:border-green-600 font-bold py-2 px-4 rounded-xl text-sm transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        Excel
                    </button>
                    <div class="w-px h-8 bg-slate-200 mx-1"></div>
                    <a href="{{ route('wali.diniyyah-journals.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2 px-4 rounded-xl text-sm transition-colors">Reset Filter</a>
                    <button type="submit" formaction="{{ route('wali.diniyyah-journals.index') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-xl text-sm transition-colors shadow-sm">Tampilkan</button>
                </div>
            </div>
            
            <!-- Row 2: Detailed Filters -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t border-slate-100 pt-4 mt-2">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kelas</label>
                    <select name="classroom_term_id" onchange="this.form.submit()" class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Semua Kelas --</option>
                        @foreach($classOptions as $id => $name)
                            <option value="{{ $id }}" {{ ($filterClassroomTermId ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mata Pelajaran</label>
                    <select name="subject_id" onchange="this.form.submit()" class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Semua Mapel --</option>
                        @foreach($subjectOptions as $id => $subject)
                            <option value="{{ $id }}" {{ ($filterSubjectId ?? '') == $id ? 'selected' : '' }}>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Guru Diniyyah</label>
                    <select name="teacher_id" onchange="this.form.submit()" class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Semua Guru --</option>
                        @foreach($teacherOptions as $id => $t)
                            <option value="{{ $id }}" {{ ($filterTeacherId ?? '') == $id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Status Pengisian</label>
                    <select name="status" onchange="this.form.submit()" class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Semua Status --</option>
                        <option value="TERISI" {{ ($filterStatus ?? '') == 'TERISI' ? 'selected' : '' }}>Sudah Terisi</option>
                        <option value="KOSONG" {{ ($filterStatus ?? '') == 'KOSONG' ? 'selected' : '' }}>Kosong (Belum Diisi)</option>
                        <option value="TERISI_TIDAK_TERJADWAL" {{ ($filterStatus ?? '') == 'TERISI_TIDAK_TERJADWAL' ? 'selected' : '' }}>Terisi (Di Luar Jadwal)</option>
                        <option value="LIBUR" {{ ($filterStatus ?? '') == 'LIBUR' ? 'selected' : '' }}>Diliburkan (Hari Libur)</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="space-y-6">
            @forelse ($monitoringData as $dayData)
                <div class="glass-card rounded-3xl overflow-hidden border {{ $dayData['is_holiday'] ? 'border-slate-200' : 'border-indigo-100' }}">
                    <!-- Header Tanggal -->
                    <div class="px-6 py-4 {{ $dayData['is_holiday'] ? 'bg-slate-100' : 'bg-indigo-50/50' }} border-b {{ $dayData['is_holiday'] ? 'border-slate-200' : 'border-indigo-100' }} flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex flex-col justify-center items-center {{ $dayData['is_holiday'] ? 'bg-slate-200 text-slate-500' : 'bg-indigo-600 text-white' }}">
                                <span class="text-[10px] font-bold uppercase leading-none">{{ $dayData['date']->translatedFormat('D') }}</span>
                                <span class="text-lg font-black leading-none mt-0.5">{{ $dayData['date']->format('d') }}</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">{{ $dayData['date']->translatedFormat('l, d F Y') }}</h3>
                                @if($dayData['is_holiday'])
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ $dayData['holiday_name'] ?? 'Hari Libur' }}</span>
                                @else
                                    <span class="text-xs font-semibold text-indigo-600">{{ count($dayData['items']) }} Jadwal Mengajar</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200">
                                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Jam Sesi</th>
                                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Kelas & Mapel</th>
                                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Guru Pengajar</th>
                                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Status</th>
                                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Jurnal & Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($dayData['items'] as $item)
                                    <tr class="hover:bg-slate-50/50 transition-colors {{ $item['status'] === 'KOSONG' ? 'bg-red-50/30' : '' }}">
                                        
                                        <!-- Kolom Jam Sesi -->
                                        <td class="py-4 px-4 align-top whitespace-nowrap">
                                            <div class="flex flex-col gap-1">
                                                <span class="inline-block px-2.5 py-1 text-xs font-black bg-slate-100 text-slate-700 rounded-lg text-center w-fit">Jam {{ $item['schedule']->classSession->session_name ?? '?' }}</span>
                                                @if($item['schedule']->classSession->starts_at)
                                                    <span class="text-[11px] font-semibold text-slate-500">{{ \Carbon\Carbon::parse($item['schedule']->classSession->starts_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($item['schedule']->classSession->ends_at)->format('H:i') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        
                                        <!-- Kolom Kelas & Mapel -->
                                        <td class="py-4 px-4 align-top">
                                            <p class="font-bold text-slate-800 text-sm leading-tight">{{ $item['schedule']->teacherAssignment->classSubject->classroomTerm->name ?? 'Kelas' }}</p>
                                            <p class="text-xs font-semibold text-slate-500 mt-1">{{ $item['schedule']->teacherAssignment->classSubject->subject->name ?? 'Mapel' }}</p>
                                        </td>
                                        
                                        <!-- Kolom Guru -->
                                        <td class="py-4 px-4 align-top">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs">
                                                    {{ substr($item['schedule']->teacherAssignment->teacher->name ?? 'G', 0, 1) }}
                                                </div>
                                                <span class="text-sm font-semibold text-slate-700">{{ $item['schedule']->teacherAssignment->teacher->name ?? '-' }}</span>
                                            </div>
                                        </td>
                                        
                                        <!-- Kolom Status -->
                                        <td class="py-4 px-4 align-top text-center whitespace-nowrap">
                                            @if($item['status'] === 'TERISI')
                                                <span class="inline-flex text-[11px] font-black uppercase tracking-wide px-3 py-1.5 rounded-xl bg-emerald-100 text-emerald-700 border border-emerald-200">Terisi</span>
                                            @elseif($item['status'] === 'TERISI_TIDAK_TERJADWAL')
                                                <span class="inline-flex text-[11px] font-black uppercase tracking-wide px-3 py-1.5 rounded-xl bg-blue-100 text-blue-700 border border-blue-200">Terisi (Ekstra)</span>
                                            @elseif($item['status'] === 'LIBUR')
                                                <span class="inline-flex text-[11px] font-black uppercase tracking-wide px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 border border-slate-200">Libur</span>
                                            @else
                                                <span class="inline-flex text-[11px] font-black uppercase tracking-wide px-3 py-1.5 rounded-xl bg-red-100 text-red-700 border border-red-200 shadow-sm animate-pulse">Kosong</span>
                                            @endif
                                        </td>
                                        
                                        <!-- Kolom Jurnal & Kehadiran -->
                                        <td class="py-4 px-4 align-top min-w-[280px]">
                                            @if(in_array($item['status'], ['TERISI', 'TERISI_TIDAK_TERJADWAL']) && $item['journal'])
                                                <div class="space-y-3">
                                                    <div>
                                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-0.5">Materi Pembelajaran</span>
                                                        <p class="text-xs font-semibold text-slate-700 bg-white border border-slate-100 rounded-lg p-2">{{ $item['journal']->material }}</p>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-1">Status Kehadiran Santri</span>
                                                        @if($item['journal']->absences->isEmpty())
                                                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-md">
                                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                                                Hadir Semua
                                                            </span>
                                                        @else
                                                            <div class="flex flex-wrap gap-1.5">
                                                                @foreach($item['journal']->absences as $absence)
                                                                    <span class="text-[10px] bg-amber-50 border border-amber-200 text-amber-800 px-2 py-1 rounded-md font-bold flex items-center gap-1">
                                                                        {{ $absence->classEnrollment->student->name }} 
                                                                        <span class="opacity-60">({{ $absence->status === 'skipped' ? 'Bolos' : ucfirst($absence->status) }})</span>
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs italic text-slate-400 font-medium">- Tidak ada data jurnal -</span>
                                            @endif
                                        </td>
                                        
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center p-10 glass-card rounded-3xl text-slate-500 font-medium flex flex-col items-center">
                    Belum ada riwayat jadwal pada bulan ini.
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
