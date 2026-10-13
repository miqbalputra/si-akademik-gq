@props(['options', 'routePrefix'])

@once
    <style>
        .class-attendance-download { display:grid; gap:1rem; min-width:0; color:var(--ui-text); font-family:var(--font-sans); }
        .cad-filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(11rem,1fr)); gap:1rem; }
        .cad-field { min-width:0; }
        .cad-field label { display:block; margin-bottom:.4rem; font-size:14px; line-height:20px; font-weight:500; }
        .cad-field input,.cad-field select { width:100%; min-width:0; min-height:44px; border:1px solid var(--ui-line-strong); border-radius:.75rem; background:var(--ui-surface); color:var(--ui-text); padding:.65rem .75rem; font-family:inherit; font-size:14px; line-height:20px; }
        .cad-field select { appearance:none; background-image:url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20"%3E%3Cpath stroke="%2394a3b8" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4"/%3E%3C/svg%3E'); background-position:right .75rem center; background-repeat:no-repeat; background-size:18px; padding-right:2.5rem; }
        .dark .cad-field input { color-scheme:dark; }
        .cad-actions { display:flex; flex-wrap:wrap; gap:.75rem; }
        .cad-button { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; min-height:44px; border:1px solid var(--ui-line-strong); border-radius:.75rem; background:var(--ui-surface); color:var(--ui-text); padding:.65rem 1rem; font-size:14px; line-height:20px; font-weight:500; cursor:pointer; }
        .cad-button:hover { background:var(--ui-surface-subtle); }
        .cad-button-primary { background:var(--color-brand-600); border-color:var(--color-brand-600); color:#fff; }
        .cad-button-primary:hover { background:var(--color-brand-700); }
        .cad-button:disabled { opacity:.5; cursor:not-allowed; }
        .cad-preview { max-height:18rem; overflow:auto; border:1px solid var(--ui-line); border-radius:.75rem; background:var(--ui-surface-subtle); padding:1rem; font-size:14px; line-height:20px; }
        .cad-class + .cad-class { margin-top:1rem; border-top:1px solid var(--ui-line); padding-top:1rem; }
        .cad-class strong { color:var(--ui-heading); font-weight:500; }
        .cad-month { margin-top:.35rem; color:var(--ui-muted); font-size:12px; line-height:18px; }
        .cad-caption { margin:0; color:var(--ui-muted); font-size:12px; line-height:18px; }
        .cad-error { border:1px solid var(--ui-danger-line); border-radius:.75rem; background:var(--ui-danger-soft); color:var(--ui-danger-ink); padding:.75rem 1rem; font-size:14px; line-height:20px; }
        @media(max-width:479px) { .cad-filters { grid-template-columns:1fr; } .cad-actions .cad-button { width:100%; } }
    </style>
    <script>
        window.gqClassAttendanceReport = function(config) {
            return {
                terms:config.terms, classes:config.classes, filters:{...config.initial},
                preview:null, loading:false, error:'', sequence:0,
                get selectedTerm() { return this.terms.find(term => term.id === this.filters.academic_term_id); },
                get availableClasses() { return this.classes.filter(item => item.term_id === this.filters.academic_term_id); },
                init() { this.refreshPreview(); },
                changeTerm() {
                    this.filters.classroom_term_id = config.admin ? 'all' : (this.availableClasses[0]?.id || '');
                    const term = this.selectedTerm;
                    if (term?.start && this.filters.month < term.start.slice(0,7)) this.filters.month = term.start.slice(0,7);
                    if (term?.end && this.filters.month > term.end.slice(0,7)) this.filters.month = (term.start || term.end).slice(0,7);
                    this.filters.start_date = term?.start || this.filters.month + '-01';
                    this.filters.end_date = term?.end || this.filters.start_date;
                    this.refreshPreview();
                },
                async refreshPreview() {
                    const sequence = ++this.sequence;
                    this.preview = null;
                    this.loading = false;
                    this.error = '';
                    if (!this.filters.academic_term_id || !this.filters.classroom_term_id) return;
                    const params = new URLSearchParams({academic_term_id:this.filters.academic_term_id, classroom_term_id:this.filters.classroom_term_id, period_mode:this.filters.period_mode});
                    if (this.filters.period_mode === 'month') {
                        if (!this.filters.month) { this.error = 'Pilih bulan laporan.'; return; }
                        params.set('month', this.filters.month);
                    } else {
                        if (!this.filters.start_date || !this.filters.end_date) { this.error = 'Lengkapi tanggal awal dan akhir.'; return; }
                        params.set('start_date', this.filters.start_date);
                        params.set('end_date', this.filters.end_date);
                    }
                    this.loading = true;
                    try {
                        const response = await fetch(config.previewUrl + '?' + params.toString(), {headers:{Accept:'application/json'}, cache:'no-store'});
                        const payload = await response.json();
                        if (sequence !== this.sequence) return;
                        if (!response.ok) {
                            this.error = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Ringkasan laporan tidak dapat dimuat.';
                            return;
                        }
                        this.preview = payload;
                    } catch (_) {
                        if (sequence === this.sequence) this.error = 'Ringkasan laporan tidak dapat dimuat. Periksa koneksi lalu coba lagi.';
                    } finally {
                        if (sequence === this.sequence) this.loading = false;
                    }
                }
            };
        };
    </script>
@endonce

@if(empty($options['terms']))
    <p class="cad-caption">Belum ada kelas/periode yang dapat dilaporkan.</p>
@else
    @php
        $config = array_merge($options, ['previewUrl' => route($routePrefix.'.preview')]);
        $fieldId = str_replace('.', '-', $routePrefix);
    @endphp
    <form method="GET" action="{{ route($routePrefix.'.export', 'xlsx') }}" class="class-attendance-download" x-data="gqClassAttendanceReport(@js($config))" data-class-attendance-download>
        <div class="cad-filters">
            <div class="cad-field">
                <label for="{{ $fieldId }}-term">Semester</label>
                <select id="{{ $fieldId }}-term" name="academic_term_id" x-model="filters.academic_term_id" @change="changeTerm()" required>
                    @foreach($options['terms'] as $term)
                        <option value="{{ $term['id'] }}">{{ $term['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cad-field">
                <label for="{{ $fieldId }}-class">Kelas</label>
                <select id="{{ $fieldId }}-class" name="classroom_term_id" x-model="filters.classroom_term_id" @change="refreshPreview()" required>
                    @if($options['admin'])<option value="all">Semua kelas</option>@endif
                    <template x-for="item in availableClasses" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                </select>
            </div>
            <div class="cad-field">
                <label for="{{ $fieldId }}-mode">Periode laporan</label>
                <select id="{{ $fieldId }}-mode" name="period_mode" x-model="filters.period_mode" @change="refreshPreview()">
                    <option value="month">Bulanan</option><option value="range">Rentang tanggal / satu hari</option>
                </select>
            </div>
            <div class="cad-field" x-show="filters.period_mode === 'month'">
                <label for="{{ $fieldId }}-month">Bulan</label>
                <input id="{{ $fieldId }}-month" type="month" name="month" x-model="filters.month" @change="refreshPreview()" :min="selectedTerm?.start?.slice(0,7) || null" :max="selectedTerm?.end?.slice(0,7) || null" :disabled="filters.period_mode !== 'month'" :required="filters.period_mode === 'month'">
            </div>
            <div class="cad-field" x-show="filters.period_mode === 'range'" x-cloak>
                <label for="{{ $fieldId }}-start">Tanggal awal</label>
                <input id="{{ $fieldId }}-start" type="date" name="start_date" x-model="filters.start_date" @change="refreshPreview()" :min="selectedTerm?.start || null" :max="selectedTerm?.end || null" :disabled="filters.period_mode !== 'range'" :required="filters.period_mode === 'range'">
            </div>
            <div class="cad-field" x-show="filters.period_mode === 'range'" x-cloak>
                <label for="{{ $fieldId }}-end">Tanggal akhir</label>
                <input id="{{ $fieldId }}-end" type="date" name="end_date" x-model="filters.end_date" @change="refreshPreview()" :min="filters.start_date || selectedTerm?.start || null" :max="selectedTerm?.end || null" :disabled="filters.period_mode !== 'range'" :required="filters.period_mode === 'range'">
            </div>
        </div>
        <p class="cad-caption">Untuk satu tanggal, isi tanggal awal dan akhir dengan tanggal yang sama.</p>
        @if($errors->any())<p class="cad-error" role="alert">{{ $errors->first() }}</p>@endif
        <p class="cad-caption" role="status" x-show="loading" x-cloak>Memuat ringkasan periode…</p>
        <p class="cad-error" role="alert" x-show="error" x-text="error" x-cloak></p>
        <section class="cad-preview" aria-label="Ringkasan periode laporan" aria-live="polite" x-show="preview" x-cloak>
            <p style="margin:0 0 .75rem;font-weight:500;" x-text="preview?.period?.label"></p>
            <template x-for="(item, index) in preview?.classes || []" :key="index">
                <div class="cad-class">
                    <strong x-text="item.name"></strong>
                    <span x-text="' · ' + item.students + ' santri' "></span>
                    <p style="margin:.35rem 0;" x-text="'Hari efektif dalam pilihan: ' + item.effective_days + ' · Sudah berlangsung: ' + item.elapsed_days"></p>
                    <template x-for="month in item.months" :key="month.label"><p class="cad-month" x-text="month.label + ': ' + month.full_effective_days + ' hari efektif sebulan; ' + month.selected_effective_days + ' dalam pilihan; ' + month.elapsed_effective_days + ' sudah berlangsung.'"></p></template>
                </div>
            </template>
        </section>
        <div class="cad-actions">
            <button type="submit" class="cad-button cad-button-primary" :disabled="loading || !preview">Download Excel</button>
            <button type="submit" class="cad-button" formaction="{{ route($routePrefix.'.export', 'pdf') }}" :disabled="loading || !preview">Download PDF</button>
            <button type="button" class="cad-button" @click="refreshPreview()" :disabled="loading">Muat ulang ringkasan</button>
        </div>
        <p class="cad-caption">H* dihitung sebagai Hadir bawaan karena belum ada catatan tersimpan. Persentase memakai hari yang sudah berlangsung dan tidak menghitung libur khusus (L) atau tanggal tanpa catatan setelah santri keluar.</p>
        <noscript><p class="cad-error">Aktifkan JavaScript untuk memilih kelas dan melihat ringkasan sebelum mengunduh laporan.</p></noscript>
    </form>
@endif
