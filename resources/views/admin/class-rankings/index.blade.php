<x-layouts.portal title="Peringkat Semua Kelas" portalLabel="Portal Manajemen" breadcrumb="Leger & Rapor / Peringkat">
    @php
        $exportFilters = array_filter(['academic_term_id'=>$term?->id,'classroom_term_id'=>$selectedClassId,'include_archived'=>$includeArchived ? 1 : null], fn ($v) => $v !== null);
        $number = fn ($value) => $value === null ? '—' : number_format((float)$value, 2, ',', '.');
    @endphp
    <div class="space-y-5 min-w-0">
        <header><h1 class="ui-page-title">Peringkat Semua Kelas</h1><p class="mt-2 text-muted">Peringkat per kelas mengikuti Leger yang tersimpan. Nilai dan peringkat tidak dihitung ulang di halaman ini.</p></header>
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="GET" class="card-lg p-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="ui-field min-w-0"><span>Periode</span><select class="form-input" name="academic_term_id" onchange="this.form.elements['classroom_term_id'].value=''">
                @foreach($terms as $option)<option value="{{ $option->id }}" @selected($term?->id === $option->id)>{{ $option->academicYear?->name }} · {{ $option->name }}</option>@endforeach
            </select></label>
            <label class="ui-field min-w-0"><span>Kelas</span><select class="form-input" name="classroom_term_id"><option value="">Semua kelas</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected($selectedClassId === $classroom->id)>{{ $classroom->name }}</option>@endforeach</select></label>
            <div class="space-y-3"><label class="flex items-center gap-2"><input type="checkbox" name="include_archived" value="1" @checked($includeArchived)>Sertakan santri arsip</label><button class="btn btn-primary">Tampilkan peringkat</button></div>
        </form>
        @if($report && count($report['classes']))
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">@foreach(['classes'=>'Kelas','students'=>'Santri di Leger','complete'=>'Nilai lengkap','incomplete'=>'Belum lengkap'] as $key=>$label)<div class="card-lg p-4"><p class="text-muted">{{ $label }}</p><p class="text-2xl font-semibold">{{ $report['summary'][$key] }}</p></div>@endforeach</div>
            <div class="flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('admin.class-rankings.export', ['format'=>'xlsx'] + $exportFilters) }}">Unduh Excel {{ $selectedClassId ? 'kelas ini' : 'semua kelas' }}</a><a class="btn btn-outline" href="{{ route('admin.class-rankings.export', ['format'=>'pdf'] + $exportFilters) }}">Unduh PDF {{ $selectedClassId ? 'kelas ini' : 'semua kelas' }}</a></div>
            <p class="text-sm text-muted">Draf belum menjadi hasil final. Perubahan nilai setelah Leger disusun baru terlihat setelah wali kelas menyegarkan Leger. {{ $includeArchived ? 'Santri arsip ikut ditampilkan.' : 'Hanya santri aktif ditampilkan; nomor peringkat tersimpan dapat memiliki jeda.' }}</p>
            @foreach($report['classes'] as $class)
                <section class="card-lg overflow-hidden" aria-label="Peringkat {{ $class['name'] }}">
                    <header class="p-5 space-y-2"><h2 class="ui-card-title">{{ $class['name'] }}</h2><p class="text-sm text-muted">{{ !$class['snapshot_id'] ? 'Leger belum dibuat' : (in_array($class['status'], ['locked','published'], true) ? 'FINAL' : 'DRAF — belum final') }} · {{ $class['scope_label'] }} · Data per {{ $class['generated_at'] ?? '—' }}</p>
                        @if(!$selectedClassId)<div class="flex gap-3 text-sm"><a class="underline" href="{{ route('admin.class-rankings.export', ['format'=>'xlsx'] + array_replace($exportFilters, ['classroom_term_id'=>$class['id']])) }}">Excel kelas ini</a><a class="underline" href="{{ route('admin.class-rankings.export', ['format'=>'pdf'] + array_replace($exportFilters, ['classroom_term_id'=>$class['id']])) }}">PDF kelas ini</a></div>@endif
                    </header>
                    <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="border-y border-line bg-surface-subtle">@foreach(['Peringkat','Nama / NIS','Total Diniyyah','Total Tahfidz','Total akhir','Rata-rata','Kelengkapan'] as $heading)<th scope="col" class="p-3 whitespace-nowrap">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                        @forelse($class['rows'] as $row)<tr class="border-b border-line {{ !$row['complete'] ? 'bg-warning-soft' : '' }}"><td class="p-3 font-semibold">{{ $row['rank'] ?? '—' }}</td><td class="p-3 font-semibold">{{ $row['name'] }}<span class="block text-muted font-normal">{{ $row['nis'] }}{{ $row['archived'] ? ' · Arsip' : '' }}</span></td><td class="p-3">{{ $number($row['diniyyah_total']) }}</td><td class="p-3">{{ $number($row['tahfidz_total']) }}</td><td class="p-3 font-semibold">{{ $number($row['total']) }}</td><td class="p-3">{{ $number($row['average']) }}</td><td class="p-3">{{ $row['complete'] ? 'Lengkap' : 'Belum lengkap' }}</td></tr>
                        @empty<tr><td colspan="7" class="p-5 text-muted">{{ $class['snapshot_id'] ? 'Belum ada santri pada leger ini sesuai filter.' : 'Leger belum dibuat oleh wali kelas.' }}</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endforeach
        @else<p class="text-muted">Belum ada kelas pada periode yang dipilih.</p>@endif
    </div>
</x-layouts.portal>
