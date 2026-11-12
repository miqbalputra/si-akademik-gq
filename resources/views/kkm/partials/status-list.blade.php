<section class="rounded-2xl border border-line bg-surface" aria-labelledby="kkm-status-title">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line p-4 sm:p-5">
        <div>
            <h2 id="kkm-status-title" class="ui-card-title text-heading">Status KKM</h2>
            <p class="mt-1 text-theme-sm text-muted">{{ $scopes->total() }} kelompok mapel–mustawa{{ $manager && $rosterTerm ? ' · '.$rosterTerm->kkm_label : '' }}</p>
        </div>
        <details class="relative">
            <summary class="btn cursor-pointer">Ekspor rekap <span aria-hidden="true">⌄</span></summary>
            <div class="absolute right-0 z-10 mt-2 grid min-w-48 gap-1 rounded-xl border border-line bg-surface p-2 shadow-theme-lg">
                <a class="rounded-lg px-3 py-2 text-theme-sm hover:bg-surface-subtle" href="{{ route($prefix.'recap', ['format' => 'xlsx'] + request()->only('academic_term_id', 'mustawa', 'status')) }}">Ekspor rekap Excel</a>
                <a class="rounded-lg px-3 py-2 text-theme-sm hover:bg-surface-subtle" href="{{ route($prefix.'recap', ['format' => 'pdf'] + request()->only('academic_term_id', 'mustawa', 'status')) }}">Ekspor rekap PDF</a>
            </div>
        </details>
    </header>
    <div class="kkm-status-head bg-surface-subtle px-5 py-3 text-theme-xs text-muted" aria-hidden="true"><span>Mapel / Mustawa</span><span>Guru penyusun</span><span>Status pengajuan</span><span>KKM resmi</span><span>Tindakan</span></div>
    <div class="divide-y divide-line">
        @forelse($scopes as $scope)
            @php
                $visibleRevision = ($manager || $writers->has($scope->id)) ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision;
                $status = $visibleRevision?->status;
                $tone = match($status) { 'submitted' => 'badge-amber', 'needs_revision' => 'badge-red', 'approved' => 'badge-green', 'school_fixed' => 'badge-indigo', 'draft' => 'badge-blue', default => 'badge-slate' };
            @endphp
            <article class="kkm-status-row p-4 sm:p-5" aria-label="{{ $scope->subject?->name }} Mustawa {{ $scope->mustawa }}">
                <div class="kkm-status-subject min-w-0">
                    <h3 class="font-semibold text-heading">{{ $scope->subject?->name }}</h3>
                    <p class="mt-1 text-theme-sm text-muted">Mustawa {{ $scope->mustawa }}@if(! $manager) · {{ $scope->academicTerm?->kkm_label }}@endif</p>
                </div>
                <div class="kkm-status-teacher min-w-0 text-theme-sm"><p class="mb-1 text-theme-xs text-muted lg:hidden">Guru penyusun</p>{{ $visibleRevision?->is_school_fixed ? 'Sekolah/Kabag Diniyyah' : ($scope->assignedTeacher?->name ?? 'Belum ditugaskan') }}</div>
                <div class="min-w-0"><p class="mb-1 text-theme-xs text-muted lg:hidden">Status pengajuan</p><span class="badge {{ $tone }}">{{ $visibleRevision?->status_label ?? 'Belum dibuat' }}</span>@if($scope->workingRevision && $scope->activeRevision)<p class="mt-1 text-theme-xs text-muted">KKM lama tetap berlaku</p>@endif</div>
                <div><p class="mb-1 text-theme-xs text-muted lg:hidden">KKM resmi</p>@if($scope->activeRevision)<strong class="text-theme-xl text-heading">{{ $scope->activeRevision->final_kkm }}</strong>@else<span class="text-theme-sm text-muted">Menunggu ACC</span>@endif</div>
                <div class="kkm-status-action"><a class="btn btn-sm {{ $manager && $status === 'submitted' ? 'btn-primary' : '' }} w-full" href="{{ route($prefix.'show', $scope) }}">{{ $manager && $status === 'submitted' ? 'Tinjau pengajuan' : 'Buka KKM' }} <span aria-hidden="true">→</span></a></div>
            </article>
        @empty
            <div class="px-5 py-10 text-center">
                <p class="font-medium text-heading">Belum ada KKM yang dapat ditampilkan.</p>
                <p class="mt-2 text-theme-sm text-muted">{{ $manager ? 'Ubah filter atau buka Guru Penyusun untuk memulai penugasan penyusunan KKM.' : 'KKM akan muncul setelah Anda ditugaskan sebagai penyusun atau KKM mapel Anda disetujui.' }}</p>
                @if($manager)<a class="btn mt-4" href="{{ route($prefix.'index', $contextFilters + ['view' => 'assignments']) }}">Kelola guru penyusun</a>@endif
            </div>
        @endforelse
    </div>
</section>
