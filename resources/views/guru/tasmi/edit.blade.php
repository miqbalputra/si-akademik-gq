<x-layouts.portal title="Edit Tasmi'" portalLabel="Portal Guru" breadcrumb="Edit Tasmi'">
    <header class="fade-up" style="margin-bottom:24px;">
        <a href="{{ route('guru.tasmi.records') }}" style="font-size:12px;font-weight:700;color:var(--ui-brand-ink);display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;">
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Riwayat Tasmi'
        </a>
        <h1 style="font-size:24px;font-weight:700;color:var(--ui-heading);margin:0 0 4px;letter-spacing:-.02em;">Edit Data Tasmi'</h1>
        <p style="font-size:14px;color:var(--ui-muted);font-weight:500;margin:0;">
            Santri: <strong>{{ $record->student?->name }}</strong> · {{ $record->classroomTerm?->classroom?->name ?? '-' }} · {{ $record->exam_date?->locale('id')->translatedFormat('d M Y') }}
        </p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:var(--ui-success-soft);border:1px solid var(--ui-success-line);border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;color:var(--ui-success-ink);" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div style="margin-bottom:18px;background:var(--ui-danger-soft);border:1px solid var(--ui-danger-line);border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:var(--ui-danger-ink);" class="fade-up">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Audit reminder --}}
    <div style="margin-bottom:18px;background:var(--ui-warning-soft);border:1px solid var(--ui-warning-line);border-radius:12px;padding:12px 16px;font-size:12px;color:var(--ui-warning-ink);font-weight:600;display:flex;align-items:center;gap:8px;" class="fade-up">
        <svg style="width:16px;height:16px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
        Setiap perubahan dicatat di audit log (score_change_logs + activity_log).
    </div>

    <form method="POST" action="{{ route('guru.tasmi.update', $record) }}" class="fade-up delay-1">
        @csrf
        @method('PUT')

        <div class="card" style="padding:24px;margin-bottom:18px;">
            {{-- Hari & Tanggal --}}
            @php($selectedExamDay = old('exam_day_label', $record->exam_day_label))
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 180px),1fr));gap:14px;margin-bottom:20px;">
                <div>
                    <label for="exam_day_label" class="form-label">Hari</label>
                    <select id="exam_day_label" name="exam_day_label" class="form-input">
                        <option value="">— Pilih hari —</option>
                        @if(filled($selectedExamDay) && !array_key_exists($selectedExamDay, $dayOptions))
                            <option value="{{ $selectedExamDay }}" selected>{{ $selectedExamDay }} (data lama)</option>
                        @endif
                        @foreach($dayOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedExamDay === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="exam_date" class="form-label">Tanggal (Masehi) *</label>
                    <input id="exam_date" name="exam_date" type="date" class="form-input" required value="{{ old('exam_date', $record->exam_date?->toDateString()) }}" onchange="tasmiAutofillHijriDate()">
                </div>
                <div>
                    <label for="hijri_date" class="form-label">Tanggal (Hijriyah)</label>
                    <input id="hijri_date" name="hijri_date" type="text" class="form-input" readonly data-hijri-auto maxlength="50" value="{{ old('hijri_date', $record->hijri_date) }}">
                    <p class="form-hint">Terisi otomatis berdasarkan kalender Hijriyah dan zona waktu WIB.</p>
                </div>
            </div>

            {{-- Jenis Ujian & Juz --}}
            <div style="margin-bottom:20px;">
                <label class="form-label">Jenis Ujian Tasmi'</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                    @foreach($examTypeOptions as $value => $label)
                        <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid var(--ui-line);border-radius:10px;cursor:pointer;transition:all .2s;">
                            <input type="radio" name="exam_type" value="{{ $value }}" required @if(old('exam_type', $record->exam_type) === $value) checked @endif onchange="tasmiToggleJuzFields()">
                            <span style="font-size:14px;font-weight:700;color:var(--ui-heading);">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div id="juz-one-block" style="display:none;margin-bottom:12px;">
                    <label for="juz_single" class="form-label">Juz yang Diuji (1 - 30)</label>
                    <select id="juz_single" class="form-input" onchange="document.getElementById('juz_start').value=this.value; document.getElementById('juz_end').value=this.value;">
                        <option value="">— Pilih juz —</option>
                        @for($i = 1; $i <= 30; $i++)
                            @if(old('exam_type', $record->exam_type) === '1_juz' && (int)old('juz_start', $record->juz_start) === $i)
                                <option value="{{ $i }}" selected>Juz {{ $i }}</option>
                            @else
                                <option value="{{ $i }}">Juz {{ $i }}</option>
                            @endif
                        @endfor
                    </select>
                </div>

                <div id="juz-five-block" style="display:none;margin-bottom:12px;">
                    <p class="form-label" style="margin-bottom:8px;">Rentang 5 Juz</p>
                    <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;">
                        <select id="juz_start" name="juz_start" class="form-input" onchange="tasmiUpdateFiveEnd()">
                            <option value="">Dari juz</option>
                            @for($i = 1; $i <= 26; $i++)
                                <option value="{{ $i }}" @if((int)old('juz_start', $record->juz_start) === $i) selected @endif>Juz {{ $i }}</option>
                            @endfor
                        </select>
                        <span style="font-weight:700;color:var(--ui-text);">→</span>
                        <select id="juz_end" name="juz_end" class="form-input">
                            <option value="">Sampai juz</option>
                            @for($i = 5; $i <= 30; $i++)
                                <option value="{{ $i }}" @if((int)old('juz_end', $record->juz_end) === $i) selected @endif>Juz {{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>

            {{-- Predikat --}}
            <div style="margin-bottom:20px;">
                <label for="predicate" class="form-label">Predikat *</label>
                <select id="predicate" name="predicate" class="form-input" required>
                    <option value="">— Pilih predikat —</option>
                    @foreach($predicateOptions as $value => $label)
                        <option value="{{ $value }}" @if(old('predicate', $record->predicate) === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Catatan --}}
            <div style="margin-bottom:8px;">
                <label for="notes" class="form-label">Catatan</label>
                <textarea id="notes" name="notes" class="form-input" rows="3" maxlength="1000">{{ old('notes', $record->notes) }}</textarea>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                Simpan Perubahan
            </button>
            <a href="{{ route('guru.tasmi.records') }}" class="btn btn-outline">Batal</a>

            <button type="submit" form="tasmi-delete-form" class="btn btn-danger" style="margin-left:auto;" onclick="return confirm('Hapus record tasmi\' ini? Tindakan ini tercatat di audit log (soft delete).');">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .342c.361.027.722.062 1.082.097m-1.082-.097L5.34 5.79a2.25 2.25 0 0 1 2.15-1.967L16.5 3.75a2.25 2.25 0 0 1 2.15 1.967L20.228 5.79" /></svg>
                Hapus
            </button>
        </div>
    </form>

    <form id="tasmi-delete-form" method="POST" action="{{ route('guru.tasmi.destroy', $record) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    @push('scripts')
    <script>
        function tasmiToggleJuzFields() {
            var checked = document.querySelector('input[name="exam_type"]:checked');
            var type = checked ? checked.value : null;
            var oneBlock = document.getElementById('juz-one-block');
            var fiveBlock = document.getElementById('juz-five-block');
            if (oneBlock) oneBlock.style.display = (type === '1_juz') ? 'block' : 'none';
            if (fiveBlock) fiveBlock.style.display = (type === '5_juz') ? 'block' : 'none';
        }
        function tasmiUpdateFiveEnd() {
            var start = document.getElementById('juz_start');
            var end = document.getElementById('juz_end');
            if (!start || !end) return;
            var s = parseInt(start.value, 10);
            if (!s) return;
            var computed = s + 4;
            if (computed > 30) computed = 30;
            end.value = String(computed);
        }
        function tasmiAutofillHijriDate() {
            var dateInput = document.getElementById('exam_date');
            var hijriInput = document.getElementById('hijri_date');
            if (!dateInput || !hijriInput) return;
            if (!dateInput.value) {
                hijriInput.value = '';
                return;
            }
            try {
                var date = new Date(dateInput.value + 'T00:00:00+07:00');
                if (Number.isNaN(date.getTime())) return;
                hijriInput.value = new Intl.DateTimeFormat('id-ID-u-ca-islamic', {
                    timeZone: 'Asia/Jakarta',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    era: 'short'
                }).format(date);
            } catch (error) {
                // Server menghitung ulang ketika data disimpan.
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            tasmiToggleJuzFields();
            tasmiAutofillHijriDate();
        });
    </script>
    @endpush
</x-layouts.portal>
