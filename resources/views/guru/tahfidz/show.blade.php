<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $halaqah->name }} — Input Pekanan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Outfit','sans-serif']},colors:{brand:{50:'#fffbeb',100:'#fef3c7',500:'#f59e0b',600:'#d97706',700:'#b45309'}}}}}</script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; -webkit-font-smoothing: antialiased; }
        .bg-grid { background-size: 40px 40px; background-image: linear-gradient(to right, rgba(0,0,0,.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,.025) 1px, transparent 1px); }
        .portal-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.95); backdrop-filter: blur(16px); border-bottom: 1px solid #f1f5f9; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .btn-primary { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 14px; border-radius: 10px; padding: 10px 24px; background: #d97706; color: #fff; border: none; cursor: pointer; transition: all .2s; box-shadow: 0 2px 8px rgba(217,119,6,.3); }
        .btn-primary:hover { background: #b45309; transform: translateY(-1px); }
        .btn-ghost { background: transparent; border: 1.5px solid #e2e8f0; color: #475569; border-radius: 10px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: 5px; font-family: 'Outfit', sans-serif; }
        .btn-ghost:hover { background: #f8fafc; border-color: #fde68a; color: #d97706; }
        .month-tab { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .15s; border: 1.5px solid #e2e8f0; color: #64748b; background: #fff; }
        .month-tab:hover { border-color: #fde68a; color: #d97706; }
        .month-tab.active { background: #d97706; color: #fff; border-color: #d97706; box-shadow: 0 2px 8px rgba(217,119,6,.25); }
        .score-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 5px 8px; font-size: 12px; font-family: 'Outfit', sans-serif; color: #1e293b; background: #f8fafc; outline: none; transition: border-color .15s, background .15s; margin-bottom: 4px; }
        .score-input:focus { border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.1); }
        .score-input:last-child { margin-bottom: 0; }
        .th-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; }
        @keyframes fadeInUp { 0%{opacity:0;transform:translateY(14px)} 100%{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeInUp .5s cubic-bezier(.16,1,.3,1) forwards; opacity: 0; }
        .summary-box { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px; cursor: pointer; transition: all .2s; min-height: 60px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; font-weight: 600; color: #475569; }
        .summary-box:hover { border-color: #f59e0b; background: #fffbeb; color: #d97706; }
        .summary-box.filled { background: #fff; border-color: #d97706; color: #b45309; }
        .modal-overlay { position: fixed; inset: 0; z-index: 100; background: rgba(15,23,42,.4); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .modal-card { background: #fff; border-radius: 20px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px -10px rgba(0,0,0,.1); overflow: hidden; transform: scale(1); transition: all .2s; }
        .modal-header { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafaf8; }
        .modal-body { padding: 24px; display: grid; gap: 16px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; background: #fafaf8; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .form-select, .form-input { padding: 10px 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; font-family: 'Outfit', sans-serif; font-size: 13px; font-weight: 500; color: #0f172a; width: 100%; transition: all .2s; background: #f8fafc; }
        .form-select:focus, .form-input:focus { outline: none; border-color: #f59e0b; background: #fff; box-shadow: 0 0 0 3px rgba(245,158,11,.15); }
    </style>
    @include('partials.pwa-head')
</head>
<body>
    <div class="fixed inset-0 z-[-1] bg-grid opacity-50"></div>

    {{-- Nav --}}
    <nav class="portal-nav">
        <div style="max-width:1200px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <span style="width:36px;height:36px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:#fff;">GQ</span>
                <div>
                    <span style="display:block;font-size:14px;font-weight:800;color:#0f172a;line-height:1.2;">Griya Qur'an</span>
                    <span style="display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#d97706;">Input Tahfidz Pekanan</span>
                </div>
            </a>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('guru.tahfidz.uas', $halaqah) }}" class="btn-ghost">Mode UAS</a>
                <a href="{{ route('guru.tahfidz.index') }}" class="btn-ghost">
                    <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Daftar Halaqah
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </div>
        </div>
    </nav>

    <main style="max-width:1200px;margin:0 auto;padding:28px 24px;">

        {{-- Header --}}
        <header class="fade-up" style="margin-bottom:24px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <div style="display:inline-flex;align-items:center;gap:6px;background:#fef3c7;border-radius:999px;padding:3px 12px;margin-bottom:10px;">
                        <span style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;">Rekap Pekanan</span>
                    </div>
                    <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">{{ $halaqah->name }}</h1>
                    <p style="font-size:13px;color:#64748b;font-weight:500;margin:0;">{{ $halaqah->teacher?->name }} &middot; {{ $halaqah->academicTerm?->name }}</p>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div style="margin-bottom:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:8px;">
                <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('guru.tahfidz.update', $halaqah) }}">
            @csrf
            @method('PUT')

            {{-- Month Selector --}}
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;" class="fade-up">
                @foreach ($availableMonths as $month)
                    <a href="?month={{ $month['number'] }}" class="month-tab {{ $selectedMonth === $month['number'] ? 'active' : '' }}">
                        {{ $month['label'] }}
                    </a>
                @endforeach
            </div>

            @if ($monthWeeks->isEmpty())
                <div class="card" style="padding:40px;text-align:center;">
                    <svg style="width:36px;height:36px;color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    <p style="font-size:13px;font-weight:600;color:#94a3b8;">Belum ada pekan untuk bulan ini. PJ Tahfidz perlu mengatur pekan terlebih dahulu.</p>
                </div>
            @else
                {{-- Score Table --}}
                <div class="card fade-up" style="overflow:hidden;margin-bottom:20px;">
                    {{-- Table header info --}}
                    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;background:#fafaf8;display:flex;align-items:center;gap:8px;">
                        <svg style="width:15px;height:15px;color:#d97706;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <span style="font-size:12px;font-weight:600;color:#64748b;">Isi surah:ayat, jumlah baris, nilai (0–100), dan catatan untuk setiap pekan.</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table style="min-width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f8fafc;border-bottom:2px solid #f1f5f9;">
                                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;white-space:nowrap;">No</th>
                                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;min-width:160px;">Nama Santri</th>
                                    @foreach ($monthWeeks as $week)
                                        <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;min-width:140px;border-left:1px solid #f1f5f9;">
                                            {{ $week->date_label ?? 'Pekan '.$week->week_number }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr style="border-top:1px solid #f8fafc;{{ $loop->even ? 'background:#fafaf8;' : 'background:#fff;' }}">
                                        <td style="padding:12px 16px;color:#94a3b8;font-size:12px;font-weight:600;">{{ $loop->iteration }}</td>
                                        <td style="padding:12px 16px;font-weight:700;color:#0f172a;">{{ $member->student->name }}</td>
                                        @foreach ($monthWeeks as $week)
                                            @php $score = $scores->get($member->id.'-'.$week->id); @endphp
                                            <td style="padding:10px 12px;border-left:1px solid #f1f5f9;vertical-align:top;" x-data="tahfidzCell('{{ addslashes($score?->surah_ayat) }}', '{{ addslashes($score?->sabaq_amount) }}', '{{ $score?->score }}', '{{ addslashes($score?->notes) }}', '{{ $member->id }}', '{{ $week->id }}', '{{ route('guru.tahfidz.update-single', $halaqah) }}')">
                                                
                                                <!-- Clickable Summary Box -->
                                                <div @click="showModal = true" class="summary-box relative" :class="hasData ? 'filled' : ''">
                                                    <span x-html="summaryHtml"></span>
                                                    
                                                    <!-- Loading Indicator -->
                                                    <div x-show="isSaving" class="absolute top-2 right-2 text-amber-500">
                                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                    </div>
                                                    
                                                    <!-- Success Indicator -->
                                                    <div x-show="saveSuccess" class="absolute top-2 right-2 text-emerald-500" x-transition.opacity>
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
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
                                                
                                                <!-- Pop-up Modal -->
                                                <template x-teleport="body">
                                                    <div x-show="showModal" style="display:none;" class="modal-overlay" x-transition.opacity>
                                                        <div @click.outside="closeAndSave()" class="modal-card" x-show="showModal" x-transition.scale.origin.bottom>
                                                            <div class="modal-header">
                                                                <div>
                                                                    <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;">Input Hafalan Pekanan</h3>
                                                                    <p style="margin:2px 0 0;font-size:11px;font-weight:600;color:#64748b;">{{ $member->student->name }} &middot; {{ $week->date_label ?? 'Pekan '.$week->week_number }}</p>
                                                                </div>
                                                                <button type="button" @click="closeAndSave()" style="background:transparent;border:none;cursor:pointer;color:#94a3b8;"><svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <!-- Dari -->
                                                                <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                                                                    <div class="form-group">
                                                                        <label class="form-label">Nama Surat Dari <span style="color:#ef4444;">*</span></label>
                                                                        <select class="form-select" x-model="startSurah">
                                                                            <option value="">- Pilih Surah -</option>
                                                                            <option value="-">- (Murojaah / Kosong)</option>
                                                                            <template x-for="surah in quranSurahs" :key="surah">
                                                                                <option :value="surah" x-text="surah" :selected="startSurah === surah"></option>
                                                                            </template>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Dari Ayat <span style="color:#ef4444;">*</span></label>
                                                                        <input type="text" class="form-input" x-model="startAyat" :disabled="startSurah === '-'" placeholder="Mis: 1">
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Sampai -->
                                                                <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                                                                    <div class="form-group">
                                                                        <label class="form-label">Nama Surat Sampai <span style="color:#ef4444;">*</span></label>
                                                                        <select class="form-select" x-model="endSurah">
                                                                            <option value="">- Pilih Surah -</option>
                                                                            <option value="-">- (Murojaah / Kosong)</option>
                                                                            <template x-for="surah in quranSurahs" :key="surah">
                                                                                <option :value="surah" x-text="surah" :selected="endSurah === surah"></option>
                                                                            </template>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Sampai Ayat <span style="color:#ef4444;">*</span></label>
                                                                        <input type="text" class="form-input" x-model="endAyat" :disabled="endSurah === '-'" placeholder="Mis: 5">
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Sabaq & Nilai -->
                                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                                                    <div class="form-group">
                                                                        <label class="form-label">Jumlah Sabaq (Baris) <span style="color:#ef4444;">*</span></label>
                                                                        <div style="position:relative;">
                                                                            <input type="text" list="sabaqOptions-{{ $member->id }}-{{ $week->id }}" class="form-input" x-model="baris" placeholder="Mis: 18 atau 'Murojaah'">
                                                                            <datalist id="sabaqOptions-{{ $member->id }}-{{ $week->id }}">
                                                                                <option value="Murojaah">
                                                                            </datalist>
                                                                            <div style="position:absolute;bottom:-18px;left:4px;font-size:10px;font-weight:700;color:#d97706;" x-text="formattedSabaq"></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label class="form-label">Nilai (1-100) <span style="color:#ef4444;">*</span></label>
                                                                        <input type="text" class="form-input" x-model="score" :disabled="baris && baris.toLowerCase() === 'murojaah'" placeholder="Bulat, Mis: 85">
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Catatan -->
                                                                <div class="form-group" style="margin-top:12px;">
                                                                    <label class="form-label">Catatan (Opsional)</label>
                                                                    <input type="text" class="form-input" x-model="notes" placeholder="Tuliskan evaluasi/catatan hafalan">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" @click="clearForm(); closeAndSave()" style="background:#fef2f2;color:#ef4444;border:none;border-radius:10px;padding:8px 16px;font-weight:700;font-size:12px;font-family:'Outfit',sans-serif;cursor:pointer;margin-right:auto;">Kosongkan</button>
                                                                <button type="button" @click="closeAndSave()" style="background:#f1f5f9;color:#64748b;border:none;border-radius:10px;padding:8px 20px;font-weight:700;font-size:13px;font-family:'Outfit',sans-serif;cursor:pointer;">
                                                                    Selesai
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;">
                    <button type="submit" class="btn-primary">
                        <svg style="width:15px;height:15px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Simpan Data Pekanan
                    </button>
                </div>
            @endif
        </form>
    </main>

    <script>
        const quranSurahs = [
            "Al-Fatihah", "Al-Baqarah", "Ali 'Imran", "An-Nisa'", "Al-Ma'idah", "Al-An'am", "Al-A'raf", "Al-Anfal", "At-Taubah", "Yunus", "Hud", "Yusuf", "Ar-Ra'd", "Ibrahim", "Al-Hijr", "An-Nahl", "Al-Isra'", "Al-Kahf", "Maryam", "Taha", "Al-Anbiya'", "Al-Hajj", "Al-Mu'minun", "An-Nur", "Al-Furqan", "Asy-Syu'ara'", "An-Naml", "Al-Qasas", "Al-'Ankabut", "Ar-Rum", "Luqman", "As-Sajdah", "Al-Ahzab", "Saba'", "Fatir", "Yasin", "As-Saffat", "Sad", "Az-Zumar", "Gafir", "Fussilat", "Asy-Syura", "Az-Zukhruf", "Ad-Dukhan", "Al-Jasiyah", "Al-Ahqaf", "Muhammad", "Al-Fath", "Al-Hujurat", "Qaf", "Az-Zariyat", "At-Tur", "An-Najm", "Al-Qamar", "Ar-Rahman", "Al-Waqi'ah", "Al-Hadid", "Al-Mujadalah", "Al-Hasyr", "Al-Mumtahanah", "As-Saff", "Al-Jumu'ah", "Al-Munafiqun", "At-Tagabun", "At-Talaq", "At-Tahrim", "Al-Mulk", "Al-Qalam", "Al-Haqqah", "Al-Ma'arij", "Nuh", "Al-Jinn", "Al-Muzzammil", "Al-Muddassir", "Al-Qiyamah", "Al-Insan", "Al-Mursalat", "An-Naba'", "An-Nazi'at", "'Abasa", "At-Takwir", "Al-Infitar", "Al-Mutaffifin", "Al-Insyiqaq", "Al-Buruj", "At-Tariq", "Al-A'la", "Al-Gasyiyah", "Al-Fajr", "Al-Balad", "Asy-Syams", "Al-Lail", "Ad-Duha", "Asy-Syarh", "At-Tin", "Al-'Alaq", "Al-Qadr", "Al-Bayyinah", "Az-Zalzalah", "Al-'Adiyat", "Al-Qari'ah", "At-Takasur", "Al-'Asr", "Al-Humazah", "Al-Fil", "Quraisy", "Al-Ma'un", "Al-Kausar", "Al-Kafirun", "An-Nasr", "Al-Lahab", "Al-Ikhlas", "Al-Falaq", "An-Naas"
        ];

        document.addEventListener('alpine:init', () => {
            Alpine.data('tahfidzCell', (initialSurahAyat, initialSabaqAmount, initialScore, initialNotes, memberId, weekId, updateUrl) => {
                let sSurah = '', sAyat = '', eSurah = '', eAyat = '';
                
                // Parser for "Al-Baqarah:1 - Ali 'Imran:5" or simple formats
                if (initialSurahAyat) {
                    if (initialSurahAyat === '-') {
                        sSurah = '-'; sAyat = '-';
                        eSurah = '-'; eAyat = '-';
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
                
                // Ensure parsed surah exists in dropdown
                if (sSurah && sSurah !== '-' && !quranSurahs.includes(sSurah)) sSurah = '';
                if (eSurah && eSurah !== '-' && !quranSurahs.includes(eSurah)) eSurah = '';

                // Score as string
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
                            if (value === '-') this.startAyat = '-';
                        });
                        this.$watch('endSurah', value => {
                            if (value === '-') this.endAyat = '-';
                        });
                        this.$watch('baris', value => {
                            if (value && value.toLowerCase() === 'murojaah') this.score = '-';
                            else if (this.score === '-') this.score = '';
                        });
                    },
                    
                    closeAndSave() {
                        // Check if the form is completely empty (which means they are clearing it or it's untouched)
                        const isEmpty = !this.startSurah && !this.startAyat && !this.endSurah && !this.endAyat && !this.baris && (this.score === '' || this.score === null) && !this.notes;
                        
                        // If it's not empty, validate all required fields
                        if (!isEmpty) {
                            if (!this.startSurah) { alert("Nama Surat Dari wajib diisi!"); return; }
                            if (!this.startAyat) { alert("Dari Ayat wajib diisi!"); return; }
                            if (!this.endSurah) { alert("Nama Surat Sampai wajib diisi (Pilih '-' jika kosong)!"); return; }
                            if (!this.endAyat) { alert("Sampai Ayat wajib diisi (Ketik '-' jika kosong)!"); return; }
                            if (!this.baris) { alert("Jumlah Sabaq wajib diisi!"); return; }
                            if (this.score === '' || this.score === null) { alert("Nilai wajib diisi!"); return; }
                        }
                        
                        if (this.currentDataString() !== this._originalData) {
                            this.autoSave();
                        } else {
                            this.showModal = false;
                        }
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
                        // Do not automatically close here, let closeAndSave handle it
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
                        if (this.startAyat && this.startAyat !== '-') combined += ':' + this.startAyat;
                        if (this.endSurah && this.endSurah !== '-') {
                            combined += ' - ' + this.endSurah;
                            if (this.endAyat && this.endAyat !== '-') combined += ':' + this.endAyat;
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
                        if (!this.hasData) return '<span style="color:#cbd5e1;">Kosong<br><small>(Klik untuk isi)</small></span>';
                        let parts = [];
                        if (this.combinedSurahAyat) parts.push('<span style="font-weight:800;color:#0f172a;">' + this.combinedSurahAyat + '</span>');
                        if (this.formattedSabaq) parts.push('<span style="color:#d97706;font-size:10px;display:block;margin-top:2px;">' + this.formattedSabaq + '</span>');
                        if (this.score) parts.push('<span style="background:#fef3c7;color:#b45309;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:800;display:inline-block;margin-top:4px;">Nilai: ' + this.score + '</span>');
                        return parts.join('');
                    }
                }
            })
        });
    </script>
</body>
</html>