<x-layouts.portal title="Pemeriksaan Tahfidz Kelas" portalLabel="Portal Guru" breadcrumb="Wali Kelas / Pemeriksaan Tahfidz">
    <div class="space-y-5">
        <h1 class="ui-page-title">Pemeriksaan Tahfidz · {{ $classroom->name }}</h1>
        <p>{{ $classroom->academicTerm->academicYear?->name }} · {{ $classroom->academicTerm->name }}</p>
        <p>Periksa nilai final guru sebelum menyegarkan dan mengunci leger. Santri yang belum lengkap tetap terlihat di daftar ini.</p>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <a class="btn btn-outline" href="{{ route('guru.report-workflow', ['classroom_term_id' => $classroom->id]) }}">Kembali ke Alur Nilai &amp; Rapor</a>
        <div class="card-lg overflow-x-auto"><table class="w-full text-left"><thead><tr>@foreach(['Santri','Juz yang diujikan','Ujian','Sabaq','Sabqi','Manzil','Pengisi / Status','Pemeriksaan'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
        @forelse($enrollments as $enrollment)
            @php
                $state=$states[$enrollment->id]; $entry=$state['entry'];
            @endphp
            <tr class="border-t border-line"><td class="p-3">{{ $enrollment->student->name }}<span class="block text-muted">{{ $enrollment->student->nis }}</span></td>
            @foreach($state['values'] as $value)<td class="p-3">{{ $value ?? 'Belum diisi' }}</td>@endforeach
            <td class="p-3">{{ $entry ? $users->get($entry->input_by) : 'Sumber nilai sebelumnya' }}<span class="block">{{ ['draft'=>'Draf / belum final','final'=>'Menunggu pemeriksaan','reviewed'=>'Sudah diperiksa','legacy_validated'=>'Nilai lama tervalidasi','missing'=>'Belum lengkap'][$state['status']] }}</span></td>
            <td class="p-3">@if($state['locked'])Leger/rapor terkunci @elseif($state['status']==='final')<form method="POST" action="{{ route('guru.tahfidz-report.review', $enrollment) }}">@csrf<input type="hidden" name="version" value="{{ $entry->version }}"><button class="btn btn-primary">Sudah diperiksa</button></form>@else{{ $state['ready'] ? 'Siap masuk leger' : 'Menunggu guru melengkapi' }}@endif</td></tr>
        @empty<tr><td colspan="8" class="p-5">Belum ada santri aktif di kelas.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</x-layouts.portal>
