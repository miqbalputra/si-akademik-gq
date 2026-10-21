<section class="rounded-2xl border border-line bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="ui-card-title text-heading">Tetapkan guru penyusun</h2><p class="mt-1 text-theme-sm text-muted">Rekap otomatis dari tugas mengajar. Satu penyusun berlaku untuk seluruh kelas paralel sejenjang.</p></div>
        <a class="btn btn-primary" href="{{ route($prefix.'bulk.preview', ['academic_term_id' => $rosterTerm?->id, 'mustawa' => request('mustawa')]) }}">Tetapkan Massal</a>
    </div>
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="ui-field"><span>Semester rekap pengajar</span><select name="academic_term_id" class="form-input">@foreach($terms as $term)<option value="{{ $term->id }}" @selected($rosterTerm?->id === $term->id)>{{ $term->academicYear?->name }} {{ $term->name }}</option>@endforeach</select></label>
        <button class="btn" type="submit">Tampilkan pengajar</button>
    </form>
    <div class="mt-4">@include('kkm.partials.issues')</div>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-theme-sm">
            <thead><tr class="border-b border-line"><th class="p-3">Mapel / Mustawa</th><th class="p-3">Kelas dan pengajar</th><th class="p-3">Penugasan penyusun</th></tr></thead>
            <tbody>
            @forelse($roster['rows'] as $row)
                <tr class="border-b border-line align-top">
                    <td class="p-3"><strong>{{ $row['subject'] }}</strong><p class="mt-1 text-muted">Mustawa {{ $row['mustawa'] }}</p></td>
                    <td class="min-w-64 p-3"><p>{{ implode(', ', $row['classes']) }}</p>@include('kkm.partials.candidates', ['teachers' => $row['teachers']])</td>
                    <td class="min-w-64 p-3">
                        <p class="mb-2">Penyusun: <strong>{{ $row['assigned_teacher'] ?? ($row['assigned_teacher_id'] ? 'Guru sebelumnya tidak tersedia' : 'Belum ditugaskan') }}</strong></p>
                        @if($row['blocked'])<p class="text-muted">{{ $row['blocked'] }}</p>
                        @elseif($row['teachers'])
                            <form method="POST" action="{{ route($prefix.'assign') }}" class="space-y-2">@csrf
                                <input type="hidden" name="class_subject_id" value="{{ $row['class_subject_id'] }}">
                                <label class="ui-field"><span class="sr-only">Penyusun {{ $row['subject'] }} Mustawa {{ $row['mustawa'] }}</span>
                                    <select name="teacher_id" class="form-input" required>
                                        <option value="">Pilih guru penyusun</option>
                                        @foreach($row['teachers'] as $teacher)<option value="{{ $teacher['id'] }}" @selected((int) $row['assigned_teacher_id'] === $teacher['id'])>{{ $teacher['name'] }} · {{ ['male'=>'Laki-laki','female'=>'Perempuan'][$teacher['gender']] ?? 'Gender belum diisi' }}</option>@endforeach
                                    </select>
                                </label>
                                <button class="btn" type="submit">{{ $row['assigned_teacher_id'] ? 'Ganti penyusun' : 'Tetapkan penyusun' }}</button>
                                @if($row['assigned_teacher_id'])<p class="text-muted">Pergantian mengembalikan pengajuan berjalan ke draf. KKM yang sudah disetujui tetap berlaku.</p>@endif
                            </form>
                        @else<p class="text-muted">Lengkapi penugasan guru aktif dan akun guru.</p>@endif
                    </td>
                </tr>
            @empty<tr><td colspan="3" class="p-4 text-muted">Belum ada mapel kelas yang dapat dikelompokkan pada semester ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>
