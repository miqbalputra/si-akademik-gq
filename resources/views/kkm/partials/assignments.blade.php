<section class="rounded-2xl border border-line bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="ui-card-title text-heading">Tetapkan guru penyusun</h2><p class="mt-1 text-theme-sm text-muted">{{ $rosterTerm?->kkm_label }} · {{ count($roster['rows']) }} kelompok mapel–mustawa. Satu penyusun untuk seluruh kelas paralel sejenjang.</p></div>
        <a class="btn btn-primary" href="{{ route($prefix.'bulk.preview', ['academic_term_id' => $rosterTerm?->id, 'mustawa' => request('mustawa')]) }}">Tetapkan Massal</a>
    </div>
    <div class="mt-4">@include('kkm.partials.issues')</div>
    <div class="mt-4 text-theme-sm">
        <div class="kkm-assignment-head border-b border-line bg-surface-subtle px-3 py-3 text-theme-xs text-muted" aria-hidden="true"><span>Mapel / Mustawa</span><span>Kelas dan pengajar</span><span>Penugasan penyusun</span></div>
        <div class="divide-y divide-line">
            @forelse($roster['rows'] as $row)
                <article class="kkm-assignment-row" aria-label="{{ $row['subject'] }} Mustawa {{ $row['mustawa'] }}">
                    <div class="min-w-0"><h3 class="font-semibold text-heading">{{ $row['subject'] }}</h3><p class="mt-1 text-muted">Mustawa {{ $row['mustawa'] }}</p></div>
                    <div class="min-w-0"><p class="mb-1 text-theme-xs text-muted lg:hidden">Kelas dan pengajar</p><p>{{ implode(', ', $row['classes']) }}</p>@include('kkm.partials.candidates', ['teachers' => $row['teachers']])</div>
                    <div class="min-w-0">
                        @if(! $row['school_managed'])<p class="mb-2">Penyusun: <strong>{{ $row['assigned_teacher'] ?? ($row['assigned_teacher_id'] ? 'Guru sebelumnya tidak tersedia' : 'Belum ditugaskan') }}</strong></p>@endif
                        @if($row['blocked'])<p class="text-muted">{{ $row['blocked'] }}</p>
                        @elseif($row['teachers'])
                            <details @if(! $row['assigned_teacher_id']) open @endif>
                                <summary class="cursor-pointer text-theme-sm font-medium text-heading">{{ $row['assigned_teacher_id'] ? 'Ganti penyusun' : 'Pilih guru penyusun' }}</summary>
                            <form method="POST" action="{{ route($prefix.'assign') }}" class="mt-3 space-y-2">@csrf
                                <input type="hidden" name="class_subject_id" value="{{ $row['class_subject_id'] }}">
                                <label class="ui-field"><span class="sr-only">Penyusun {{ $row['subject'] }} Mustawa {{ $row['mustawa'] }}</span>
                                    <select name="teacher_id" class="form-input" required>
                                        <option value="">Pilih guru penyusun</option>
                                        @foreach($row['teachers'] as $teacher)<option value="{{ $teacher['id'] }}" @selected((int) $row['assigned_teacher_id'] === $teacher['id'])>{{ $teacher['name'] }} · {{ ['male'=>'Laki-laki','female'=>'Perempuan'][$teacher['gender']] ?? 'Gender belum diisi' }}</option>@endforeach
                                    </select>
                                </label>
                                <button class="btn" type="submit">{{ $row['assigned_teacher_id'] ? 'Ganti penyusun' : 'Tetapkan penyusun' }}</button>
                                @if($row['assigned_teacher_id'])<p class="text-theme-xs text-muted">Pergantian mengembalikan pengajuan berjalan ke draf. KKM yang sudah disetujui tetap berlaku.</p>@endif
                            </form>
                            </details>
                        @else<p class="text-muted">Lengkapi penugasan guru aktif dan akun guru.</p>@endif
                    </div>
                </article>
            @empty<p class="p-4 text-muted">Belum ada mapel kelas yang dapat dikelompokkan pada semester ini.</p>@endforelse
        </div>
    </div>
</section>
