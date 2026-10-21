<x-layouts.portal title="Penetapan KKM" :portalLabel="$prefix === 'admin.kkm.' ? 'Portal Manajemen' : ($prefix === 'kabag.kkm.' ? 'Portal Kabag Diniyyah' : 'Portal Guru')" breadcrumb="KKM Diniyyah">
    <div class="space-y-6">
        <header><h1 class="ui-page-title text-heading">Penetapan KKM</h1><p class="mt-2 text-muted">Satu KKM bersama per mapel, mustawa, dan semester untuk kelas ikhwan–akhwat.</p></header>
        @if(session('status'))<div role="status" class="rounded-xl bg-success-soft p-4 text-success-ink">{{ session('status') }}</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        @if($manager)
            @include('kkm.partials.assignments')
        @endif

        <form method="GET" class="grid gap-3 rounded-2xl border border-line bg-surface p-5 md:grid-cols-4">
            <label class="ui-field"><span>Semester</span><select name="academic_term_id" class="form-input" @if($teacherPortal) onchange="this.form.elements.mustawa.value = ''; this.form.requestSubmit();" @endif><option value="">{{ $teacherPortal ? 'Semua semester saya' : 'Semua semester' }}</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(request('academic_term_id') == $term->id)>{{ $term->academicYear?->name }} {{ $term->name }}</option>@endforeach</select></label>
            <label class="ui-field"><span>Mustawa</span><select name="mustawa" class="form-input"><option value="">{{ $teacherPortal ? 'Semua mustawa saya' : 'Semua mustawa' }}</option>@foreach($mustawaOptions as $level)<option value="{{ $level }}" @selected(request('mustawa') == $level)>Mustawa {{ $level }}</option>@endforeach</select></label>
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
