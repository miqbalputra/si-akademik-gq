<x-layouts.portal title="Input Tasmi'" portalLabel="Portal Guru" breadcrumb="Input Tasmi'">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .form-label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#475569; margin-bottom:6px; }
        .form-input { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 13px; font-size:14px; font-weight:500; color:#1e293b; background:#f8fafc; outline:none; transition: border-color .2s, background .2s; font-family:'Outfit',sans-serif; }
        .form-input:focus { border-color:#17663a; background:#fff; box-shadow: 0 0 0 3px rgba(0,223,102,.18); }
        .form-hint { font-size:12px; color:#64748b; margin-top:4px; font-weight:500; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:13px; border-radius:10px; padding:9px 18px; transition: all .2s; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; }
        .btn-primary { background:#00df66; color:#063d23; box-shadow: 0 2px 8px rgba(0,173,79,.22); }
        .btn-primary:hover { background:#29fa79; transform: translateY(-1px); }
        .btn-outline { background:transparent; border:1.5px solid #e2e8f0; color:#475569; }
        .btn-outline:hover { background:#f8fafc; }
    </style>
    @endpush

    {{-- Header --}}
    <header class="fade-up" style="margin-bottom:24px;">
        <a href="{{ route('guru.tasmi.index') }}" style="font-size:12px;font-weight:700;color:#17663a;display:inline-flex;align-items:center;gap:4px;margin-bottom:10px;text-decoration:none;">
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Kembali ke dashboard Tasmi'
        </a>
        <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">Input Tasmi' Baru</h1>
        <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">Pilih kelas → pilih santri → isi data setoran tasmi'.</p>
    </header>

    @if ($errors->any())
        <div style="margin-bottom:20px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:600;color:#991b1b;" class="fade-up">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Pemilih kelas sengaja memakai GET; jangan jadikan perubahan dropdown
         sebagai submit form Tasmi' yang membutuhkan seluruh data ujian. --}}
    <form method="GET" action="{{ route('guru.tasmi.create') }}" class="fade-up delay-1">
        <div class="card" style="padding:24px;margin-bottom:18px;">
            <div style="margin-bottom:20px;">
                <label for="classroom_term_id" class="form-label">1. Pilih Kelas</label>
                <select id="classroom_term_id" name="classroom_term_id" class="form-input" required onchange="this.form.submit()">
                    <option value="">— Pilih kelas —</option>
                    @foreach($classroomTerms as $ct)
                        <option value="{{ $ct->id }}" @if((string)($selectedClassroomTerm->id ?? '') === (string)$ct->id) selected @endif>
                            {{ $ct->classroom->name ?? $ct->name }} ({{ $ct->classroom->gender_group === 'male' ? 'Ikhwan' : 'Akwat' }})
                        </option>
                    @endforeach
                </select>
                <p class="form-hint">Hanya kelas @if($genderScope === 'male') ikhwan @elseif($genderScope === 'female') akhwat @endif yang muncul sesuai gender Anda.</p>
            </div>
        </div>
    </form>

    @if($selectedClassroomTerm)
        <form method="POST" action="{{ route('guru.tasmi.store') }}" class="fade-up delay-1">
            @csrf
            <input type="hidden" name="classroom_term_id" value="{{ $selectedClassroomTerm->id }}">
            <div class="card" style="padding:24px;margin-bottom:18px;">
                {{-- 2. Pilih Santri --}}
                <div style="margin-bottom:20px;">
                    <label for="student_id" class="form-label">2. Nama Santri</label>
                    <select id="student_id" name="student_id" class="form-input" required>
                        <option value="">— Pilih santri —</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" @if((string)($selectedStudent->id ?? '') === (string)$student->id) selected @endif>
                                {{ $student->name }}@if($student->nis) · NIS {{ $student->nis }}@endif
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">{{ $students->count() }} santri aktif di kelas ini.</p>
                </div>

                {{-- 3. Hari & Tanggal --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
                    <div>
                        <label for="exam_day_label" class="form-label">3. Hari</label>
                        <select id="exam_day_label" name="exam_day_label" class="form-input">
                            <option value="">— Pilih hari —</option>
                            @foreach($dayOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('exam_day_label') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="exam_date" class="form-label">Tanggal (Masehi) *</label>
                        <input id="exam_date" name="exam_date" type="date" class="form-input" required value="{{ old('exam_date') }}" onchange="tasmiAutofillHijriDate()">
                    </div>
                    <div>
                        <label for="hijri_date" class="form-label">Tanggal (Hijriyah)</label>
                        <input id="hijri_date" name="hijri_date" type="text" class="form-input" readonly data-hijri-auto placeholder="Otomatis dari tanggal Masehi" maxlength="50" value="{{ old('hijri_date') }}">
                        <p class="form-hint">Terisi otomatis berdasarkan kalender Hijriyah dan zona waktu WIB.</p>
                    </div>
                </div>

                {{-- 4. Jenis Ujian & Juz --}}
                <div style="margin-bottom:20px;">
                    <label class="form-label">4. Jenis Ujian Tasmi'</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                        @foreach($examTypeOptions as $value => $label)
                            <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:all .2s;">
                                <input type="radio" name="exam_type" value="{{ $value }}" required @if(old('exam_type') === $value) checked @endif onchange="tasmiToggleJuzFields()">
                                <span style="font-size:14px;font-weight:700;color:#0f172a;">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div id="juz-one-block" style="display:none;margin-bottom:12px;">
                        <label for="juz_single" class="form-label">Juz yang Diuji (1 - 30)</label>
                        <select id="juz_single" name="juz_single" class="form-input" onchange="document.getElementById('juz_start').value=this.value; document.getElementById('juz_end').value=this.value;">
                            <option value="">— Pilih juz —</option>
                            @for($i = 1; $i <= 30; $i++)
                                <option value="{{ $i }}">Juz {{ $i }}</option>
                            @endfor
                        </select>
                        <p class="form-hint">Setoran 1 juz full — pilih satu juz (1-30).</p>
                    </div>

                    <div id="juz-five-block" style="display:none;margin-bottom:12px;">
                        <p class="form-label" style="margin-bottom:8px;">Rentang 5 Juz (dari juz X sampai juz Y)</p>
                        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;">
                            <select id="juz_start" name="juz_start" class="form-input" onchange="tasmiUpdateFiveEnd()">
                                <option value="">Dari juz</option>
                                @for($i = 1; $i <= 26; $i++)
                                    <option value="{{ $i }}" @if(old('juz_start') == $i) selected @endif>Juz {{ $i }}</option>
                                @endfor
                            </select>
                            <span style="font-weight:700;color:#475569;">→</span>
                            <select id="juz_end" name="juz_end" class="form-input">
                                <option value="">Sampai juz</option>
                                @for($i = 5; $i <= 30; $i++)
                                    <option value="{{ $i }}" @if(old('juz_end') == $i) selected @endif>Juz {{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <p class="form-hint">Setoran 5 juz full — rentang harus tepat 5 juz (mis. Juz 1 → Juz 5).</p>
                    </div>
                </div>

                {{-- 5. Predikat --}}
                <div style="margin-bottom:20px;">
                    <label for="predicate" class="form-label">5. Predikat *</label>
                    <select id="predicate" name="predicate" class="form-input" required>
                        <option value="">— Pilih predikat —</option>
                        @foreach($predicateOptions as $value => $label)
                            <option value="{{ $value }}" @if(old('predicate') === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 6. Catatan --}}
                <div style="margin-bottom:8px;">
                    <label for="notes" class="form-label">6. Catatan (opsional)</label>
                    <textarea id="notes" name="notes" class="form-input" rows="3" maxlength="1000" placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Simpan Data Tasmi'
                </button>
                <a href="{{ route('guru.tasmi.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    @else
        <div class="card fade-up delay-1" style="padding:24px;text-align:center;">
            <p style="font-size:14px;color:#64748b;font-weight:600;margin:0;">Pilih kelas terlebih dahulu untuk menampilkan daftar santri.</p>
        </div>
    @endif

    @push('scripts')
    <script>
        function tasmiToggleJuzFields() {
            var checked = document.querySelector('input[name="exam_type"]:checked');
            var type = checked ? checked.value : null;
            var oneBlock = document.getElementById('juz-one-block');
            var fiveBlock = document.getElementById('juz-five-block');
            if (oneBlock) oneBlock.style.display = (type === '1_juz') ? 'block' : 'none';
            if (fiveBlock) fiveBlock.style.display = (type === '5_juz') ? 'block' : 'none';

            // Kosongkan field juz yang tidak aktif agar tidak ter-submit
            if (type === '1_juz') {
                var js = document.getElementById('juz_start');
                var je = document.getElementById('juz_end');
                if (js) js.value = '';
                if (je) je.value = '';
            } else if (type === '5_juz') {
                var jsingle = document.getElementById('juz_single');
                if (jsingle) jsingle.value = '';
            }
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
                // Tambahkan offset WIB secara eksplisit agar tanggal tidak
                // bergeser pada perangkat dengan zona waktu selain Indonesia.
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
                // Nilai tetap akan dihitung ulang di server saat disimpan.
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            tasmiToggleJuzFields();
            tasmiAutofillHijriDate();
        });
    </script>
    @endpush
</x-layouts.portal>
