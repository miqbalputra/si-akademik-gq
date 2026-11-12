<x-layouts.portal title="Penetapan KKM" :portalLabel="$prefix === 'admin.kkm.' ? 'Portal Manajemen' : ($prefix === 'kabag.kkm.' ? 'Portal Kabag Diniyyah' : 'Portal Guru')" breadcrumb="KKM Diniyyah">
    @php($contextFilters = request()->only('academic_term_id', 'mustawa'))
    <div class="space-y-4">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="ui-page-title text-heading">Penetapan KKM</h1>
                <p class="mt-2 text-theme-sm text-muted">{{ $manager ? 'Pantau pengajuan, tinjau KKM, dan kelola guru penyusun dalam satu tempat.' : 'Satu KKM bersama per mapel, mustawa, dan semester untuk kelas ikhwan–akhwat.' }}</p>
            </div>
            @if($teacherPortal && $writers->isNotEmpty())
                @include('kkm.partials.guide')
            @endif
        </header>
        @if(session('status'))<div role="status" class="rounded-xl bg-success-soft p-4 text-success-ink">{{ session('status') }}</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger-line bg-danger-soft p-4 text-danger-ink">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        @if($manager)
            <nav class="kkm-view-nav" aria-label="Bagian pengelolaan KKM">
                <a href="{{ route($prefix.'index', $contextFilters + ['view' => 'status']) }}" @if($activeView === 'status') aria-current="page" @endif>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H6a2 2 0 0 0-2 2v13h16V7a2 2 0 0 0-2-2h-3M9 3h6v4H9zM8 12h8M8 16h5" /></svg>
                    Status KKM
                </a>
                <a href="{{ route($prefix.'index', $contextFilters + ['view' => 'assignments']) }}" @if($activeView === 'assignments') aria-current="page" @endif>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75" /><circle cx="9" cy="7" r="4" /></svg>
                    Guru Penyusun
                </a>
            </nav>
            @if($activeView === 'status')
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan KKM semester terpilih">
                @foreach([
                    'submitted' => ['Menunggu ACC', 'Tinjau pengajuan', 'warning'],
                    'needs_revision' => ['Perlu revisi', 'Lihat catatan revisi', 'danger'],
                    'approved' => ['Disetujui', 'Lihat KKM disetujui', 'success'],
                    'unassigned' => ['Belum ditugaskan', 'Tetapkan guru penyusun', 'info'],
                ] as $key => [$label, $hint, $tone])
                    <a class="kkm-stat kkm-stat-{{ $tone }}" href="{{ route($prefix.'index', $contextFilters + ['view' => $key === 'unassigned' ? 'assignments' : 'status', 'status' => $key]) }}" @if(request('status') === $key) aria-current="true" @endif>
                        <span class="kkm-stat-label">{{ $label }}</span>
                        <strong class="kkm-stat-value">{{ $summary[$key] }}</strong>
                        <span class="kkm-stat-hint">{{ $hint }} <span aria-hidden="true">→</span></span>
                    </a>
                @endforeach
            </div>
            @endif
        @endif

        <details class="kkm-filters rounded-2xl border border-line bg-surface p-4" aria-label="Filter KKM" open x-data x-init="const media = window.matchMedia('(min-width: 768px)'); $el.open = media.matches; media.addEventListener('change', event => $el.open = event.matches)">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 md:hidden"><span><span class="font-medium">Filter tampilan</span><span class="mt-1 block text-theme-xs text-muted">{{ $manager ? $rosterTerm?->kkm_label : 'Semester, mustawa, dan status' }}</span></span><span class="text-theme-sm text-muted">Ubah <span class="kkm-filter-chevron inline-block" aria-hidden="true">⌄</span></span></summary>
            <form method="GET" action="{{ route($prefix.'index') }}" class="mt-4 grid grid-cols-2 items-end gap-3 md:mt-0 lg:grid-cols-4">
                @if($manager)<input type="hidden" name="view" value="{{ $activeView }}">@endif
                <label class="ui-field col-span-2 sm:col-span-1"><span>Semester</span><select name="academic_term_id" class="form-input" @if($teacherPortal) onchange="this.form.elements.mustawa.value = ''; this.form.requestSubmit();" @endif>
                    @if(! $manager)<option value="">Semua semester saya</option>@endif
                    @foreach($terms as $term)<option value="{{ $term->id }}" @selected(request('academic_term_id') == $term->id)>{{ $term->kkm_label }}</option>@endforeach
                </select></label>
                <label class="ui-field"><span>Mustawa</span><select name="mustawa" class="form-input"><option value="">{{ $teacherPortal ? 'Semua mustawa saya' : 'Semua mustawa' }}</option>@foreach($mustawaOptions as $level)<option value="{{ $level }}" @selected(request('mustawa') == $level)>Mustawa {{ $level }}</option>@endforeach</select></label>
                <label class="ui-field"><span>{{ $activeView === 'assignments' ? 'Penugasan' : 'Status KKM' }}</span><select name="status" class="form-input">
                    <option value="">{{ $activeView === 'assignments' ? 'Semua penugasan' : 'Semua status' }}</option>
                    @foreach($activeView === 'assignments' ? ['unassigned' => 'Belum ditugaskan'] : ['submitted' => 'Menunggu ACC', 'needs_revision' => 'Perlu Revisi', 'draft' => 'Draf', 'approved' => 'Disetujui', 'school_fixed' => 'Ditetapkan Sekolah', 'unassigned' => 'Belum ditugaskan'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select></label>
                <div class="col-span-2 flex items-center gap-3 sm:col-span-1"><button type="submit" class="btn btn-primary flex-1">Tampilkan</button><a class="btn" href="{{ route($prefix.'index', $manager ? ['academic_term_id' => $rosterTerm?->id, 'view' => $activeView] : []) }}">Reset</a></div>
            </form>
        </details>

        @if($manager && $activeView === 'assignments')
            @include('kkm.partials.assignments')
        @else
            @include('kkm.partials.status-list')
            {{ $scopes->links() }}
        @endif
    </div>
</x-layouts.portal>
