@php
    $totals = $workflowStatistics['total'];
    $columns = ['input' => 'Sudah diinput PJ', 'pending' => 'Menunggu approve wali kelas', 'approved' => 'Disetujui · siap unduh'];
    $statQuery = request()->except(['page', 'review_status']);
@endphp
<section class="space-y-4" aria-labelledby="tasmi-workflow-statistics-title" data-tasmi-workflow-statistics>
    <div>
        <h2 id="tasmi-workflow-statistics-title" class="ui-card-title text-heading">Statistik proses Tasmi'</h2>
        <p class="mt-1 text-theme-sm text-muted">Jumlah hasil Tasmi' 1 dan 5 juz; satu santri dapat memiliki beberapa hasil. Statistik mengikuti filter laporan selain status persetujuan.</p>
    </div>
    <div class="grid gap-3 lg:grid-cols-3">
        @foreach($columns as $key => $label)
            <article class="metric-card">
                <p>{{ $label }}</p><strong>{{ number_format($totals[$key], 0, ',', '.') }}</strong>
                <span>{{ $key === 'input' ? 'seluruh hasil yang tercatat' : ($key === 'pending' ? 'termasuk data lama yang belum diverifikasi' : 'tersedia dalam unduhan admin untuk piagam') }}</span>
            </article>
        @endforeach
    </div>
    <div class="ui-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-theme-sm">
                <caption class="sr-only">Statistik persetujuan Tasmi' menurut kelompok kelas</caption>
                <thead class="bg-surface-subtle text-muted"><tr><th scope="col" class="px-5 py-3">Kelompok kelas</th>@foreach($columns as $label)<th scope="col" class="px-5 py-3 text-right">{{ $label }}</th>@endforeach<th scope="col" class="px-5 py-3 text-right">Perlu perbaikan PJ</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach($workflowStatistics['groups'] as $gender => $group)
                        @if($gender !== 'other' || $group['input'] > 0)
                            <tr data-stat-gender="{{ $gender }}">
                                <th scope="row" class="px-5 py-4 font-medium text-heading">{{ $group['label'] }}</th>
                                @foreach(['input' => null, 'pending' => 'pending', 'approved' => 'approved', 'correction' => 'correction_requested'] as $key => $status)
                                    <td class="px-5 py-4 text-right" data-stat-status="{{ $key }}"><a href="{{ route('admin.tasmi-report.index', array_merge($statQuery, ['gender_group' => $gender, 'review_status' => $status])) }}" class="font-medium text-success-ink underline underline-offset-4" aria-label="{{ $group['label'] }}: {{ $key === 'correction' ? 'Perlu perbaikan PJ' : $columns[$key] }}">{{ number_format($group[$key], 0, ',', '.') }}</a></td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot class="border-t border-line bg-surface-subtle font-medium text-heading"><tr><th scope="row" class="px-5 py-3">Total</th>@foreach(['input', 'pending', 'approved', 'correction'] as $key)<td class="px-5 py-3 text-right">{{ number_format($totals[$key], 0, ',', '.') }}</td>@endforeach</tr></tfoot>
            </table>
        </div>
        <p class="border-t border-line px-5 py-3 text-theme-xs text-muted">Perlu perbaikan ditampilkan terpisah karena sedang menunggu tindakan PJ. Kelompok kelas mengikuti data kelas saat ujian.</p>
    </div>
</section>
