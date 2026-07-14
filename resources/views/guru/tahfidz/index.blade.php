<x-layouts.portal title="Halaqah Tahfidz" portalLabel="Portal Guru" breadcrumb="Halaqah Tahfidz">
    {{-- Header --}}
    <header class="fade-up" style="margin-bottom:28px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:#e0e7ff;border-radius:999px;padding:4px 12px;margin-bottom:12px;">
            <svg style="width:12px;height:12px;color:#4f46e5;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#4f46e5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
            <span style="font-size:11px;font-weight:700;color:#4338ca;text-transform:uppercase;letter-spacing:.05em;">Modul Tahfidz</span>
        </div>
        <h1 style="font-size:26px;font-weight:900;color:#0f172a;margin:0 0 6px;letter-spacing:-.02em;">Halaqah Tahfidz</h1>
        <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Pilih halaqah untuk menginput rekap setoran hafalan pekanan santri.</p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#166534;display:flex;align-items:center;gap:8px;" class="fade-up">
            <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Halaqah List --}}
    <div style="display:grid;gap:14px;" class="fade-up delay-1">
        @forelse ($halaqahs as $halaqah)
            <a href="{{ route('guru.tahfidz.show', $halaqah) }}" class="card hover-card" style="padding:20px 24px;text-decoration:none;display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:48px;height:48px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:22px;height:22px;color:#d97706;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                    </div>
                    <div>
                        <h2 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 4px;">{{ $halaqah->name }}</h2>
                        <p style="font-size:13px;color:#64748b;font-weight:500;margin:0;">
                            {{ $halaqah->teacher?->name ?? 'Belum ada guru' }} &middot; {{ $halaqah->academicTerm?->name ?? '-' }}
                        </p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="badge badge-amber">{{ $halaqah->active_members_count ?? $halaqah->activeMembers->count() }} santri</span>
                    <svg style="width:16px;height:16px;color:#94a3b8;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <svg style="width:40px;height:40px;color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Belum ada halaqah yang ditugaskan untuk Anda.</p>
            </div>
        @endforelse
    </div>

    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .hover-card { transition: all .25s cubic-bezier(.16,1,.3,1); }
        .hover-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(0,0,0,.1); border-color: #fde68a; }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 48px 24px; text-align: center; }
    </style>
    @endpush
</x-layouts.portal>