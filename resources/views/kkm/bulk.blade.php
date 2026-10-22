<x-layouts.portal title="Tetapkan Massal Penyusun KKM" :portalLabel="$prefix === 'admin.kkm.' ? 'Portal Manajemen' : 'Portal Kabag Diniyyah'" breadcrumb="KKM Diniyyah">
    <div class="space-y-6">
        <header><a class="text-theme-sm underline" href="{{ route($prefix.'index', ['academic_term_id' => $term?->id]) }}">Kembali ke KKM</a><h1 class="ui-page-title mt-3 text-heading">Tetapkan Massal Penyusun KKM</h1><p class="mt-2 text-muted">Pilih gender guru, periksa calon per mapel dan mustawa, lalu terapkan baris terpilih. Tugas yang sudah ditetapkan tetap dipertahankan.</p></header>
        @if($errors->any())<div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="GET" class="grid gap-4 rounded-2xl border border-line bg-surface p-5 md:grid-cols-2 xl:grid-cols-5">
            <label class="ui-field"><span>Semester</span><select name="academic_term_id" class="form-input" required>@foreach($terms as $option)<option value="{{ $option->id }}" @selected($term?->id === $option->id)>{{ $option->academicYear?->name }} {{ $option->name }}</option>@endforeach</select></label>
            <label class="ui-field"><span>Mustawa</span><select name="mustawa" class="form-input"><option value="">Semua mustawa</option>@for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected(request('mustawa') == $i)>Mustawa {{ $i }}</option>@endfor</select></label>
            <label class="ui-field"><span>Mapel</span><select name="subject_id" class="form-input"><option value="">Semua mapel</option>@foreach($subjects as $subject)<option value="{{ $subject['subject_id'] }}" @selected(request('subject_id') == $subject['subject_id'])>{{ $subject['subject'] }}</option>@endforeach</select></label>
            <label class="ui-field"><span>Gender penyusun awal</span><select name="gender" class="form-input"><option value="female" @selected($gender === 'female')>Guru Perempuan</option><option value="male" @selected($gender === 'male')>Guru Laki-laki</option></select></label>
            <div class="flex items-end"><button type="submit" class="btn btn-primary">Tampilkan Pratinjau</button></div>
        </form>
        @include('kkm.partials.issues')
        @if($term && ! $term->kkm_workflow_enabled)<p role="status" class="rounded-xl border border-line bg-surface p-4">Alur KKM semester ini belum aktif. Penetapan massal tidak mengaktifkan semester lama.</p>@endif
        <form method="POST" action="{{ route($prefix.'bulk.apply') }}" class="space-y-4">@csrf
            <input type="hidden" name="academic_term_id" value="{{ $term?->id }}">
            <input type="hidden" name="preview_token" value="{{ $previewToken }}">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="ui-card-title text-heading">Pratinjau penugasan · {{ count($roster['rows']) }} mapel–mustawa</h2><p class="mt-1 text-theme-sm text-muted">{{ $term?->academicYear?->name }} {{ $term?->name }} · Jika calon lebih dari satu, pilih guru secara manual atau keluarkan baris dari batch.</p></div><button class="btn btn-primary" type="submit" @disabled(! $term?->kkm_workflow_enabled || ! collect($roster['rows'])->contains(fn($r) => ! $r['assigned_teacher_id'] && ! $r['blocked'] && count($r['teachers'])))>Terapkan Penugasan</button></div>
            <div class="space-y-4">
                @forelse($roster['rows'] as $row)
                    @php
                        $id = $row['class_subject_id'];
                        $initialGender = old('genders.'.$id, $gender);
                        $candidates = array_values(array_filter($row['teachers'], fn($t) => $t['gender'] === $initialGender));
                        $editable = ! $row['assigned_teacher_id'] && ! $row['blocked'];
                        $initialTeacher = (string) old('teachers.'.$id, count($candidates) === 1 ? $candidates[0]['id'] : '');
                        $initialSelected = old('selected') !== null ? in_array($id, old('selected', [])) : count($candidates) === 1;
                    @endphp
                    <section class="rounded-2xl border border-line bg-surface p-5" @if($editable) x-data="{ teachers: @js($row['teachers']), gender: @js($initialGender), teacher: @js($initialTeacher), selected: @js($initialSelected), get candidates() { return this.teachers.filter(t => t.gender === this.gender); }, changeGender() { this.teacher = this.candidates.length === 1 ? String(this.candidates[0].id) : ''; this.selected = this.candidates.length === 1; } }" @endif>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div><h3 class="font-semibold text-heading">{{ $row['subject'] }} · Mustawa {{ $row['mustawa'] }}</h3><p class="mt-1 text-theme-sm text-muted">{{ implode(', ', $row['classes']) }}</p>@include('kkm.partials.candidates', ['teachers' => $row['teachers']])</div>
                            <div>
                                @if($row['school_managed'])<p class="font-medium">{{ $row['blocked'] }}</p>
                                @elseif($row['assigned_teacher_id'])<p class="font-medium">Sudah ditetapkan: {{ $row['assigned_teacher'] ?? 'Guru sebelumnya tidak tersedia' }}</p><p class="mt-1 text-theme-sm text-muted">Dilewati pada penetapan massal.</p>
                                @elseif($row['blocked'])<p class="text-muted">{{ $row['blocked'] }}</p>
                                @else
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="ui-field"><span>Gender guru · {{ $row['subject'] }} M{{ $row['mustawa'] }}</span><select class="form-input" name="genders[{{ $id }}]" x-model="gender" @change="changeGender()"><option value="female">Perempuan</option><option value="male">Laki-laki</option></select></label>
                                        <label class="ui-field"><span>Penyusun · {{ $row['subject'] }} M{{ $row['mustawa'] }}</span><select class="form-input" name="teachers[{{ $id }}]" x-model="teacher" :required="selected" :disabled="!candidates.length"><option value="">Pilih guru</option><template x-for="candidate in candidates" :key="candidate.id"><option :value="String(candidate.id)" x-text="candidate.name"></option></template></select></label>
                                    </div>
                                    <p class="mt-2 text-theme-sm text-muted" x-text="candidates.length === 0 ? 'Tidak ada calon dengan gender ini. Lengkapi penugasan/akun atau pilih gender lain.' : (candidates.length === 1 ? 'Satu calon terdeteksi dan dipilih otomatis.' : 'Beberapa calon terdeteksi. Pilih satu guru secara manual.')"></p>
                                    <label class="mt-3 flex items-center gap-2 text-theme-sm"><input type="checkbox" name="selected[]" value="{{ $id }}" x-model="selected" :disabled="!candidates.length"><span>Sertakan {{ $row['subject'] }} Mustawa {{ $row['mustawa'] }}</span></label>
                                @endif
                            </div>
                        </div>
                    </section>
                @empty<p class="rounded-xl border border-line bg-surface p-5 text-muted">Tidak ada mapel kelas sesuai filter.</p>@endforelse
            </div>
        </form>
    </div>
</x-layouts.portal>
