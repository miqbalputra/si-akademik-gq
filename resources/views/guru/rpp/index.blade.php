<x-layouts.portal title="RPP Saya" portalLabel="Portal Guru" breadcrumb="RPP Saya">
    <header class="portal-page-header">
        <div>
            <p class="school-index">Perangkat Pembelajaran</p>
            <h1 class="text-slate-900">RPP Saya</h1>
            <p class="mt-2 text-sm font-medium text-slate-500">Buat, cari, ekspor, dan kelola RPP Diniyyah berdasarkan penugasan Anda.</p>
        </div>
        <a href="{{ route('guru.rpp.create') }}" class="btn btn-primary">Buat RPP</a>
    </header>

    @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
    <form class="ui-card mb-6 flex gap-3 rounded-2xl p-4" method="GET">
        <input class="form-input flex-1" type="search" name="q" value="{{ request('q') }}" placeholder="Cari materi, nomor RPP, atau mapel">
        <button class="btn btn-outline" type="submit">Cari</button>
        <a class="btn btn-outline" href="{{ route('guru.rpp.references') }}">Referensi</a>
        <a class="btn btn-outline" href="{{ route('guru.rpp.trash') }}">Sampah</a>
    </form>

    <section class="ui-card overflow-hidden rounded-2xl">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-600"><tr><th class="p-4">Materi</th><th class="p-4">Mapel / Kelas</th><th class="p-4">Metode</th><th class="p-4">Diperbarui</th><th class="p-4 text-right">Aksi</th></tr></thead>
                <tbody>
                @forelse($rpps as $rpp)
                    <tr class="border-b border-slate-100"><td class="p-4 font-bold text-slate-800">{{ $rpp->materi }} @if($rpp->no_rpp)<span class="block text-xs font-medium text-slate-500">No. {{ $rpp->no_rpp }}</span>@endif</td><td class="p-4">{{ $rpp->classSubject?->subject?->name }}<span class="block text-xs text-slate-500">{{ $rpp->classSubject?->classroomTerm?->name }}</span></td><td class="p-4"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ strtoupper($rpp->input_method) }}</span></td><td class="p-4 text-slate-500">{{ $rpp->updated_at->diffForHumans() }}</td><td class="p-4 text-right"><a class="btn btn-outline btn-sm" href="{{ route('guru.rpp.show', $rpp) }}">Buka</a></td></tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center text-slate-500">Belum ada RPP. Mulai dari tombol “Buat RPP”.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $rpps->links() }}</div>
    </section>
    <section class="ui-card mt-6 rounded-2xl p-5"><h2 class="font-black text-slate-800">Butuh bantuan RPP?</h2><p class="mt-1 text-sm text-slate-500">Kirim pesan ke Admin dan Kabag Diniyyah melalui pusat notifikasi.</p><form class="mt-3 flex flex-col gap-3 sm:flex-row" method="POST" action="{{ route('guru.rpp.help') }}">@csrf <input class="form-input flex-1" name="message" maxlength="2000" placeholder="Tulis kendala Anda" required><button class="btn btn-outline" type="submit">Kirim bantuan</button></form></section>
</x-layouts.portal>
