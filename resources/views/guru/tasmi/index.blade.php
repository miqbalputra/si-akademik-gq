<x-layouts.portal title="Tasmi'" portalLabel="Portal Guru" breadcrumb="Ujian Tasmi'">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .hover-card { transition: all .25s cubic-bezier(.16,1,.3,1); }
        .hover-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(0,0,0,.1); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #f3e8ff; color: #6b21a8; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 48px 24px; text-align: center; }
        .predicate-maqbul { background: #f1f5f9; color: #475569; }
        .predicate-jayyid { background: #dbeafe; color: #1e40af; }
        .predicate-jayyid_jiddan { background: #dcfce7; color: #166534; }
        .predicate-mumtaz { background: #f3e8ff; color: #6b21a8; }
    </style>
    @endpush

    {{-- Header --}}
    <header class="fade-up" style="margin-bottom:28px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:#f3e8ff;border-radius:999px;padding:4px 12px;margin-bottom:12px;">
            <svg style="width:12px;height:12px;color:#6b21a8;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#6b21a8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
            <span style="font-size:11px;font-weight:700;color:#6b21a8;text-transform:uppercase;letter-spacing:.05em;">Modul Tasmi'</span>
        </div>
        <h1 style="font-size:26px;font-weight:900;color:#0f172a;margin:0 0 6px;letter-spacing:-.02em;">Ujian Tasmi'</h1>
        <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">
            Anda ditugaskan sebagai PJ Tasmi'
            @if($academicTerm) — {{ $academicTerm->name }} @endif
            @if($genderScope === 'male') · Ujian ikhwan @elseif($genderScope === 'female') · Ujian akhwat @endif
        </p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:8px;" class="fade-up">
            <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Aksi cepat --}}
    <section style="margin-bottom:28px;" class="fade-up delay-1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <a href="{{ route('guru.tasmi.create') }}" class="card hover-card" style="padding:20px 22px;text-decoration:none;">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,#f3e8ff,#e9d5ff);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:20px;height:20px;color:#6b21a8;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#6b21a8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </div>
                    <div>
                        <p style="font-size:14px;font-weight:800;color:#0f172a;margin:0;">Input Tasmi' Baru</p>
                        <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Catat setoran ujian tasmi' santri.</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('guru.tasmi.records') }}" class="card hover-card" style="padding:20px 22px;text-decoration:none;">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:20px;height:20px;color:#92400e;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#92400e"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </div>
                    <div>
                        <p style="font-size:14px;font-weight:800;color:#0f172a;margin:0;">Riwayat &amp; Laporan</p>
                        <p style="font-size:12px;color:#64748b;margin:2px 0 0;">Lihat, edit, dan audit data tasmi'.</p>
                    </div>
                </div>
            </a>
        </div>
    </section>

    {{-- Ringkasan kelas yang bisa diuji --}}
    <section style="margin-bottom:28px;" class="fade-up delay-2">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <h2 style="font-size:16px;font-weight:800;color:#0f172a;white-space:nowrap;">Kelas yang Bisa Diuji</h2>
            <div style="flex:1;height:1px;background:#f1f5f9;"></div>
        </div>
        @if($classroomTerms->isEmpty())
            <div class="empty-state">
                <svg style="width:40px;height:40px;color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada kelas @if($genderScope === 'male') ikhwan @elseif($genderScope === 'female') akhwat @endif yang aktif pada periode ini.</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
                @foreach($classroomTerms as $ct)
                    <a href="{{ route('guru.tasmi.create', ['classroom_term_id' => $ct->id]) }}" class="card hover-card" style="padding:16px 18px;text-decoration:none;">
                        <p style="font-size:14px;font-weight:800;color:#0f172a;margin:0;">{{ $ct->classroom->name ?? $ct->name }}</p>
                        <p style="font-size:12px;color:#64748b;margin:4px 0 0;">{{ $ct->classroom->gender_group === 'male' ? 'Ikhwan' : 'Akwat' }}</p>
                        <span class="badge badge-amber" style="margin-top:10px;">Input tasmi' →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Riwayat terakhir --}}
    <section class="fade-up delay-3">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <h2 style="font-size:16px;font-weight:800;color:#0f172a;white-space:nowrap;">Setoran Terakhir</h2>
            <div style="flex:1;height:1px;background:#f1f5f9;"></div>
            <a href="{{ route('guru.tasmi.records') }}" style="font-size:12px;font-weight:700;color:#6b21a8;">Lihat semua →</a>
        </div>
        @if($recentRecords->isEmpty())
            <div class="empty-state">
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada record tasmi' yang Anda input.</p>
            </div>
        @else
            <div style="display:grid;gap:12px;">
                @foreach($recentRecords as $record)
                    <a href="{{ route('guru.tasmi.edit', $record) }}" class="card hover-card" style="padding:16px 20px;text-decoration:none;display:flex;align-items:center;justify-content:space-between;gap:14px;">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="width:38px;height:38px;background:#f3e8ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#6b21a8;">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($record->student->name, 0, 1)) }}
                            </div>
                            <div>
                                <p style="font-size:14px;font-weight:800;color:#0f172a;margin:0;">{{ $record->student->name }}</p>
                                <p style="font-size:12px;color:#64748b;margin:2px 0 0;">
                                    {{ $record->classroomTerm?->classroom?->name ?? '-' }} · {{ $record->exam_date?->locale('id')->translatedFormat('d M Y') }} · {{ $record->juz_range_label }}
                                </p>
                            </div>
                        </div>
                        <span class="badge predicate-{{ $record->predicate }}">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.portal>