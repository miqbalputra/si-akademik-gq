<x-layouts.portal title="Pemeriksaan Tahfidz Kelas" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Pemeriksaan Tahfidz">
    <div class="space-y-5">
        <h1 class="ui-page-title">Pemeriksaan Tahfidz · {{ $classroom->name }}</h1>
        <p>{{ $classroom->academicTerm->academicYear?->name }} · {{ $classroom->academicTerm->name }}</p>
        <p>Periksa nilai final dari Kabag Tahfidz, lalu segarkan leger agar nilai masuk ke rapor.</p>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <a class="btn btn-outline" href="{{ route('guru.report-workflow', ['classroom_term_id' => $classroom->id]) }}">Kembali ke Alur Nilai &amp; Rapor</a>
        <section class="grid gap-3 sm:grid-cols-3" aria-label="Pengisian nilai oleh Kabag Tahfidz">
            <article class="metric-card"><p class="metric-label ui-metric-label">Final dari Kabag</p><p class="metric-value">{{ $kabagComplete }} / {{ $enrollments->count() }}</p></article>
            <article class="metric-card"><p class="metric-label ui-metric-label">Draf dari Kabag</p><p class="metric-value">{{ $kabagDrafts }}</p></article>
            <article class="metric-card"><p class="metric-label ui-metric-label">Belum diisi Kabag</p><p class="metric-value">{{ $enrollments->count() - $kabagComplete - $kabagDrafts }}</p></article>
        </section>
        <div class="card-lg overflow-x-auto"><table class="w-full min-w-[760px] text-left text-theme-sm"><thead class="bg-surface-subtle"><tr>@foreach(['Santri','Juz yang diujikan','Ujian Tahfidz','Sabaq','Sabqi','Manzil','Pengisi / Status','Pemeriksaan'] as $heading)<th scope="col" class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
        @forelse($enrollments as $enrollment)
            @php
                $state=$states[$enrollment->id]; $entry=$state['entry'];
            @endphp
            <tr class="border-t border-line"><th scope="row" class="p-3">{{ $enrollment->student->name }}<span class="block font-normal text-muted">{{ $enrollment->student->nis }}</span></th>
            @foreach($state['values'] as $value)<td class="p-3">{{ $value ?? 'Belum diisi' }}</td>@endforeach
            <td class="p-3">
                @if ($entry?->input_role === 'kabag_tahfidz')
                    <span class="font-medium">Kabag Tahfidz</span><span class="block text-muted">{{ $users->get($entry->input_by) }}</span>
                @elseif ($entry)
                    <span>Isian sebelumnya</span><span class="block text-muted">{{ $users->get($entry->input_by) }}</span>
                @else
                    <span>Belum diisi Kabag</span>
                @endif
                <span class="block">{{ ['draft'=>'Draf / belum final','final'=>'Menunggu pemeriksaan','reviewed'=>'Sudah diperiksa','legacy_validated'=>'Nilai lama tervalidasi','missing'=>'Belum lengkap'][$state['status']] }}</span>
            </td>
            <td class="p-3">@if($locked)Leger/rapor terkunci @elseif($state['status']==='final')<form method="POST" action="{{ route('guru.tahfidz-report.review', $enrollment) }}">@csrf<input type="hidden" name="version" value="{{ $entry->version }}"><button class="btn btn-primary">Sudah diperiksa</button></form>@else{{ $state['ready'] ? 'Siap masuk leger' : 'Menunggu Kabag Tahfidz' }}@endif</td></tr>
        @empty<tr><td colspan="8" class="p-5">Belum ada santri aktif di kelas.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</x-layouts.portal>
