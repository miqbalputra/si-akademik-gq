@php
    $isHomeroom = app(\App\Services\DiniyyahLedgerAccess::class)->allows(auth()->user(), $snapshot->classroom_term_id);
    $canGenerate = $isHomeroom || auth()->user()->hasAnyRole(['admin', 'kabag_diniyyah']);
@endphp
<x-layouts.portal title="Rapor {{ $snapshot->classroomTerm?->name }}" :portalLabel="$isHomeroom ? 'Portal Guru' : 'Portal Manajemen'" breadcrumb="Rapor / Daftar kelas">
    <div class="space-y-5">
        <header><h1 class="ui-page-title text-heading">Rapor {{ $snapshot->classroomTerm?->name }}</h1><p class="mt-2 text-muted">{{ $snapshot->academicTerm?->academicYear?->name }} · {{ $snapshot->academicTerm?->name }} · F4 / Folio 216 × 330 mm</p></header>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <div class="flex flex-wrap gap-2">
            @if($isHomeroom)
                <a class="btn btn-outline" href="{{ route('guru.report-workflow', ['classroom_term_id' => $snapshot->classroom_term_id]) }}">Alur Nilai &amp; Rapor</a>
                <a class="btn btn-outline" href="{{ route('diniyyah.ledger.show', $snapshot) }}">Lihat leger</a>
            @endif
            @if(in_array($snapshot->status, ['locked','published'], true))
                @if($canGenerate && $missingCount > 0)
                <form method="POST" action="{{ route('report-cards.generate', $snapshot) }}">@csrf<button class="btn btn-outline">Buat rapor yang belum ada</button></form>
                @endif
                @if($cards->isNotEmpty() && $missingCount === 0 && $readiness->every(fn ($items) => count($items) === 0))
                    <a class="btn btn-primary" href="{{ route('report-cards.classroom.print', $snapshot) }}" target="_blank" rel="noopener">Cetak seluruh kelas</a>
                    <a class="btn btn-outline" href="{{ route('report-cards.classroom.pdf', $snapshot) }}">Unduh PDF seluruh kelas</a>
                @endif
            @else<p class="text-warning-ink">Kunci leger final untuk membuat rapor otomatis.</p>@endif
        </div>
        @if($missingCount > 0)<p class="text-warning-ink" role="status">{{ $missingCount }} santri belum memiliki rapor. Selesaikan leger, lalu buat rapor yang belum ada.</p>@endif
        @if($isHomeroom && $cards->contains(fn ($card) => $card->status !== 'published'))
            <p class="text-theme-sm text-muted">Tinjau dan simpan pelengkap setiap rapor. Setelah lengkap, Admin atau Kabag Diniyyah mengunci dan menerbitkan rapor.</p>
        @endif
        <div class="card-lg overflow-x-auto">
            <table class="w-full text-left"><thead><tr class="border-b border-line"><th class="p-4">Santri / NIS</th><th class="p-4">Status</th><th class="p-4">Kesiapan cetak</th><th class="p-4">Tindakan</th></tr></thead>
            <tbody>@forelse($cards as $card)<tr class="border-b border-line">
                <td class="p-4 font-semibold">{{ $card->student?->name }}<span class="block text-muted text-sm">{{ $card->student?->nis }}</span></td>
                <td class="p-4">{{ \App\Support\UiLabel::statusLabel($card->status) }}</td>
                <td class="p-4">{{ count($readiness[$card->id]) ? 'Lengkapi: '.implode(', ', $readiness[$card->id]) : 'Siap cetak F4' }}</td>
                <td class="p-4"><a class="btn btn-outline" href="{{ route('report-cards.show', $card) }}">Tinjau rapor</a></td>
            </tr>@empty<tr><td colspan="4" class="p-6 text-muted">Belum ada rapor untuk kelas ini.</td></tr>@endforelse</tbody></table>
        </div>
    </div>
</x-layouts.portal>
