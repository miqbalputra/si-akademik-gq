@php
    $isManagement = $scope === 'management';
    $portalLabel = $portalLabel ?? ($isManagement ? 'Portal Kabag Tahfidz' : 'Portal Guru');
    $breadcrumb = $isManagement ? 'Laporan Tasmi\'' : ($scope === 'homeroom' ? "Tasmi' Kelas Saya" : "Riwayat Tasmi'");
    $summary = $report['summary'];
    $records = $report['records'];
    $exportQuery = request()->query();
@endphp

<x-layouts.portal :title="$pageTitle" :portal-label="$portalLabel" :breadcrumb="$breadcrumb">
    <section class="space-y-6">
        <header class="portal-page-header flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 text-success-ink transition hover:text-success-ink text-theme-sm font-medium">
                    <span aria-hidden="true">←</span> {{ $backLabel }}
                </a>
                <p class="mt-4 text-theme-xs font-normal uppercase text-success-ink">{{ $isManagement ? 'Monitoring Tahfidz' : 'Laporan Tasmi\'' }}</p>
                <h1 class="mt-1 text-heading ui-page-title">{{ $pageTitle }}</h1>
                <p class="mt-2 max-w-2xl text-theme-sm font-normal text-muted">{{ $pageDescription }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($canEdit)
                    <a href="{{ route('guru.tasmi.create') }}" class="btn btn-primary text-theme-sm font-medium">Input Tasmi' baru</a>
                @endif
                <a href="{{ route($exportRoute, array_merge($exportQuery, ['format' => 'xlsx'])) }}" class="btn btn-outline text-theme-sm font-medium">Download Excel</a>
                <a href="{{ route($exportRoute, array_merge($exportQuery, ['format' => 'pdf'])) }}" class="btn btn-outline text-theme-sm font-medium">Download PDF</a>
            </div>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article class="metric-card"><p>Total setoran</p><strong>{{ $summary['total_records'] }}</strong><span>hasil sesuai filter</span></article>
            <article class="metric-card"><p>Santri</p><strong>{{ $summary['total_students'] }}</strong><span>santri tercatat</span></article>
            <article class="metric-card"><p>Kelas</p><strong>{{ $summary['total_classes'] }}</strong><span>kelas tercatat</span></article>
            <article class="metric-card"><p>Tasmi' 1 juz</p><strong>{{ $summary['one_juz'] }}</strong><span>setoran 1 juz</span></article>
            <article class="metric-card"><p>Tasmi' 5 juz</p><strong>{{ $summary['five_juz'] }}</strong><span>setoran 5 juz</span></article>
        </div>

        <section class="ui-card p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-soft ui-metric-label">Filter laporan</p><h2 class="mt-1 text-heading ui-card-title">Temukan hasil yang diperlukan</h2></div>
                <a href="{{ route($resetRoute) }}" class="text-theme-sm font-medium text-muted underline decoration-success-300 decoration-2 underline-offset-4 hover:text-success-ink">Reset filter</a>
            </div>
            <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="ui-field ui-form-label"><span>Semester</span><select name="academic_term_id"><option value="">Semua semester</option>@foreach($options['terms'] as $term)<option value="{{ $term->id }}" @selected((string)($filters['academic_term_id'] ?? '') === (string)$term->id)>{{ $term->name }}</option>@endforeach</select></label>
                <label class="ui-field ui-form-label"><span>Kelas</span><select name="classroom_term_id"><option value="">Semua kelas</option>@foreach($options['classroomTerms'] as $classroomTerm)<option value="{{ $classroomTerm->id }}" @selected((string)($filters['classroom_term_id'] ?? '') === (string)$classroomTerm->id)>{{ $classroomTerm->classroom?->name ?? $classroomTerm->name }}</option>@endforeach</select></label>
                <label class="ui-field ui-form-label"><span>Santri</span><select name="student_id"><option value="">Semua santri</option>@foreach($options['students'] as $student)<option value="{{ $student->id }}" @selected((string)($filters['student_id'] ?? '') === (string)$student->id)>{{ $student->name }}@if($student->nis) · {{ $student->nis }}@endif</option>@endforeach</select></label>
                @if($isManagement)
                    <label class="ui-field ui-form-label"><span>PJ Tasmi'</span><select name="examiner_teacher_id"><option value="">Semua PJ Tasmi'</option>@foreach($options['examiners'] as $examiner)<option value="{{ $examiner->id }}" @selected((string)($filters['examiner_teacher_id'] ?? '') === (string)$examiner->id)>{{ $examiner->name }}</option>@endforeach</select></label>
                @endif
                <label class="ui-field ui-form-label"><span>Jenis Tasmi'</span><select name="exam_type"><option value="">Semua jenis</option>@foreach(\App\Models\TasmiRecord::examTypeOptions() as $value => $label)<option value="{{ $value }}" @selected(($filters['exam_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="ui-field ui-form-label"><span>Juz</span><select name="juz"><option value="">Semua juz</option>@for($juz = 1; $juz <= 30; $juz++)<option value="{{ $juz }}" @selected((string)($filters['juz'] ?? '') === (string)$juz)>Mencakup Juz {{ $juz }}</option>@endfor</select></label>
                <label class="ui-field ui-form-label"><span>Predikat</span><select name="predicate"><option value="">Semua predikat</option>@foreach(\App\Models\TasmiRecord::predicateOptions() as $value => $label)<option value="{{ $value }}" @selected(($filters['predicate'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="ui-field ui-form-label"><span>Cari santri</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau NIS"></label>
                <label class="ui-field ui-form-label"><span>Dari tanggal</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="ui-field ui-form-label"><span>Sampai tanggal</span><input type="date" name="date_until" value="{{ $filters['date_until'] ?? '' }}"></label>
                <div class="flex items-end"><button type="submit" class="btn btn-primary w-full text-theme-sm font-medium">Terapkan filter</button></div>
            </form>
        </section>

        <section class="grid gap-3 md:grid-cols-4" aria-label="Distribusi predikat">
            @foreach(\App\Models\TasmiRecord::predicateOptions() as $value => $label)
                <article class="rounded-2xl border border-line bg-surface px-4 py-3 shadow-sm"><p class="text-theme-xs font-normal text-muted">{{ $label }}</p><p class="mt-1 text-heading ui-metric-value">{{ $summary['predicates'][$value] ?? 0 }}</p></article>
            @endforeach
        </section>

        <section class="ui-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6"><div><p class="text-theme-xs font-normal uppercase text-soft">Rincian hasil</p><h2 class="mt-1 text-heading ui-card-title">{{ $records->total() }} setoran ditemukan</h2></div></div>
            @if($records->isEmpty())
                <div class="p-10 text-center"><p class="text-theme-sm font-normal text-muted">Belum ada hasil Tasmi' yang sesuai dengan filter ini.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-theme-sm">
                        <thead class="bg-surface-subtle text-muted text-theme-xs font-medium"><tr><th class="px-5 py-3 text-theme-xs font-medium">Tanggal</th><th class="px-5 py-3 text-theme-xs font-medium">Santri</th><th class="px-5 py-3 text-theme-xs font-medium">Kelas</th><th class="px-5 py-3 text-theme-xs font-medium">Jenis / Juz</th><th class="px-5 py-3 text-theme-xs font-medium">Predikat</th><th class="px-5 py-3 text-theme-xs font-medium">PJ Tasmi'</th><th class="px-5 py-3 text-right text-theme-xs font-medium">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach($records as $record)
                                @php($detailRoute = $scope === 'management' ? 'admin.tasmi-report.show' : ($scope === 'homeroom' ? 'guru.tasmi-wali.show' : 'guru.tasmi.edit'))
                                <tr class="transition hover:bg-success-soft/40"><td class="px-5 py-4 font-medium text-body">{{ $record->exam_date?->locale('id')->translatedFormat('d M Y') }}<span class="mt-0.5 block text-theme-xs font-normal text-soft">{{ $record->hijri_date ?: '' }}</span></td><td class="px-5 py-4"><p class="font-medium text-heading">{{ $record->student?->name ?? '-' }}</p><p class="mt-0.5 text-theme-xs font-normal text-soft">{{ $record->student?->nis ? 'NIS '.$record->student->nis : '' }}</p></td><td class="px-5 py-4 font-medium text-body">{{ $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name ?? '-' }}</td><td class="px-5 py-4"><p class="font-medium text-heading">{{ \App\Models\TasmiRecord::examTypeOptions()[$record->exam_type] ?? $record->exam_type }}</p><p class="mt-0.5 text-theme-xs font-normal text-muted">{{ $record->juz_range_label }}</p></td><td class="px-5 py-4"><span class="rounded-full bg-success-soft px-2.5 py-1 text-success-ink text-theme-xs font-medium">{{ \App\Models\TasmiRecord::predicateLabel($record->predicate) }}</span></td><td class="px-5 py-4 font-medium text-body">{{ $record->examinerTeacher?->name ?? '-' }}</td><td class="px-5 py-4 text-right"><a href="{{ route($detailRoute, $record) }}" class="text-theme-sm font-medium text-success-ink underline decoration-success-300 decoration-2 underline-offset-4">{{ $canEdit ? 'Kelola' : 'Detail' }}</a></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4 sm:px-6">{{ $records->links() }}</div>
            @endif
        </section>
    </section>
</x-layouts.portal>
