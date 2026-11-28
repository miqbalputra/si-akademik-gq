<x-layouts.portal :title="'Alur Nilai & Rapor'" portalLabel="Portal Guru" :breadcrumb="'Rapor / Alur Nilai & Rapor'">
    @php
        $step = $details['step'] ?? ($classroom ? 2 : 1);
        $isHomeroom = $details['isHomeroom'] ?? false;
        $snapshot = $details['snapshot'] ?? null;
        $params = $classroom ? ['classroom_term_id' => $classroom->id] : [];
        $ledgerReady = $snapshot && ($details['inputsReady'] ?? false) && !$details['ledgerIssue'];
        $steps = [
            ['Pilih kelas', 'Kelas dan semester'],
            ['Lengkapi nilai', 'Diniyyah, Suluk, Tahfidz'],
            ['Tinjau leger', 'Wali kelas mengunci leger'],
            ['Lengkapi rapor', 'Wali kelas meninjau draf'],
            ['Final & cetak', 'Cetak F4 dan terbitkan'],
        ];
    @endphp

    <div class="space-y-6">
        <header>
            <h1 class="ui-page-title text-heading">Alur Nilai &amp; Rapor</h1>
            <p class="mt-2 text-muted">Pilih kelas untuk melihat pekerjaan Anda dan tahap berikutnya.</p>
        </header>

        <nav aria-label="Tahapan nilai sampai rapor" class="rounded-2xl border border-brand-line bg-brand-soft/50 p-4 sm:p-5">
            <ol class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($steps as $index => [$label, $description])
                    @php
                        $number = $index + 1;
                        $state = $number < $step ? 'done' : ($number === $step ? 'current' : 'pending');
                    @endphp
                    <li @if($state === 'current') aria-current="step" @endif
                        class="flex items-start gap-3 rounded-xl border p-3 {{ $state === 'current' ? 'border-brand-line bg-brand-soft text-brand-ink' : 'border-line bg-surface text-muted' }}">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-current text-theme-xs font-semibold" aria-hidden="true">{{ $state === 'done' ? '✓' : $number }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold {{ $state === 'current' ? '' : 'text-heading' }}">{{ $label }}</p>
                            <p class="mt-1 text-theme-xs">{{ $description }}</p>
                            <span class="sr-only">{{ $state === 'done' ? 'Selesai' : ($state === 'current' ? 'Tahap saat ini' : 'Tahap berikutnya') }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($errors->any())
            <div class="inline-feedback inline-feedback-error" role="alert">{{ $errors->first() }}</div>
        @endif
        <section class="card-lg p-5">
            @if ($classrooms->isNotEmpty())
                <form action="{{ route('guru.report-workflow') }}" method="GET" class="flex flex-wrap items-end gap-3">
                    <label class="ui-field min-w-0 w-full sm:w-auto sm:flex-1">
                        <span>Kelas · tahun ajaran · semester</span>
                        <select class="form-input" name="classroom_term_id" required>
                            <option value="">Pilih kelas</option>
                            @foreach ($classrooms as $option)
                                <option value="{{ $option->id }}" @selected($classroom?->id === $option->id)>{{ $option->name }} · {{ $option->academicTerm?->academicYear?->name }} · {{ $option->academicTerm?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">Lihat tahapan</button>
                </form>
            @else
                <p class="text-muted">Belum ada kelas dengan penugasan Diniyyah atau wali kelas yang aktif.</p>
            @endif
        </section>

        @if ($classroom)
            @if ($details['ownSubjects']->isNotEmpty())
                <section class="card-lg space-y-4 p-5" aria-labelledby="my-assessments">
                    <h2 id="my-assessments" class="ui-card-title text-heading">Penilaian yang Anda isi</h2>
                    <ul class="divide-y divide-line">
                        @foreach ($details['ownSubjects'] as $subject)
                            @forelse ($subject->assessmentSets as $set)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <a class="font-medium text-brand-ink hover:underline" href="{{ route('guru.diniyyah-scores.edit', $set) }}">{{ $subject->subject?->name ?? $set->title }}@if(filled($set->tested_material) && $set->tested_material !== '-') · {{ $set->tested_material }}@endif</a>
                                    <span class="text-theme-sm {{ $set->missingExamName() ? 'font-medium text-warning-ink' : 'text-muted' }}">{{ $set->missingExamName() ? 'Nama ujian belum diisi' : \App\Support\UiLabel::statusLabel($set->status) }}</span>
                                </li>
                            @empty
                                <li class="py-2 text-muted">{{ $subject->subject?->name }} · Set penilaian belum dibuat</li>
                            @endforelse
                        @endforeach
                    </ul>
                    <p class="text-theme-sm text-muted">Simpan nilai sebagai draf. Setelah lengkap, ajukan ke Kabag Diniyyah untuk validasi.</p>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="{{ route('guru.diniyyah-scores.index', $params) }}">Buka input nilai kelas</a>
                        <a class="btn btn-outline" href="{{ route('guru.kkm.index') }}">Lihat KKM</a>
                    </div>
                </section>
            @endif

            @if ($isHomeroom && !$details['frozen'])
                <section class="card-lg space-y-4 p-5" aria-labelledby="class-readiness">
                    <h2 id="class-readiness" class="ui-card-title text-heading">{{ $details['inputsReady'] ? 'Tinjau leger kelas' : 'Lengkapi sumber nilai kelas' }}</h2>
                    @if (!$details['inputsReady'])
                        <ul class="list-disc space-y-1 pl-5 text-theme-sm text-body">
                            @if ($details['enrollments']->isEmpty())<li>Belum ada santri aktif. Hubungi admin untuk memeriksa daftar kelas.</li>@endif
                            @if ($details['withoutSet']->isNotEmpty())<li>Set penilaian belum dibuat untuk {{ $details['withoutSet']->join(', ') }}.</li>@endif
                            @if ($details['sets']->isEmpty() && $details['withoutSet']->isEmpty())<li>Belum ada penilaian yang masuk leger.</li>@endif
                            @foreach ($details['pendingSets'] as $set)
                                <li>{{ $set->title }}@if(filled($set->tested_material) && $set->tested_material !== '-' && ! $set->missingExamName()) · {{ $set->tested_material }}@endif — {{ $set->missingExamName() ? 'nama ujian belum diisi oleh guru pengampu' : ($set->status === 'validated' ? 'periksa kelengkapan nilai atau KKM' : \App\Support\UiLabel::statusLabel($set->status)) }}</li>
                            @endforeach
                            @if ($details['pendingTahfidz'] > 0)<li>{{ $details['pendingTahfidz'] }} santri masih memerlukan kelengkapan atau pemeriksaan Tahfidz.</li>@endif
                        </ul>
                    @endif
                    <p class="text-theme-sm text-muted">Periksa rekap presensi kelas. Draf dapat dibuat kapan saja. Leger harus lengkap 100% sebelum lanjut ke rapor.</p>
                    @if ($details['ledgerReadiness'])<p class="text-theme-sm font-medium text-heading">Kelengkapan nilai santri {{ $details['ledgerReadiness']['percentage'] }}%</p>@endif
                    @if ($details['ledgerIssue'])<p class="text-theme-sm text-warning-ink" role="status">{{ $details['ledgerIssue'] }}</p>@endif
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-outline" href="{{ route('guru.tahfidz-report.monitor', $classroom) }}">Status &amp; pemeriksaan Tahfidz</a>
                        <a class="btn btn-outline" href="{{ route('attendance.edit', $classroom) }}">Periksa presensi</a>
                        @if ($snapshot)<a class="btn {{ $ledgerReady ? 'btn-primary' : 'btn-outline' }}" href="{{ route('diniyyah.ledger.show', $snapshot) }}">Tinjau draf leger</a>@endif
                        <form method="POST" action="{{ route('diniyyah.ledger.generate', $classroom) }}">
                            @csrf
                            <button class="btn {{ $ledgerReady ? 'btn-outline' : 'btn-primary' }}" type="submit">{{ $snapshot ? 'Segarkan draf leger' : 'Buat draf leger' }}</button>
                        </form>
                    </div>
                </section>
            @elseif ($isHomeroom)
                <section class="card-lg space-y-4 p-5" aria-labelledby="report-readiness">
                    <h2 id="report-readiness" class="ui-card-title text-heading">{{ $step === 4 ? 'Lengkapi draf rapor' : 'Cetak dan penerbitan rapor' }}</h2>
                    @if ($details['missingReports'])<p class="text-warning-ink">{{ $details['missingReports'] }} rapor masih perlu dibuat dari leger final.</p>@endif
                    @if ($details['incompleteReports'])<p class="text-warning-ink">{{ $details['incompleteReports'] }} rapor F4 masih memiliki pelengkap yang perlu diperiksa.</p>@endif
                    @if ($details['archiveReports'])<p class="text-muted">{{ $details['archiveReports'] }} rapor arsip dapat dibuka satu per satu dari daftar kelas.</p>@endif
                    @if ($step === 5)
                        <p class="text-muted">Wali kelas dapat mencetak rapor dari daftar kelas. {{ $details['unpublished'] ? 'Admin atau Kabag Diniyyah mengunci dan menerbitkan rapor melalui menu Rapor Diniyyah.' : 'Rapor yang terbit dapat dilihat wali santri.' }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="{{ route('report-cards.classroom', $snapshot) }}">Buka rapor kelas</a>
                        <a class="btn btn-outline" href="{{ route('diniyyah.ledger.show', $snapshot) }}">Lihat leger final</a>
                        <a class="btn btn-outline" href="{{ route('guru.tahfidz-report.monitor', $classroom) }}">Status Tahfidz</a>
                    </div>
                </section>
            @else
                <p class="rounded-xl border border-line bg-surface p-4 text-theme-sm text-muted">Setelah Kabag Diniyyah memvalidasi nilai, wali kelas melanjutkan ke leger dan rapor.</p>
            @endif
        @endif
    </div>
</x-layouts.portal>
