<x-layouts.portal title="Leger Kelas Saya" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Leger">
    <div class="space-y-5">
        <header><h1 class="ui-page-title text-heading">Leger Kelas Saya</h1><p class="mt-2 text-muted">Hasil akhir Diniyyah dan Tahfidz, khusus wali kelas yang ditugaskan.</p></header>
        @if($errors->any())<div role="alert" class="rounded-xl bg-danger-soft p-4 text-danger-ink">{{ $errors->first() }}</div>@endif
        @forelse($classrooms as $classroom)
            @php($snapshot = $snapshots->get($classroom->id))
            <section class="rounded-2xl border border-line bg-surface p-5">
                <h2 class="ui-card-title">{{ $classroom->name }}</h2><p class="mt-1 text-muted">{{ $classroom->academicTerm?->academicYear?->name }} · {{ $classroom->academicTerm?->name }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    @if($snapshot)<a class="btn" href="{{ route('diniyyah.ledger.show', $snapshot) }}">Buka leger · {{ \App\Support\UiLabel::statusLabel($snapshot->status) }}</a>@endif
                    @if(! $snapshot || !in_array($snapshot->status, ['locked','published'], true))<form method="POST" action="{{ route('diniyyah.ledger.generate', $classroom) }}">@csrf<button class="btn btn-primary">{{ $snapshot ? 'Segarkan leger' : 'Buat leger' }}</button></form>@endif
                </div>
            </section>
        @empty<p class="rounded-xl border border-line bg-surface p-5 text-muted">Belum ada penugasan wali kelas aktif.</p>@endforelse
    </div>
</x-layouts.portal>
