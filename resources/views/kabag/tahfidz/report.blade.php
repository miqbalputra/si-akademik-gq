<x-layouts.portal title="Nilai Tahfidz Rapor" portalLabel="Portal Kabag Tahfidz" breadcrumb="Nilai Tahfidz Rapor">
    <div class="space-y-6">
        <header>
            <h1 class="ui-page-title text-heading">Nilai Tahfidz Rapor</h1>
            <p class="mt-2 text-muted">Isi Sabaq, Sabqi, Manzil, dan nilai ujian. Wali kelas memeriksa nilai final sebelum memasukkannya ke leger dan rapor.</p>
        </header>

        @if (session('status'))
            <div class="inline-feedback inline-feedback-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="inline-feedback inline-feedback-error" role="alert">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($term)
            <section class="card-lg grid gap-4 p-5 lg:grid-cols-2" aria-label="Periode dan kelas">
                <form method="GET" action="{{ route('kabag-tahfidz.report.index') }}" class="flex flex-wrap items-end gap-2">
                    <label class="ui-field min-w-0 flex-1">
                        <span>Tahun ajaran dan semester</span>
                        <select class="form-input" name="academic_term_id" required>
                            @foreach ($terms as $option)
                                <option value="{{ $option->id }}" @selected($term->is($option))>{{ $option->academicYear?->name }} · {{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-outline" type="submit">Pilih semester</button>
                </form>
                <form method="GET" action="{{ route('kabag-tahfidz.report.index') }}" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="academic_term_id" value="{{ $term->id }}">
                    <label class="ui-field min-w-0 flex-1">
                        <span>Kelas</span>
                        <select class="form-input" name="classroom_term_id">
                            <option value="">Semua kelas</option>
                            @foreach ($classrooms as $option)
                                <option value="{{ $option->id }}" @selected($classroom?->is($option))>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">Tampilkan kelas</button>
                </form>
            </section>

            @if ($classrooms->isNotEmpty())
                <section class="card-lg grid gap-5 p-5 lg:grid-cols-2" aria-labelledby="excel-heading">
                    <div class="space-y-3">
                        <h2 id="excel-heading" class="ui-card-title text-heading">Isi lewat Excel</h2>
                        <p class="text-theme-sm text-muted">Isi kolom nilai dan juz yang diujikan. Kolom kosong mempertahankan nilai tersimpan. Isian lengkap dikirim untuk diperiksa, dan isian sebagian disimpan sebagai draf.</p>
                        <div class="flex flex-wrap gap-2">
                            @if ($classroom && !$locked)
                                <a class="btn btn-outline" href="{{ route('kabag-tahfidz.report.template', ['term' => $term, 'classroom_term_id' => $classroom->id]) }}">Unduh template {{ $classroom->name }}</a>
                            @endif
                            <a class="btn btn-outline" href="{{ route('kabag-tahfidz.report.template', $term) }}">Unduh template semua santri</a>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('kabag-tahfidz.report.import', $term) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <label class="ui-field">
                            <span>Template yang sudah diisi (.xlsx)</span>
                            <input type="file" name="file" accept=".xlsx" class="form-input" required>
                        </label>
                        <button class="btn btn-primary" type="submit">Unggah dan simpan nilai</button>
                    </form>
                </section>
            @else
                <p class="card-lg p-5 text-muted">Belum ada santri aktif terdaftar pada semester ini.</p>
            @endif
        @else
            <p class="card-lg p-5 text-muted">Belum ada semester akademik.</p>
        @endif

        @if ($classroom)
            @php
                $classFilters = ['academic_term_id' => $term->id, 'classroom_term_id' => $classroom->id];
                $fields = \App\Services\TahfidzReportWorkflow::FIELDS;
                $statusLabels = ['draft' => 'Draf', 'final' => 'Menunggu pemeriksaan', 'reviewed' => 'Sudah diperiksa', 'legacy_validated' => 'Nilai lama tervalidasi', 'missing' => 'Belum lengkap'];
            @endphp
            <section class="card-lg overflow-hidden" aria-labelledby="class-heading">
                <div class="space-y-2 border-b border-line p-5">
                    <h2 class="ui-card-title text-heading" id="class-heading">Daftar nilai · {{ $classroom->name }}</h2>
                    @if ($locked)
                        <p class="text-warning-ink">Leger atau rapor kelas ini sudah terkunci.</p>
                    @else
                        <p class="text-theme-sm text-muted">Pilih santri untuk mengisi atau memperbarui nilainya langsung.</p>
                    @endif
                </div>
                @if ($selectedEnrollment && !$locked)
                    <form class="space-y-4 border-b border-line bg-surface-subtle p-5" method="POST"
                        action="{{ route('kabag-tahfidz.report.save', $selectedEnrollment) }}" id="isi-nilai" aria-labelledby="isi-nilai-heading">
                        @csrf
                        @php $entry = $states[$selectedEnrollment->id]['entry']; @endphp
                        <input type="hidden" name="version" value="{{ old('version', $entry?->version ?? 0) }}">
                        <h3 class="ui-card-title text-heading" id="isi-nilai-heading">{{ $selectedEnrollment->student->name }} · NIS {{ $selectedEnrollment->student->nis ?? '—' }}</h3>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            @foreach ($fields as $field => $label)
                                <label class="ui-field">
                                    <span>{{ $label }}</span>
                                    <input class="form-input" name="{{ $field }}" value="{{ old($field, $values[$field]) }}"
                                        @if ($field === 'juz_tested') type="text" maxlength="255"
                                        @else type="number" min="0" max="100" step="0.01" inputmode="decimal" @endif>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button class="btn btn-outline" type="submit" name="action" value="draft">Simpan draf</button>
                            <button class="btn btn-primary" type="submit" name="action" value="final">Simpan final</button>
                            <a class="btn btn-outline" href="{{ route('kabag-tahfidz.report.index', $classFilters) }}">Tutup</a>
                        </div>
                    </form>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-theme-sm">
                        <thead class="bg-surface-subtle text-muted"><tr>
                            <th scope="col" class="p-3">Santri / NIS</th>
                            @foreach ($fields as $label)<th scope="col" class="p-3">{{ $label }}</th>@endforeach
                            <th scope="col" class="p-3">Status</th>
                            @unless ($locked)<th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>@endunless
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($enrollments as $enrollment)
                                @php $state = $states[$enrollment->id]; @endphp
                                <tr>
                                    <th scope="row" class="p-3 text-heading">{{ $enrollment->student->name }}<span class="block font-normal text-muted">{{ $enrollment->student->nis ?? '—' }}</span></th>
                                    @foreach ($state['values'] as $value)<td class="p-3">{{ $value ?? '—' }}</td>@endforeach
                                    <td class="p-3">{{ $statusLabels[$state['status']] }}</td>
                                    @unless ($locked)
                                        <td class="p-3"><a class="btn btn-outline" aria-label="Buka nilai {{ $enrollment->student->name }}"
                                            href="{{ route('kabag-tahfidz.report.index', $classFilters + ['enrollment_id' => $enrollment->id]).'#isi-nilai' }}">{{ $state['entry'] ? 'Ubah' : 'Isi' }}</a></td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.portal>
