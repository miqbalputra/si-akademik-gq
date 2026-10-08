<x-layouts.portal title="RPP Saya" portalLabel="Portal Guru" breadcrumb="RPP Saya">
    <header class="portal-page-header">
        <div>
            <p class="school-index">Perangkat Pembelajaran</p>
            <h1 class="text-heading ui-page-title">RPP Saya</h1>
            <p class="mt-2 text-theme-sm font-normal text-muted">Cari, ekspor, dan lihat RPP Diniyyah berdasarkan penugasan Anda.</p>
        </div>
        @unless(config('rpp_sync.enabled'))<a href="{{ route('guru.rpp.create') }}" class="btn btn-primary text-theme-sm font-medium">Buat RPP</a>@endunless
    </header>

    @if(session('success'))<div class="mb-5 rounded-xl border border-success-line bg-success-soft p-4 text-theme-sm font-medium text-success-ink">{{ session('success') }}</div>@endif
    <form class="ui-card mb-6 flex gap-3 rounded-2xl p-4" method="GET">
        <input class="form-input flex-1 text-theme-sm font-normal" type="search" name="q" value="{{ request('q') }}" placeholder="Cari materi, nomor RPP, atau mapel">
        <button class="btn btn-outline text-theme-sm font-medium" type="submit">Cari</button>
        <a class="btn btn-outline text-theme-sm font-medium" href="{{ route('guru.rpp.references') }}">Referensi</a>
        @unless(config('rpp_sync.enabled'))<a class="btn btn-outline text-theme-sm font-medium" href="{{ route('guru.rpp.trash') }}">Sampah</a>@endunless
    </form>

    <section class="ui-card overflow-hidden rounded-2xl">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-theme-sm">
                <thead class="border-b border-line bg-surface-subtle text-body text-theme-xs font-medium"><tr><th class="p-4 text-theme-xs font-medium">Materi</th><th class="p-4 text-theme-xs font-medium">Mapel / Kelas</th><th class="p-4 text-theme-xs font-medium">Metode</th><th class="p-4 text-theme-xs font-medium">Diperbarui</th><th class="p-4 text-right text-theme-xs font-medium">Aksi</th></tr></thead>
                <tbody>
                @forelse($rpps as $rpp)
                    <tr class="border-b border-line"><td class="p-4 font-medium text-heading">{{ $rpp->materi }} @if($rpp->no_rpp)<span class="block text-theme-xs font-normal text-muted">No. {{ $rpp->no_rpp }}</span>@endif</td><td class="p-4">{{ $rpp->classSubject?->subject?->name }}<span class="block text-theme-xs text-muted font-normal">{{ $rpp->classSubject?->classroomTerm?->name }}</span></td><td class="p-4"><span class="rounded-full bg-success-soft px-2 py-1 text-success-ink text-theme-xs font-medium">{{ strtoupper($rpp->input_method) }}</span></td><td class="p-4 text-muted">{{ $rpp->updated_at->diffForHumans() }}</td><td class="p-4 text-right"><a class="btn btn-outline btn-sm text-theme-sm font-medium" href="{{ route('guru.rpp.show', $rpp) }}">Buka</a></td></tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center text-muted">Belum ada RPP yang tersinkron dari Project RPP.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $rpps->links() }}</div>
    </section>
    @unless(config('rpp_sync.enabled'))<section class="ui-card mt-6 rounded-2xl p-5"><h2 class="text-heading ui-card-title">Butuh bantuan RPP?</h2><p class="mt-1 text-theme-sm text-muted">Kirim pesan ke Admin dan Kabag Diniyyah melalui pusat notifikasi.</p><form class="mt-3 flex flex-col gap-3 sm:flex-row" method="POST" action="{{ route('guru.rpp.help') }}">@csrf <input class="form-input flex-1 text-theme-sm font-normal" name="message" maxlength="2000" placeholder="Tulis kendala Anda" required><button class="btn btn-outline text-theme-sm font-medium" type="submit">Kirim bantuan</button></form></section>@endunless
</x-layouts.portal>
