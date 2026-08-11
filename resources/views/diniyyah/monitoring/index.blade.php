<x-layouts.portal title="Monitoring Input Diniyyah" portalLabel="Portal Manajemen" breadcrumb="Monitoring Diniyyah">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber">Monitoring &amp; Validasi</span>
                <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">Monitoring Input Nilai Diniyyah</h1>
                <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">Lacak keaktifan guru, kelas, mapel, dan lakukan verifikasi berkas nilai.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="inline-feedback inline-feedback-success" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="inline-feedback inline-feedback-error" role="alert">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @php
            $totalSets = $summaries->count();
            $completeSets = $summaries->filter(fn ($summary) => $summary['progress_percentage'] >= 100)->count();
            $incompleteStudents = $summaries->sum('incomplete_students');
            $submittedSets = $summaries->where('status', 'submitted')->count();
        @endphp

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Ringkasan monitoring">
            <div class="metric-card"><p class="metric-label">Set ditampilkan</p><p class="metric-value">{{ $totalSets }}</p></div>
            <div class="metric-card border-emerald-200 bg-emerald-50"><p class="metric-label text-emerald-700">Set lengkap</p><p class="metric-value text-emerald-900">{{ $completeSets }}</p></div>
            <div class="metric-card border-rose-200 bg-rose-50"><p class="metric-label text-rose-700">Santri kurang nilai</p><p class="metric-value text-rose-900">{{ $incompleteStudents }}</p></div>
            <div class="metric-card border-amber-200 bg-amber-50"><p class="metric-label text-amber-700">Menunggu validasi</p><p class="metric-value text-amber-900">{{ $submittedSets }}</p></div>
        </section>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="monitoring-filter-heading">
            <div class="mb-4">
                <p class="text-xs font-black uppercase tracking-[.14em] text-amber-700">Filter data</p>
                <h2 id="monitoring-filter-heading" class="mt-1 text-lg font-black text-slate-900">Temukan set penilaian</h2>
            </div>
            <form method="GET" class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="monitoring-classroom" class="mb-1.5 block text-xs font-bold text-slate-600">Kelas</label>
                    <select id="monitoring-classroom" name="classroom" class="form-input min-h-11">
                        <option value="">Semua kelas</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom }}" @selected(request('classroom') === $classroom)>{{ $classroom }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="monitoring-subject" class="mb-1.5 block text-xs font-bold text-slate-600">Mapel</label>
                    <select id="monitoring-subject" name="subject" class="form-input min-h-11">
                        <option value="">Semua mapel</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject }}" @selected(request('subject') === $subject)>{{ $subject }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="monitoring-status" class="mb-1.5 block text-xs font-bold text-slate-600">Status</label>
                    <select id="monitoring-status" name="status" class="form-input min-h-11">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\UiLabel::statusLabel($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <label for="monitoring-attention" class="flex min-h-11 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4">
                    <input id="monitoring-attention" type="checkbox" name="needs_attention" value="1" @checked(request()->boolean('needs_attention')) class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span class="text-xs font-bold text-slate-600">Perlu perhatian</span>
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary min-h-11 flex-1">Filter</button>
                    <a href="{{ route('diniyyah.monitoring.index') }}" class="btn btn-outline min-h-11 flex-1">Reset</a>
                </div>
            </form>
        </section>

        <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-label="Daftar set penilaian">
            @forelse ($summaries as $summary)
                @php
                    $assessmentSet = $summary['assessment_set'];
                    $progress = $summary['progress_percentage'];
                    $isComplete = $progress >= 100;
                @endphp
                <article class="action-card flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-black text-slate-900">{{ $assessmentSet->title }}</h2>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $summary['classroom_name'] }} &middot; {{ $summary['subject_name'] }}</p>
                                <p class="mt-1 truncate text-[11px] font-bold text-slate-400">{{ implode(', ', $summary['teacher_names']) ?: 'Guru belum ditentukan' }}</p>
                            </div>
                            <span class="status-badge status-badge-{{ \App\Support\UiLabel::statusColor($summary['status']) }} shrink-0">{{ \App\Support\UiLabel::statusLabel($summary['status']) }}</span>
                        </div>

                        <div class="mt-5" aria-label="Progress {{ $progress }} persen">
                            <div class="mb-1 flex justify-between text-xs font-bold text-slate-500">
                                <span>{{ $summary['complete_students'] }} lengkap</span><span>{{ $progress }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $isComplete ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="metric-label">Santri</dt><dd class="mt-1 text-sm font-black text-slate-800">{{ $summary['total_students'] }}</dd></div>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-2"><dt class="metric-label text-emerald-600">Lengkap</dt><dd class="mt-1 text-sm font-black text-emerald-700">{{ $summary['complete_students'] }}</dd></div>
                            <div class="rounded-xl border border-rose-100 bg-rose-50 p-2"><dt class="metric-label text-rose-600">Kurang</dt><dd class="mt-1 text-sm font-black text-rose-700">{{ $summary['incomplete_students'] }}</dd></div>
                        </dl>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('guru.diniyyah-scores.edit', $assessmentSet) }}" class="btn btn-outline min-h-11">Cek Nilai</a>
                            @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']))
                                <a href="{{ url('/admin/diniyyah-assessment-sets/'.$assessmentSet->id.'/edit') }}" class="btn btn-secondary min-h-11">Edit Set</a>
                            @endif
                        </div>

                        @if (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) && $summary['status'] === 'submitted')
                            <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                <form method="POST" action="{{ route('diniyyah.assessment-sets.approve', $assessmentSet) }}" class="space-y-2">
                                    @csrf
                                    <label class="sr-only" for="approve-notes-{{ $assessmentSet->id }}">Catatan persetujuan</label>
                                    <textarea id="approve-notes-{{ $assessmentSet->id }}" name="notes" rows="2" class="form-input" placeholder="Catatan persetujuan..."></textarea>
                                    <button type="submit" class="btn min-h-11 w-full bg-emerald-600 text-white hover:bg-emerald-700">Validasi</button>
                                </form>
                                <form method="POST" action="{{ route('diniyyah.assessment-sets.revision', $assessmentSet) }}" class="space-y-2">
                                    @csrf
                                    <label class="sr-only" for="revision-notes-{{ $assessmentSet->id }}">Catatan perbaikan</label>
                                    <textarea id="revision-notes-{{ $assessmentSet->id }}" name="notes" rows="2" class="form-input" placeholder="Catatan perbaikan..."></textarea>
                                    <button type="submit" class="btn min-h-11 w-full border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100">Kembalikan untuk revisi</button>
                                </form>
                            </div>
                        @elseif (auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah']) && $summary['status'] === 'validated')
                            <form method="POST" action="{{ route('diniyyah.assessment-sets.revision', $assessmentSet) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                                @csrf
                                <label class="sr-only" for="reopen-notes-{{ $assessmentSet->id }}">Alasan pembukaan revisi</label>
                                <textarea id="reopen-notes-{{ $assessmentSet->id }}" name="notes" rows="2" class="form-input" placeholder="Alasan pembukaan revisi..."></textarea>
                                <button type="submit" class="btn min-h-11 w-full border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100">Buka ulang untuk revisi</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state md:col-span-2 xl:col-span-3">
                    <p class="text-sm font-bold text-slate-600">Belum ada set penilaian yang sesuai filter.</p>
                    <p>Ubah filter atau periksa kembali periode input nilai.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.portal>
