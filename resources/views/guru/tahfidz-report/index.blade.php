<x-layouts.portal title="Nilai Final Tahfidz Rapor" portalLabel="Portal Guru" breadcrumb="Nilai / Tahfidz Rapor">
    <div class="space-y-5">
        <h1 class="ui-page-title">Nilai Final Tahfidz Rapor</h1>
        <p>Peserta didik ditampilkan sesuai gender guru. Simpan draf selama mengisi, lalu kirim nilai final untuk diperiksa wali kelas.</p>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="GET"><label class="ui-field"><span>Periode</span><select class="form-input" name="academic_term_id" onchange="this.form.submit()">@foreach($terms as $option)<option value="{{ $option->id }}" @selected($term->id === $option->id)>{{ $option->academicYear?->name }} · {{ $option->name }}</option>@endforeach</select></label><noscript><button class="btn">Tampilkan</button></noscript></form>
        @forelse($enrollments as $enrollment)
            @php
                $state = $states[$enrollment->id];
                $entry = $state['entry'];
                $keys = ['juz_tested'=>'tahfidz_juz','uas_score'=>'tahfidz_uas','sabaq_score'=>'tahfidz_sabaq','sabqi_score'=>'tahfidz_sabqi','manzil_score'=>'tahfidz_manzil'];
            @endphp
            <form class="card-lg p-5 space-y-4" method="POST" action="{{ route('guru.tahfidz-report.save', $enrollment) }}">
                @csrf<input type="hidden" name="entry_enrollment_id" value="{{ $enrollment->id }}"><input type="hidden" name="version" value="{{ (int)old('entry_enrollment_id') === $enrollment->id ? old('version', $entry?->version ?? 0) : ($entry?->version ?? 0) }}">
                <h2 class="ui-card-title">{{ $enrollment->student->name }} · {{ $enrollment->student->nis }}</h2>
                <p class="text-muted">{{ $enrollment->classroomTerm->name }} · {{ ['draft'=>'Draf','final'=>'Final, menunggu wali kelas','reviewed'=>'Sudah diperiksa wali kelas','legacy_validated'=>'Nilai lama tervalidasi','missing'=>'Belum lengkap'][$state['status']] }}</p>
                @if($state['locked'])<p class="text-muted">Leger/rapor sudah dikunci. Isian tidak dapat diubah.</p>@endif
                <fieldset @disabled($state['locked']) class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach(['juz_tested'=>'Juz yang diujikan','uas_score'=>'Nilai ujian Tahfidz','sabaq_score'=>'Sabaq','sabqi_score'=>'Sabqi','manzil_score'=>'Manzil'] as $field=>$label)
                    <label class="ui-field"><span>{{ $label }}</span><input class="form-input" name="{{ $field }}" value="{{ (int)old('entry_enrollment_id') === $enrollment->id ? old($field, $state['values'][$keys[$field]]) : $state['values'][$keys[$field]] }}" @if($field==='juz_tested')type="text" maxlength="255"@else type="number" min="0" max="100" step="0.01"@endif></label>
                    @endforeach
                </fieldset>
                @unless($state['locked'])<div class="flex flex-wrap gap-2"><button class="btn btn-outline" name="action" value="draft">Simpan draf</button><button class="btn btn-primary" name="action" value="final">Kirim nilai final</button></div>@endunless
            </form>
        @empty<p>Belum ada peserta didik aktif dengan gender yang sama pada periode ini.</p>@endforelse
        {{ $enrollments->links() }}
    </div>
</x-layouts.portal>
