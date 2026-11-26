@php
    $isHomeroom = app(\App\Services\DiniyyahLedgerAccess::class)->allows(auth()->user(), $reportCard->classroom_term_id);
    $isManagement = auth()->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']);
    $portalLabel = $isHomeroom ? 'Portal Guru' : ($isManagement ? 'Portal Manajemen' : 'Portal Wali Santri');
@endphp
<x-layouts.portal title="Rapor {{ $printData['student_name'] }}" :portalLabel="$portalLabel" breadcrumb="Rapor / Tinjau">
    <div class="space-y-5">
        <header><h1 class="ui-page-title text-heading">{{ $printData['student_name'] }}</h1><p class="mt-2 text-muted">{{ $printData['student_nis'] }} · {{ $printData['classroom_name'] }} · {{ $printData['academic_year'] }} · {{ $printData['semester'] }}</p></header>
        @if(session('status'))<div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>@endif
        <div class="flex flex-wrap gap-2">
            <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route('report-cards.print', $reportCard) }}">{{ $missing ? 'Pratinjau F4' : 'Cetak F4' }}</a>
            @if(!$missing)<a class="btn btn-outline" href="{{ route('report-cards.download-pdf', $reportCard) }}">Unduh PDF F4</a>@endif
            @if(!$isHomeroom && !$isManagement)<a class="btn btn-outline" href="{{ route('wali.dashboard') }}">Dashboard</a>
            @elseif(!empty($printData['ledger_snapshot_id']))<a class="btn btn-outline" href="{{ route('report-cards.classroom', $printData['ledger_snapshot_id']) }}">Kembali ke rapor kelas</a>@endif
        </div>
        <div class="{{ $missing ? 'inline-feedback inline-feedback-error' : 'inline-feedback inline-feedback-success' }}" role="status">
            {{ $missing ? 'Belum siap cetak: '.implode(', ', $missing) : 'Siap cetak F4 / Folio 216 × 330 mm.' }}
        </div>
        @can('configurePrint', $reportCard)
        <form class="card-lg p-5 space-y-5" method="POST" action="{{ route('report-cards.print-options', $reportCard) }}">
            @csrf @method('PUT')
            <h2 class="ui-card-title">Pelengkap rapor</h2>
            <p class="text-muted">Simpan draf kapan saja. Lengkapi isian wajib sebelum rapor dikunci.</p>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="ui-field"><span>Tanggal rapor (ditentukan admin)</span><p>{{ $printData['options']['issue_date'] ?? 'Admin belum menetapkan tanggal rapor periode ini.' }}</p></div>
                <div class="ui-field"><span>Nama kepala sekolah (tetap)</span><p>{{ $printData['options']['principal_name'] }}</p><p class="text-theme-xs text-muted">Tanda tangan tercantum otomatis pada cetakan rapor.</p></div>
                @foreach(['issue_place'=>'Tempat penerbitan','homeroom_name'=>'Nama wali kelas','principal_title'=>'Jabatan kepala'] as $key=>$label)
                <label class="ui-field"><span>{{ $label }} · Wajib</span><input type="text" name="{{ $key }}" value="{{ old($key, $printData['options'][$key]) }}" class="form-input" maxlength="{{ $key === 'issue_place' ? 80 : 100 }}"></label>
                @endforeach
                @if($printData['semester'] === 'genap')
                <label class="ui-field"><span>Keputusan kenaikan kelas · Wajib</span><select name="promotion_status" class="form-input"><option value="">Belum diputuskan</option>@foreach(['promoted'=>'Naik kelas','retained'=>'Tinggal kelas','graduated'=>'Lulus'] as $value=>$label)<option value="{{ $value }}" @selected(old('promotion_status',$printData['options']['promotion_status']) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="ui-field"><span>Kelas tujuan (jika naik kelas)</span><input name="next_class" maxlength="60" class="form-input" value="{{ old('next_class', $printData['options']['next_class']) }}"></label>
                @endif
            </div>
            <label class="ui-field"><span>Catatan wali kelas (kosongkan untuk catatan otomatis sesuai template)</span><textarea name="note" maxlength="350" rows="3" class="form-input">{{ old('note', $printData['options']['note']) }}</textarea></label>
            <button class="btn btn-primary">Simpan draf rapor</button>
        </form>
        @endcan
        <section class="card-lg p-5 overflow-x-auto"><h2 class="ui-card-title mb-4">Nilai dari leger final</h2>
            <table class="w-full text-left"><thead><tr><th class="p-2">Mata pelajaran</th><th class="p-2">KKM</th><th class="p-2">Nilai</th><th class="p-2">Rata-rata kelas</th></tr></thead><tbody>
            @foreach(array_merge($printData['subjects'], $printData['practice']) as $line)<tr class="border-t border-line"><td class="p-2">{{ $line['name'] }} {{ $line['material'] ? '— '.$line['material'] : '' }}</td><td class="p-2">{{ $line['kkm'] }}</td><td class="p-2">{{ $line['score'] }}</td><td class="p-2">{{ $line['class_average'] }}</td></tr>@endforeach
            </tbody></table>
            <p class="mt-4 font-semibold">Jumlah nilai: {{ $printData['total'] }} · Peringkat: {{ (int)$printData['rank'] }}</p>
        </section>
    </div>
</x-layouts.portal>
