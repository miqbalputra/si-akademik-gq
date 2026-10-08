<x-layouts.portal title="Halaqah Tahfidz" portalLabel="Portal Guru" breadcrumb="Halaqah Tahfidz">
    {{-- Header --}}
    <header class="fade-up" style="margin-bottom:28px;">
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--ui-brand-soft-strong);border-radius:999px;padding:4px 12px;margin-bottom:12px;">
            <svg style="width:12px;height:12px;color:var(--color-brand-600);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--color-brand-600)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
            <span style="font-size:12px;color:var(--ui-brand-ink);text-transform:uppercase;letter-spacing:normal;line-height:18px;font-weight:400;">Modul Tahfidz</span>
        </div>
        <h1 style="color:var(--ui-heading);margin:0 0 6px;letter-spacing:normal;font-size:20px;font-weight:600;line-height:28px;">Halaqah Tahfidz</h1>
        <p style="font-size:14px;color:var(--ui-muted);margin:0;line-height:20px;font-weight:400;">Pilih halaqah untuk menginput rekap setoran hafalan pekanan santri.</p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:20px;background:var(--ui-success-soft);border:1px solid var(--ui-success-line);border-radius:12px;padding:14px 18px;font-size:14px;font-weight:500;color:var(--ui-success-ink);display:flex;align-items:center;gap:8px;line-height:20px;" class="fade-up">
            <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ session('status') }}
        </div>
    @endif

    {{-- Halaqah List --}}
    <div style="display:grid;gap:14px;" class="fade-up delay-1">
        @forelse ($halaqahs as $halaqah)
            <a href="{{ route('guru.tahfidz.show', $halaqah) }}" class="card hover-card" style="padding:20px 24px;text-decoration:none;display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--ui-warning-soft-strong),var(--ui-warning-line);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:22px;height:22px;color:var(--color-warning-600);" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="var(--color-warning-600)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                    </div>
                    <div>
                        <h2 style="color:var(--ui-heading);margin:0 0 4px;font-size:18px;font-weight:600;line-height:28px;">{{ $halaqah->name }}</h2>
                        <p style="font-size:14px;color:var(--ui-muted);margin:0;line-height:20px;font-weight:400;">
                            {{ $halaqah->teacher?->name ?? 'Belum ada guru' }} &middot; {{ $halaqah->academicTerm?->name ?? '-' }}
                        </p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="badge badge-amber text-theme-xs font-medium">{{ $halaqah->active_members_count ?? $halaqah->activeMembers->count() }} santri</span>
                    <svg style="width:16px;height:16px;color:var(--ui-soft);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <svg style="width:40px;height:40px;color:var(--ui-line-strong);margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <p style="color:var(--ui-soft);font-size:14px;line-height:20px;font-weight:400;">Belum ada halaqah yang ditugaskan untuk Anda.</p>
            </div>
        @endforelse
    </div>

    @push('styles')
    <style>
        .card { background:var(--ui-surface); border:1px solid var(--ui-surface-muted); border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .hover-card { transition:all .25s cubic-bezier(.16,1,.3,1); }
        .hover-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px -8px rgba(0,0,0,.1); border-color:var(--ui-warning-line); }
        .badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:12px; line-height:18px; font-weight:500; }
        .badge-amber { background:var(--ui-warning-soft-strong); color:var(--ui-warning-ink); }
        .empty-state { border:2px dashed var(--ui-line); border-radius:16px; padding:48px 24px; text-align:center; }
    </style>
    @endpush
</x-layouts.portal>