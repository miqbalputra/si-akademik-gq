<x-layouts.portal title="Rapor Kelas Saya" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Rapor">
    <div class="space-y-5">
        <header><h1 class="ui-page-title text-heading">Rapor Kelas Saya</h1><p class="mt-2 text-muted">Rapor dibuat otomatis setelah leger final dikunci. Tinjau pelengkapnya, lalu cetak pada kertas F4.</p></header>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($classrooms as $classroom)
                @php
                    $snapshot = $snapshots->get($classroom->id);
                @endphp
                <section class="card-lg p-5 space-y-3">
                    <h2 class="ui-card-title">{{ $classroom->name }}</h2>
                    <p class="text-muted">{{ $classroom->academicTerm?->academicYear?->name }} · {{ $classroom->academicTerm?->name }}</p>
                    <a class="btn btn-outline" href="{{ route('guru.tahfidz-report.monitor', $classroom) }}">Periksa isian Tahfidz</a>
                    @if($snapshot)<a class="btn btn-primary" href="{{ route('report-cards.classroom', $snapshot) }}">Buka rapor kelas</a>
                    @else<p>Leger belum dibuat.</p><a class="btn btn-outline" href="{{ route('diniyyah.ledger.index') }}">Buka leger</a>@endif
                </section>
            @empty<p>Belum ada penugasan wali kelas yang aktif.</p>@endforelse
        </div>
    </div>
</x-layouts.portal>
