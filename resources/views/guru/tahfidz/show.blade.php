<x-layouts.portal title="{{ $halaqah->name }} — Input Pekanan" portalLabel="Portal Guru" breadcrumb="Input Tahfidz Pekanan">
    <x-slot name="navLinks">
        <a href="{{ route('guru.tahfidz.uas', $halaqah) }}" class="btn btn-outline btn-sm hover:bg-slate-100 transition-colors">Mode UAS</a>
        <a href="{{ route('guru.tahfidz.index') }}" class="btn btn-outline btn-sm hidden sm:inline-flex hover:bg-slate-100 transition-colors">
            Daftar Halaqah
        </a>
    </x-slot>

    @push('scripts')
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endpush

    <!-- Header Section -->
    <header class="animate-fade-in-up mb-8 rounded-3xl glass-card p-6 sm:p-8 relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-amber-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-orange-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
        <div class="relative flex items-start justify-between gap-4 flex-wrap z-10">
            <div>
                <div class="inline-flex items-center gap-1.5 bg-gradient-to-r from-amber-100 to-orange-100 text-amber-800 rounded-full px-3 py-1 mb-3 text-[10px] font-black uppercase tracking-wider shadow-sm border border-amber-200">
                    Rekap Pekanan
                </div>
                <h1 class="text-3xl font-black text-slate-900 leading-tight mb-1">{{ $halaqah->name }}</h1>
                <p class="text-sm font-semibold text-slate-500 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    {{ $halaqah->teacher?->name }} &middot; {{ $halaqah->academicTerm?->name }}
                </p>
            </div>
        </div>
    </header>

    @if (session('status'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-sm font-semibold text-emerald-800 flex items-center gap-3 shadow-sm animate-fade-in-up">
            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center bg-emerald-100 rounded-full text-emerald-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </div>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('guru.tahfidz.update', $halaqah) }}">
        @csrf
        @method('PUT')

        {{-- Month Selector (Modern Pills) --}}
        <div class="flex flex-wrap gap-2 mb-6 animate-fade-in-up" style="animation-delay: 50ms;">
            @foreach ($availableMonths as $month)
                <a href="?month={{ $month['number'] }}" 
                   class="px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-300 transform hover:-translate-y-0.5 {{ $selectedMonth === $month['number'] ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/30 border-transparent' : 'bg-white text-slate-500 border border-slate-200 hover:border-amber-300 hover:text-amber-600 hover:shadow-md' }}">
                    {{ $month['label'] }}
                </a>
            @endforeach
        </div>

        @if ($monthWeeks->isEmpty())
            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white/50 p-12 text-center flex flex-col items-center justify-center animate-fade-in-up" style="animation-delay: 100ms;">
                <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4 text-slate-400">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                </div>
                <p class="text-sm font-bold text-slate-500">Belum ada pekan untuk bulan ini. PJ Tahfidz perlu mengatur pekan terlebih dahulu.</p>
            </div>
        @else
            {{-- Score Table --}}
            <div class="rounded-3xl glass-card border border-slate-200 shadow-xl shadow-slate-200/40 overflow-hidden mb-6 animate-fade-in-up" style="animation-delay: 100ms;">
                {{-- Table header info --}}
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                    </div>
                    <span class="text-xs font-bold text-slate-600">Klik kotak pada tiap pekan untuk mengisi surah, jumlah sabaq, nilai, dan catatan hafalan santri.</span>
                </div>
                
                <div class="overflow-x-auto w-full pb-2">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="bg-slate-50/80 border-b-2 border-slate-100">
                                <th class="hidden sm:table-cell px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-wider sticky left-0 z-20 bg-slate-50/90 backdrop-blur-sm border-r border-slate-100 w-16">No</th>
                                <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-wider sticky left-0 sm:left-16 z-20 bg-slate-50/90 backdrop-blur-sm border-r border-slate-100 min-w-[150px] sm:min-w-[200px] shadow-[4px_0_12px_rgba(0,0,0,0.02)]">Nama Santri</th>
                                @foreach ($monthWeeks as $week)
                                    <th class="px-4 py-4 text-center text-[11px] font-black text-slate-500 uppercase tracking-wider min-w-[180px] border-r border-slate-50 last:border-r-0">
                                        <div class="inline-flex flex-col items-center">
                                            <span class="text-amber-600">{{ $week->date_label ?? 'Pekan '.$week->week_number }}</span>
                                            <span class="text-[9px] text-slate-400 mt-0.5 font-bold">Ketuk untuk isi</span>
                                        </div>
                                    </th>
                                @endforeach
                                <th class="px-5 py-4 text-center text-xs font-black text-amber-700 uppercase tracking-wider bg-amber-50/90 border-l border-amber-200 min-w-[120px]">
                                    Rekap<br>Bulan Ini
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($members as $member)
                                <tr class="group hover:bg-slate-50/50 transition-colors {{ $loop->even ? 'bg-slate-50/30' : 'bg-white' }}">
                                    <td class="hidden sm:table-cell px-5 py-4 text-xs font-bold text-slate-400 sticky left-0 z-10 bg-inherit border-r border-slate-100">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-4 font-bold text-slate-800 sticky left-0 sm:left-16 z-10 bg-inherit border-r border-slate-100 shadow-[4px_0_12px_rgba(0,0,0,0.02)] group-hover:text-amber-700 transition-colors">
                                        {{ $member->student->name }}
                                    </td>
                                    @foreach ($monthWeeks as $week)
                                        @php $score = $scores->get($member->id.'-'.$week->id); @endphp
                                        <td class="p-2 border-r border-slate-50 last:border-r-0" x-data="tahfidzCell('{{ addslashes($score?->surah_ayat) }}', '{{ addslashes($score?->sabaq_amount) }}', '{{ $score?->score }}', '{{ addslashes($score?->notes) }}', '{{ $member->id }}', '{{ $week->id }}', '{{ route('guru.tahfidz.update-single', $halaqah) }}')">
                                            
                                            <!-- Clickable Summary Box -->
                                            <div @click="showModal = true" class="relative group/box cursor-pointer h-full min-h-[70px] w-full rounded-2xl border-2 flex flex-col items-center justify-center p-2 text-center transition-all duration-300"
                                                 :class="hasData ? 'border-amber-200 bg-amber-50/40 hover:bg-amber-100/60 hover:border-amber-400 hover:shadow-md' : 'border-dashed border-slate-200 bg-slate-50/50 hover:border-amber-300 hover:bg-amber-50/30 text-slate-400'">
                                                
                                                <div x-html="summaryHtml" class="w-full flex flex-col items-center justify-center gap-1"></div>
                                                
                                                <!-- Loading Indicator -->
                                                <div x-show="isSaving" class="absolute top-2 right-2 text-amber-500 bg-amber-100 p-1 rounded-full shadow-sm">
                                                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                                
                                                <!-- Success Indicator -->
                                                <div x-show="saveSuccess" class="absolute top-2 right-2 text-emerald-500 bg-emerald-100 p-1 rounded-full shadow-sm" x-transition.opacity>
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                </div>
                                            </div>
                                            
                                            <!-- Hidden Inputs for Form Submission -->
                                            <input type="hidden" name="scores[{{ $member->id }}][{{ $week->id }}][surah_ayat]" :value="combinedSurahAyat">
                                            <input type="hidden" name="scores[{{ $member->id }}][{{ $week->id }}][sabaq_amount]" :value="formattedSabaq">
                                            <input type="hidden" name="scores[{{ $member->id }}][{{ $week->id }}][score]" :value="(score === '-' || baris.toLowerCase() === 'murojaah') ? '' : score">
                                            <input type="hidden" name="scores[{{ $member->id }}][{{ $week->id }}][notes]" :value="notes">
                                            <input type="hidden" name="scores[{{ $member->id }}][{{ $week->id }}][category]" value="sabaq">
                                            
                                            <!-- Modern Modal -->
                                            <template x-teleport="body">
                                                <div x-show="showModal" style="display:none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" x-transition.opacity>
                                                    <!-- Backdrop -->
                                                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="cancelAndClose()"></div>
                                                    
                                                    <!-- Modal Content -->
                                                    <div class="relative bg-white rounded-[24px] w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-full" 
                                                         x-show="showModal" 
                                                         x-transition:enter="transition ease-out duration-300"
                                                         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave="transition ease-in duration-200"
                                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave-end="opacity-0 translate-y-8 scale-95">
                                                        
                                                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex justify-between items-center sticky top-0 z-10 backdrop-blur-md">
                                                            <div>
                                                                <h3 class="text-lg font-black text-slate-900 leading-tight">Input Hafalan Pekanan</h3>
                                                                <p class="text-xs font-bold text-slate-500 mt-1">{{ $member->student->name }} &middot; <span class="text-amber-600">{{ $week->date_label ?? 'Pekan '.$week->week_number }}</span></p>
                                                            </div>
                                                            <button type="button" @click="cancelAndClose()" class="p-2 bg-white rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shadow-sm border border-slate-200">
                                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                                            </button>
                                                        </div>
                                                        
                                                        <div class="p-6 space-y-6 overflow-y-auto">
                                                            <!-- Dari -->
                                                            <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                                                <div class="col-span-2 space-y-1.5">
                                                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Mulai Surat <span class="text-red-500">*</span></label>
                                                                    <select class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm" x-model="startSurah">
                                                                        <option value="">- Pilih Surah -</option>
                                                                        <option value="-">- (Murojaah / Kosong)</option>
                                                                        <template x-for="surah in quranSurahs" :key="surah">
                                                                            <option :value="surah" x-text="surah" :selected="startSurah === surah"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                                    <div class="space-y-1.5">
                                                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Ayat <span class="text-red-500">*</span></label>
                                                                        <input type="text" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm disabled:opacity-50 disabled:bg-slate-100" x-model="startAyat" x-on:input="startAyat = startAyat.replace(/[^0-9]/g, '')" :disabled="startSurah === '-'" placeholder="Mis: 1">
                                                                    </div>
                                                            </div>
                                                            
                                                            <!-- Sampai -->
                                                            <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                                                <div class="col-span-2 space-y-1.5">
                                                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Sampai Surat <span class="text-red-500">*</span></label>
                                                                    <select class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm" x-model="endSurah">
                                                                        <option value="">- Pilih Surah -</option>
                                                                        <option value="-">- (Murojaah / Kosong)</option>
                                                                        <template x-for="surah in quranSurahs" :key="surah">
                                                                            <option :value="surah" x-text="surah" :selected="endSurah === surah"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                                    <div class="space-y-1.5">
                                                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Ayat <span class="text-red-500">*</span></label>
                                                                        <input type="text" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm disabled:opacity-50 disabled:bg-slate-100" x-model="endAyat" x-on:input="endAyat = endAyat.replace(/[^0-9]/g, '')" :disabled="endSurah === '-'" placeholder="Mis: 5">
                                                                    </div>
                                                            </div>
                                                            
                                                            <!-- Sabaq & Nilai -->
                                                            <div class="grid grid-cols-2 gap-4">
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Jml Sabaq (Baris) <span class="text-red-500">*</span></label>
                                                                    <div class="relative">
                                                                        <input type="text" list="sabaqOptions-{{ $member->id }}-{{ $week->id }}" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm" x-model="baris" placeholder="Mis: 18 / Murojaah">
                                                                        <datalist id="sabaqOptions-{{ $member->id }}-{{ $week->id }}">
                                                                            <option value="Murojaah">
                                                                        </datalist>
                                                                        <div class="absolute -bottom-5 left-2 text-[10px] font-black text-amber-600" x-text="formattedSabaq"></div>
                                                                    </div>
                                                                </div>
                                                                    <div class="space-y-1.5">
                                                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Nilai (1-100) <span class="text-red-500">*</span></label>
                                                                        <input type="text" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm disabled:opacity-50 disabled:bg-slate-100" x-model="score" x-on:input="score = score.replace(/[^0-9]/g, '')" :disabled="baris && baris.toLowerCase() === 'murojaah'" placeholder="Mis: 85">
                                                                    </div>
                                                            </div>
                                                            
                                                            <!-- Catatan -->
                                                            <div class="space-y-1.5 pt-2">
                                                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Catatan Evaluasi (Opsional)</label>
                                                                <input type="text" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all shadow-sm" x-model="notes" placeholder="Tuliskan evaluasi hafalan santri...">
                                                            </div>
                                                        </div>
                                                        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex justify-between items-center mt-auto gap-2">
                                                            <button type="button" @click="clearForm()" class="px-4 py-2 bg-red-50 text-red-600 rounded-xl text-xs font-bold hover:bg-red-100 transition-colors">Kosongkan</button>
                                                            <div class="flex gap-2">
                                                                <button type="button" @click="cancelAndClose()" class="px-4 py-2.5 bg-white text-slate-600 border border-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Batal</button>
                                                                <button type="button" @click="closeAndSave()" class="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold hover:bg-slate-800 transition-colors shadow-md shadow-slate-900/20">
                                                                    Simpan
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>
                                    @endforeach
                                    
                                    <td class="px-3 py-4 text-center bg-amber-50/40 border-l border-amber-200">
                                        @php $recap = $monthlyRecaps->get($member->id); @endphp
                                        @if($recap && ($recap->average_score !== null || $recap->sabaq_monthly_baris > 0))
                                            <div class="flex flex-col items-center justify-center gap-1">
                                                <div class="text-[9px] font-black text-amber-600/70 uppercase tracking-wider">Rata-rata</div>
                                                <div class="text-sm font-black text-amber-800">{{ $recap->average_score ?? '-' }}</div>
                                                <div class="text-[9px] font-bold text-amber-700 mt-1 bg-amber-100/50 px-2 py-0.5 rounded-full border border-amber-200/50">{{ $recap->sabaq_monthly ?? '0 Baris' }}</div>
                                            </div>
                                        @else
                                            <div class="text-xs font-bold text-amber-600/30">-</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mb-10 animate-fade-in-up" style="animation-delay: 150ms;">
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-bold text-sm hover:from-amber-600 hover:to-orange-600 transition-all shadow-lg shadow-amber-500/30 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Simpan Semua Perubahan
                </button>
            </div>
        @endif
    </form>

    @push('scripts')
    <script>
        const quranSurahs = [
            "Al-Fatihah", "Al-Baqarah", "Ali 'Imran", "An-Nisa'", "Al-Ma'idah", "Al-An'am", "Al-A'raf", "Al-Anfal", "At-Taubah", "Yunus", "Hud", "Yusuf", "Ar-Ra'd", "Ibrahim", "Al-Hijr", "An-Nahl", "Al-Isra'", "Al-Kahf", "Maryam", "Taha", "Al-Anbiya'", "Al-Hajj", "Al-Mu'minun", "An-Nur", "Al-Furqan", "Asy-Syu'ara'", "An-Naml", "Al-Qasas", "Al-'Ankabut", "Ar-Rum", "Luqman", "As-Sajdah", "Al-Ahzab", "Saba'", "Fatir", "Yasin", "As-Saffat", "Sad", "Az-Zumar", "Gafir", "Fussilat", "Asy-Syura", "Az-Zukhruf", "Ad-Dukhan", "Al-Jasiyah", "Al-Ahqaf", "Muhammad", "Al-Fath", "Al-Hujurat", "Qaf", "Az-Zariyat", "At-Tur", "An-Najm", "Al-Qamar", "Ar-Rahman", "Al-Waqi'ah", "Al-Hadid", "Al-Mujadalah", "Al-Hasyr", "Al-Mumtahanah", "As-Saff", "Al-Jumu'ah", "Al-Munafiqun", "At-Tagabun", "At-Talaq", "At-Tahrim", "Al-Mulk", "Al-Qalam", "Al-Haqqah", "Al-Ma'arij", "Nuh", "Al-Jinn", "Al-Muzzammil", "Al-Muddassir", "Al-Qiyamah", "Al-Insan", "Al-Mursalat", "An-Naba'", "An-Nazi'at", "'Abasa", "At-Takwir", "Al-Infitar", "Al-Mutaffifin", "Al-Insyiqaq", "Al-Buruj", "At-Tariq", "Al-A'la", "Al-Gasyiyah", "Al-Fajr", "Al-Balad", "Asy-Syams", "Al-Lail", "Ad-Duha", "Asy-Syarh", "At-Tin", "Al-'Alaq", "Al-Qadr", "Al-Bayyinah", "Az-Zalzalah", "Al-'Adiyat", "Al-Qari'ah", "At-Takasur", "Al-'Asr", "Al-Humazah", "Al-Fil", "Quraisy", "Al-Ma'un", "Al-Kausar", "Al-Kafirun", "An-Nasr", "Al-Lahab", "Al-Ikhlas", "Al-Falaq", "An-Naas"
        ];

        document.addEventListener('alpine:init', () => {
            Alpine.data('tahfidzCell', (initialSurahAyat, initialSabaqAmount, initialScore, initialNotes, memberId, weekId, updateUrl) => {
                let sSurah = '', sAyat = '', eSurah = '', eAyat = '';
                
                // Parser
                if (initialSurahAyat) {
                        if (initialSurahAyat === '-') {
                            sSurah = '-'; sAyat = '';
                            eSurah = '-'; eAyat = '';
                        } else {
                        let parts = initialSurahAyat.split(' - ');
                        let startParts = parts[0].split(':');
                        sSurah = startParts[0].trim();
                        sAyat = startParts[1] ? startParts[1].trim() : '';
                        
                        if (parts.length > 1) {
                            let endParts = parts[1].split(':');
                            eSurah = endParts[0].trim();
                            eAyat = endParts[1] ? endParts[1].trim() : '';
                        }
                    }
                }
                
                if (sSurah && sSurah !== '-' && !quranSurahs.includes(sSurah)) sSurah = '';
                if (eSurah && eSurah !== '-' && !quranSurahs.includes(eSurah)) eSurah = '';

                let parsedScore = initialScore ? Math.round(parseFloat(initialScore)).toString() : '';
                if (initialSabaqAmount && initialSabaqAmount.toLowerCase() === 'murojaah') {
                    parsedScore = '-';
                }

                return {
                    showModal: false,
                    isSaving: false,
                    saveSuccess: false,
                    startSurah: sSurah,
                    startAyat: sAyat,
                    endSurah: eSurah,
                    endAyat: eAyat,
                    baris: initialSabaqAmount ? initialSabaqAmount.replace(/[^0-9]/g, '') : '',
                    score: parsedScore,
                    notes: initialNotes || '',
                    memberId: memberId,
                    weekId: weekId,
                    updateUrl: updateUrl,
                    _originalData: '',
                    
                    init() {
                        this._originalData = this.currentDataString();
                        
                        this.$watch('startSurah', value => {
                            if (value === '-') this.startAyat = '';
                        });
                        this.$watch('endSurah', value => {
                            if (value === '-') this.endAyat = '';
                        });
                        this.$watch('baris', value => {
                            if (value && value.toLowerCase() === 'murojaah') this.score = '-';
                            else if (this.score === '-') this.score = '';
                        });
                    },
                    
                    cancelAndClose() {
                        this.revertToOriginal();
                        this.showModal = false;
                    },

                    revertToOriginal() {
                        let orig = JSON.parse(this._originalData);
                        
                        let initialSurahAyat = orig.surah_ayat;
                        if (initialSurahAyat) {
                            if (initialSurahAyat === '-') {
                                this.startSurah = '-'; this.startAyat = '';
                                this.endSurah = '-'; this.endAyat = '';
                            } else {
                                let parts = initialSurahAyat.split(' - ');
                                let startParts = parts[0].split(':');
                                this.startSurah = startParts[0].trim();
                                this.startAyat = startParts[1] ? startParts[1].trim() : '';
                                
                                if (parts.length > 1) {
                                    let endParts = parts[1].split(':');
                                    this.endSurah = endParts[0].trim();
                                    this.endAyat = endParts[1] ? endParts[1].trim() : '';
                                } else {
                                    this.endSurah = ''; this.endAyat = '';
                                }
                            }
                        } else {
                            this.startSurah = ''; this.startAyat = '';
                            this.endSurah = ''; this.endAyat = '';
                        }
                        
                        if (this.startSurah && this.startSurah !== '-' && !quranSurahs.includes(this.startSurah)) this.startSurah = '';
                        if (this.endSurah && this.endSurah !== '-' && !quranSurahs.includes(this.endSurah)) this.endSurah = '';

                        this.baris = orig.sabaq_amount ? orig.sabaq_amount.replace(/[^0-9]/g, '') : '';
                        if (orig.sabaq_amount && orig.sabaq_amount.toLowerCase() === 'murojaah') {
                            this.baris = 'Murojaah';
                        }
                        
                        this.score = orig.score ? Math.round(parseFloat(orig.score)).toString() : '';
                        if (this.baris.toLowerCase() === 'murojaah') this.score = '-';
                        
                        this.notes = orig.notes || '';
                    },

                    closeAndSave() {
                        if (this.currentDataString() === this._originalData) {
                            this.showModal = false;
                            return;
                        }

                        const isEmpty = !this.startSurah && !this.startAyat && !this.endSurah && !this.endAyat && !this.baris && (this.score === '' || this.score === null) && !this.notes;
                        
                        if (!isEmpty) {
                            if (!this.startSurah) { alert("Nama Surat Dari wajib diisi!"); return; }
                            if (this.startSurah !== '-' && !this.startAyat) { alert("Dari Ayat wajib diisi dengan angka!"); return; }
                            if (!this.endSurah) { alert("Nama Surat Sampai wajib diisi (Pilih '-' jika kosong)!"); return; }
                            if (this.endSurah !== '-' && !this.endAyat) { alert("Sampai Ayat wajib diisi dengan angka!"); return; }
                            if (!this.baris) { alert("Jumlah Sabaq wajib diisi!"); return; }
                            if (this.score === '' || this.score === null) { alert("Nilai wajib diisi!"); return; }
                        }
                        
                        this.autoSave();
                    },
                    
                    currentDataString() {
                        return JSON.stringify({
                            surah_ayat: this.combinedSurahAyat,
                            sabaq_amount: this.formattedSabaq,
                            score: this.score,
                            notes: this.notes
                        });
                    },
                    
                    clearForm() {
                        this.startSurah = ''; this.startAyat = '';
                        this.endSurah = ''; this.endAyat = '';
                        this.baris = ''; this.score = ''; this.notes = '';
                    },
                    
                    async autoSave() {
                        this.isSaving = true;
                        this.saveSuccess = false;
                        
                        try {
                            const submitScore = (this.score === '-' || this.baris.toLowerCase() === 'murojaah') ? null : this.score;
                            
                            const response = await fetch(this.updateUrl, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                                },
                                body: JSON.stringify({
                                    week_id: this.weekId,
                                    member_id: this.memberId,
                                    surah_ayat: this.combinedSurahAyat,
                                    sabaq_amount: this.formattedSabaq,
                                    score: submitScore,
                                    notes: this.notes,
                                    category: 'sabaq'
                                })
                            });
                            
                            if (response.ok) {
                                this.saveSuccess = true;
                                this._originalData = this.currentDataString();
                                setTimeout(() => this.saveSuccess = false, 3000);
                            } else {
                                console.error(await response.text());
                                alert('Gagal menyimpan data otomatis. Silakan periksa isian atau gunakan tombol Simpan Data Pekanan.');
                            }
                        } catch (error) {
                            console.error(error);
                        } finally {
                            this.isSaving = false;
                            this.showModal = false;
                        }
                    },
                    
                    get hasData() {
                        return this.startSurah || this.baris || this.score || this.notes;
                    },

                    get combinedSurahAyat() {
                        if (this.startSurah === '-' && this.endSurah === '-') return '-';
                        if (!this.startSurah) return '';
                        let combined = this.startSurah;
                        if (this.startAyat) combined += ':' + this.startAyat;
                        if (this.endSurah && this.endSurah !== '-') {
                            combined += ' - ' + this.endSurah;
                            if (this.endAyat) combined += ':' + this.endAyat;
                        }
                        return combined;
                    },

                    get formattedSabaq() {
                        if (!this.baris) return '';
                        if (this.baris.toLowerCase() === 'murojaah') return 'Murojaah';
                        let b = parseInt(this.baris);
                        if (isNaN(b) || b <= 0) return this.baris;
                        let hal = Math.floor(b / 15);
                        let sisa = b % 15;
                        let str = [];
                        if (hal > 0) str.push(hal + ' Halaman');
                        if (sisa > 0) str.push(sisa + ' Baris');
                        return str.join(' ');
                    },

                    get summaryHtml() {
                        if (!this.hasData) {
                            return `
                                <div class="text-slate-300 group-hover/box:text-amber-400 transition-colors flex flex-col items-center">
                                    <svg class="w-5 h-5 mb-1 opacity-50 group-hover/box:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    <span class="text-[10px] font-bold">Isi Data</span>
                                </div>
                            `;
                        }
                        
                        let parts = [];
                        if (this.combinedSurahAyat) {
                            parts.push(`<span class="font-bold text-slate-800 text-[11px] leading-tight break-words max-w-[150px] line-clamp-2">${this.combinedSurahAyat}</span>`);
                        }
                        if (this.formattedSabaq) {
                            parts.push(`<span class="text-amber-600 font-bold text-[9px] uppercase tracking-wider">${this.formattedSabaq}</span>`);
                        }
                        if (this.score) {
                            parts.push(`<span class="inline-block mt-1 bg-gradient-to-r from-amber-100 to-orange-100 text-amber-800 px-2 py-0.5 rounded border border-amber-200 text-[10px] font-black shadow-sm">Nilai: ${this.score}</span>`);
                        }
                        return parts.join('');
                    }
                }
            })
        });
    </script>
    @endpush
</x-layouts.portal>