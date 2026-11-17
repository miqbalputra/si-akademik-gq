<x-layouts.portal title="Nilai Final Tahfidz Rapor" portalLabel="Portal Guru" breadcrumb="Nilai / Tahfidz Rapor">
    @push('head')
        <style>
            .tahfidz-entry-page, .tahfidz-entry-page form, .tahfidz-entry-page fieldset, .tahfidz-entry-page .ui-field { min-width: 0; }
            .tahfidz-entry-page input, .tahfidz-entry-page select { min-width: 0; max-width: 100%; }
        </style>
    @endpush
    @php
        $labels = ['draft'=>'Draf','final'=>'Menunggu pemeriksaan','reviewed'=>'Sudah diperiksa','legacy_validated'=>'Nilai lama tervalidasi','missing'=>'Belum diisi'];
        $fields = ['juz_tested'=>'Juz yang diujikan','uas_score'=>'Nilai ujian Tahfidz','sabaq_score'=>'Sabaq','sabqi_score'=>'Sabqi','manzil_score'=>'Manzil'];
        $keys = ['juz_tested'=>'tahfidz_juz','uas_score'=>'tahfidz_uas','sabaq_score'=>'tahfidz_sabaq','sabqi_score'=>'tahfidz_sabqi','manzil_score'=>'tahfidz_manzil'];
    @endphp
    <div class="tahfidz-entry-page space-y-5">
        <header><h1 class="ui-page-title">Nilai Final Tahfidz Rapor</h1><p class="mt-2 text-muted">Pilih kelas dan peserta didik, lalu isi nilai satu per satu. Periksa nama dan NIS sebelum menyimpan.</p></header>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <section class="card-lg p-5 space-y-4" aria-labelledby="pilih-peserta-heading">
            <h2 id="pilih-peserta-heading" class="ui-card-title">Pilih peserta didik</h2>
            <form method="GET" action="{{ route('guru.tahfidz-report.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <label class="ui-field"><span>Periode</span><select class="form-input" name="academic_term_id" onchange="this.form.elements['classroom_term_id'].value='';this.form.elements['enrollment_id'].value='';this.form.submit()">
                    @foreach($terms as $option)<option value="{{ $option->id }}" @selected($term->id === $option->id)>{{ $option->academicYear?->name }} · {{ $option->name }}</option>@endforeach
                </select></label>
                <label class="ui-field"><span>1. Pilih kelas</span><select class="form-input" name="classroom_term_id" onchange="this.form.elements['enrollment_id'].value='';this.form.submit()">
                    <option value="">Pilih kelas</option>
                    @foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected($selectedClass?->id === $classroom->id)>{{ $classroom->name }}</option>@endforeach
                </select></label>
                <label class="ui-field"><span>2. Pilih nama dan NIS</span><select class="form-input" name="enrollment_id" onchange="this.form.submit()" @disabled(!$selectedClass)>
                    <option value="">{{ $selectedClass ? 'Pilih peserta didik' : 'Pilih kelas terlebih dahulu' }}</option>
                    @foreach($students as $student)<option value="{{ $student->id }}" @selected($selectedEnrollment?->id === $student->id)>{{ $student->student->name }} — NIS {{ $student->student->nis }}</option>@endforeach
                </select></label>
                <noscript><button class="btn btn-outline">Tampilkan pilihan</button></noscript>
            </form>
            @if($classrooms->isEmpty())<p class="text-muted">Belum ada peserta didik aktif dengan gender yang sama pada periode ini.</p>@endif
        </section>
        @if($selectedEnrollment)
            @php
                $entry = $selectedState['entry'];
                $restore = (int)old('entry_enrollment_id') === $selectedEnrollment->id;
            @endphp
            <form class="card-lg p-5 space-y-4" method="POST" action="{{ route('guru.tahfidz-report.save', $selectedEnrollment) }}" aria-labelledby="isi-nilai-heading">
                @csrf
                <input type="hidden" name="entry_enrollment_id" value="{{ $selectedEnrollment->id }}">
                <input type="hidden" name="version" value="{{ $restore ? old('version', $entry?->version ?? 0) : ($entry?->version ?? 0) }}">
                <div class="rounded-xl border border-line bg-surface-subtle p-4">
                    <h2 id="isi-nilai-heading" class="ui-card-title">{{ $selectedEnrollment->student->name }}</h2>
                    <p class="mt-1 font-semibold">NIS {{ $selectedEnrollment->student->nis }} · {{ $selectedClass->name }}</p>
                    <p class="mt-1 text-muted">{{ $labels[$selectedState['status']] }}</p>
                </div>
                @if($selectedState['locked'])<p class="text-muted">Leger/rapor sudah dikunci. Isian tidak dapat diubah.</p>@endif
                <fieldset @disabled($selectedState['locked']) class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach($fields as $field=>$label)
                    <label class="ui-field {{ $field==='juz_tested' ? 'col-span-2 lg:col-span-4' : '' }}"><span>{{ $label }}</span>
                        <input class="form-input" name="{{ $field }}" value="{{ $restore ? old($field, $selectedState['values'][$keys[$field]]) : $selectedState['values'][$keys[$field]] }}" @if($field==='juz_tested')type="text" maxlength="255" placeholder="Contoh: Juz 29 dan 30"@else type="number" min="0" max="100" step="0.01"@endif>
                    </label>
                    @endforeach
                </fieldset>
                @unless($selectedState['locked'])
                    <div class="flex flex-wrap items-center gap-3"><button class="btn btn-primary" name="action" value="final">Simpan nilai final</button><button class="btn btn-outline" name="action" value="draft">Simpan draf</button><span class="text-sm text-muted">Untuk {{ $selectedEnrollment->student->name }} · NIS {{ $selectedEnrollment->student->nis }}</span></div>
                @endunless
            </form>
        @else
            <p class="text-muted">{{ $selectedClass ? 'Pilih nama dan NIS untuk membuka satu formulir nilai.' : 'Mulai dengan memilih kelas. Formulir nilai akan muncul setelah peserta didik dipilih.' }}</p>
        @endif
        <section id="isian-saya" class="card-lg overflow-hidden" aria-labelledby="isian-saya-heading">
            <header class="p-5"><h2 id="isian-saya-heading" class="ui-card-title">Isian saya</h2><p class="mt-1 text-muted">Isian terakhir Anda per peserta didik pada periode {{ $term->academicYear?->name }} · {{ $term->name }}.</p></header>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead><tr class="border-t border-line">@foreach(['Nama / NIS','Kelas','Juz','Ujian','Sabaq','Sabqi','Manzil','Status','Tindakan'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($history as $saved)
                    @php
                        $enrollment = $historyStudents->get($saved->student_id);
                        $current = $historyStates->get($saved->student_id);
                        $superseded = $current['entry']->id !== $saved->id;
                    @endphp
                    <tr class="border-t border-line">
                        <td class="p-3 font-semibold">{{ $enrollment->student->name }}<span class="block font-normal text-muted">NIS {{ $enrollment->student->nis }}</span></td>
                        <td class="p-3">{{ $enrollment->classroomTerm->name }}</td>
                        @foreach($fields as $field=>$label)<td class="p-3">{{ $saved->$field ?? '—' }}</td>@endforeach
                        <td class="p-3">{{ $superseded ? 'Ada pembaruan dari guru lain' : $labels[$saved->status] }}<span class="block text-muted">{{ $saved->created_at?->format('d/m/Y H:i') }}</span></td>
                        <td class="p-3"><a class="btn btn-outline" href="{{ route('guru.tahfidz-report.index', ['academic_term_id'=>$term->id,'classroom_term_id'=>$enrollment->classroom_term_id,'enrollment_id'=>$enrollment->id]) }}">{{ $current['locked'] || $superseded ? 'Lihat nilai terkini' : 'Lihat / ubah' }}</a></td>
                    </tr>
                @empty<tr><td colspan="9" class="p-5 text-muted">Anda belum menyimpan isian Tahfidz rapor pada periode ini.</td></tr>@endforelse
                </tbody>
            </table></div>
            @if($history->hasPages())<div class="p-4 border-t border-line">{{ $history->links() }}</div>@endif
        </section>
    </div>
</x-layouts.portal>
