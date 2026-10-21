<x-layouts.portal title="Penetapan KKM" :portalLabel="$prefix === 'admin.kkm.' ? 'Portal Manajemen' : ($prefix === 'kabag.kkm.' ? 'Portal Kabag Diniyyah' : 'Portal Guru')" breadcrumb="KKM Diniyyah">
    <div class="space-y-6">
        <header><h1 class="ui-page-title text-heading">Penetapan KKM</h1><p class="mt-2 text-muted">Satu KKM bersama per mapel, mustawa, dan semester untuk kelas ikhwan–akhwat.</p></header>
        @if(session('status'))<div role="status" class="rounded-xl bg-success-soft p-4 text-success-ink">{{ session('status') }}</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        @if($manager)
        <section class="rounded-2xl border border-line bg-surface p-5" x-data="{ options: @js($assignmentOptions), selected: @js((string)old('class_subject_id', '')), get teachers() { return this.options.find(o => String(o.id) === String(this.selected))?.teachers || []; } }">
            <h2 class="ui-card-title text-heading">Tetapkan guru penyusun</h2>
            <p class="mt-1 text-theme-sm text-muted">Pilih mapel, mustawa, semester, lalu guru pengampu. Penugasan berlaku untuk seluruh kelas paralel.</p>
            <form method="POST" action="{{ route($prefix.'assign') }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf
                <label class="ui-field"><span>Mapel · Mustawa · Semester</span><select class="form-input" name="class_subject_id" x-model="selected" required><option value="">Pilih lingkup KKM</option>@foreach($assignmentOptions as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach</select></label>
                <label class="ui-field"><span>Guru penyusun</span><select class="form-input" name="teacher_id" required><option value="">Pilih guru</option><template x-for="teacher in teachers" :key="teacher.id"><option :value="teacher.id" x-text="teacher.name"></option></template></select></label>
                <div class="flex items-end"><button class="btn btn-primary" type="submit">Tetapkan penyusun</button></div>
            </form>
            @if(empty($assignmentOptions))<p class="mt-4 text-muted">Belum ada mapel kelas dengan tingkat Mustawa 1–6. Lengkapi kelas, semester, mapel, dan penugasan mengajar terlebih dahulu.</p>@endif
        </section>
        @endif

        <form method="GET" class="grid gap-3 rounded-2xl border border-line bg-surface p-5 md:grid-cols-4">
            <label class="ui-field"><span>Semester</span><select name="academic_term_id" class="form-input"><option value="">Semua semester</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(request('academic_term_id') == $term->id)>{{ $term->academicYear?->name }} {{ $term->name }}</option>@endforeach</select></label>
            <label class="ui-field"><span>Mustawa</span><select name="mustawa" class="form-input"><option value="">Semua mustawa</option>@for($level=1;$level<=6;$level++)<option value="{{ $level }}" @selected(request('mustawa') == $level)>Mustawa {{ $level }}</option>@endfor</select></label>
            <label class="ui-field"><span>Status</span><select name="status" class="form-input"><option value="">Semua status</option>@foreach(['draft'=>'Draf','submitted'=>'Menunggu ACC','needs_revision'=>'Perlu Revisi','approved'=>'Disetujui','unassigned'=>'Belum ditugaskan'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="flex items-end gap-3"><button class="btn btn-primary">Terapkan</button><a class="underline" href="{{ route($prefix.'index') }}">Reset</a></div>
        </form>
        <div class="flex flex-wrap gap-3"><a class="btn" href="{{ route($prefix.'recap', ['format'=>'xlsx'] + request()->only('academic_term_id','mustawa','status')) }}">Ekspor rekap Excel</a><a class="btn" href="{{ route($prefix.'recap', ['format'=>'pdf'] + request()->only('academic_term_id','mustawa','status')) }}">Ekspor rekap PDF</a></div>
        <section class="overflow-x-auto rounded-2xl border border-line bg-surface">
            <table class="w-full text-left text-theme-sm"><thead><tr class="border-b border-line"><th class="p-4">Mapel / Semester</th><th class="p-4">Mustawa</th><th class="p-4">Penyusun</th><th class="p-4">Status</th><th class="p-4">KKM resmi</th><th class="p-4">Detail</th></tr></thead><tbody>
                @forelse($scopes as $scope)
                    @php($visibleRevision = ($manager || $access->writes(auth()->user(), $scope)) ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision)
                    <tr class="border-b border-line"><td class="p-4"><strong>{{ $scope->subject?->name }}</strong><p class="mt-1 text-muted">{{ $scope->academicTerm?->academicYear?->name }} {{ $scope->academicTerm?->name }}</p></td><td class="p-4">{{ $scope->mustawa }}</td><td class="p-4">{{ $scope->assignedTeacher?->name ?? 'Belum ditugaskan' }}</td><td class="p-4">{{ $visibleRevision?->status_label ?? 'Belum dibuat' }}</td><td class="p-4">{{ $scope->activeRevision?->final_kkm ?? 'Menunggu ACC' }}</td><td class="p-4"><a class="font-medium underline" href="{{ route($prefix.'show', $scope) }}">Buka KKM</a></td></tr>
                @empty<tr><td colspan="6" class="p-6 text-muted">Belum ada KKM yang dapat ditampilkan.</td></tr>@endforelse
            </tbody></table>
        </section>
        {{ $scopes->links() }}
    </div>
</x-layouts.portal>
