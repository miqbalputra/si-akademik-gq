# Sistem Akademik — SI Akademik GQ (nilai-sekolah)

Dokumentasi alur lengkap aplikasi akademik sekolah diniyyah. Diperbarui 2026-08-03. Mencakup: **Jurnal Guru Pengganti**, **mapping santri→kelas via web UI & artisan `santri:map-kelas`**, **Jurnal Tafsir serentak per-classroom**, **Ringkasan Penugasan Guru**, **Atur Jadwal Sesi per-kelas**, dan **Rekap JP per-guru**.

---

## 1. Ringkasan Teknologi

- **Framework:** Laravel 11 + Filament v5 (panel admin)
- **DB Production:** PostgreSQL di Coolify (BUKAN SQLite/MariaDB). BIGINT signed (no UNSIGNED). `unique()` Laravel di Postgres membuat unique INDEX, bukan constraint.
- **DB Testing:** SQLite in-memory (`use RefreshDatabase`)
- **Test:** PHPUnit 12 (bukan Pest), `#[DataProvider]` attribute
- **Repo:** github.com/miqbalputra/si-akademik-gq, branch utama → deploy via Coolify
- **Export:** HTML-table `.xls` via streamed response + CSV via `fputcsv` ke `php://temp` + BOM UTF-8 (tidak pakai maatwebsite/excel)

---

## 2. Roles & RBAC

Spatie roles (guard `web`): `admin`, `kabag_diniyyah`, `kabag_tahfidz`, `kepala_sekolah`, `guru`, `wali_santri`.

- Akses panel Filament dibatasi `User::canAccessPanel()` → hanya `['admin','kabag_diniyyah','kabag_tahfidz','kepala_sekolah']`.
- Trait `HasRoleBasedResourceAccess` + konstanta `VIEW_ROLES` / `MANAGE_ROLES` per resource.
- Diniyyah RBAC: VIEW=`['admin','kabag_diniyyah','kepala_sekolah']`, MANAGE=`['admin','kabag_diniyyah']`.

---

## 3. Deployment & Migrasi (Coolify + PostgreSQL)

### Deploy
- Push ke GitHub → Coolify auto-deploy.
- Branch: `feat/jurnal-guru-pengganti` (fitur pengganti), merged/deployed ke production.

### Cara masuk DB production
1. Buka Coolify → pilih resource container aplikasi (atau container Postgres).
2. Terminal / Execute Command di container yang sedang running.
3. Jalankan `psql` (lewat `psql "$DATABASE_URL"` atau psql interaktif).

### Cara menjalankan migrasi di production
Karena migrasi artisan bisa konflik dgn DB production, migrasi sering dijalankan **manual via raw SQL di psql** (bukan `php artisan migrate`). Format:
- Cek tabel migrations: `SELECT * FROM migrations ORDER BY id DESC LIMIT 5;`
- Setelah jalankan SQL manual, catat batch baru + insert row ke `migrations` tabel supaya artisan tahu migrasi sudah jalan:
  ```sql
  INSERT INTO migrations (migration, batch) VALUES ('2026_07_27_120000_add_substitute_teacher_to_diniyyah_class_journals_table', 4);
  ```

### Migrasi yang sudah dijalankan di production (2026-07-27)
1. `2026_07_27_100000` — unique index `diniyyah_class_journals_unique_idx` pada `(diniyyah_teacher_assignment_id, date, session_hour)`. (batch 3, id 31)
2. `2026_07_27_120000_add_substitute_teacher_to_diniyyah_class_journals_table` — kolom `substitute_teacher_id` nullable FK→teachers nullOnDelete + index. (batch 4)

---

## 4. Mapping Santri → Kelas (class_enrollments)

### Konsep
- Penempatan santri disimpan di tabel `class_enrollments` (`academic_term_id` + `classroom_term_id` + `student_id`, `status`, unique per term+student).
- 12 kelas: **Mustawa 1-6 Ikhwan** (male, sort 1-6) + **Mustawa 1-6 Akhwat** (female, sort 7-12).
- 225 santri, semua punya NIS.

### Tiga jalur mapping
1. **Web UI (utama):** menu **Struktur Kelas → Anggota Kelas** (hanya `admin`). Tombol "Template Import" (download `public/templates/import-kelas-enrollment.csv`) + "Import Kelas & Siswa" (upload CSV → `App\Services\Imports\ClassEnrollmentCsvImporter`). Idempotent/upsert.
2. **Artisan:** `php artisan santri:map-kelas {path?} {--term=} {--dry-run}` — format CSV lama. Pakai `App\Services\PlacementService::assignClass()`.
3. **SQL mentah (no-redeploy):** `mapping-santri-kelas.sql` (INSERT 12 Classroom+ClassroomTerm + 225 enrollments via JOIN on NIS, ON CONFLICT upsert).

### Format CSV web UI (`import-kelas-enrollment.csv`)
Header:
```
tahun_ajaran,periode,nama_kelas,nama_kelas_periode,level,kelompok_gender,urutan,kelas_aktif,kapasitas,status_kelas_periode,nis,no_absen,status_enrollment
```
- **Wajib:** `tahun_ajaran`, `periode`, `nama_kelas`. Sisanya opsional.
- Importer idempotent: bikin/update `Classroom` → `ClassroomTerm` → `ClassEnrollment` per santri. Baris tanpa `nis` hanya bikin kelas.

### ⚠️ Jebakan naming DB production
Importer `ClassEnrollmentCsvImporter::findAcademicTerm` matcher **case-sensitive & exact**:
- `whereHas(academicYear, name = tahun_ajaran)`
- `where(semester, lower(periode))` OR `where(name, periode_raw)`

Nama di DB production (diverifikasi via psql 2026-07-27):
- `academic_years.name` = **`Tahun Ajaran 2026/2027`** (id=1, aktif)
- `academic_terms` (id=2): `name` = **`Tahun Ajaran 2026/2027 Ganjil`**, `semester` = **`Ganjil`** (huruf besar)

Maka CSV **HARUS** pakai:
- `tahun_ajaran` = `Tahun Ajaran 2026/2027`
- `periode` = `Tahun Ajaran 2026/2027 Ganjil` (pakai nama term → cocok via `orWhere name`)

Kalau pakai contoh template (`2026/2027` / `ganjil`) → semua baris error "tidak ditemukan".

### Prasyarat sebelum upload
1. Tahun ajaran + periode sudah ada di DB (atau bikin dulu lewat menu Tahun Ajaran).
2. Santri sudah ada, dicocokkan via `nis`. Verifikasi 225 NIS cocok dengan query `cek-nis-santri.sql` (`matched` harus = 225).

### File mapping (di `D:/Project Sekolah GQ dan PKBM/`)
- `data_santri.xlsx` — sumber asli (NIS, NAMA, JK, KELAS).
- `mapping-santri-kelas.csv` — format artisan lama (nis,nama,jenis_kelamin,nama_kelas,level_name,gender_group).
- `generate-import-kelas-enrollment.js` — konversi ke format web UI.
- `import-kelas-enrollment.csv` — **file siap upload** (225 baris, tahun/periode disesuaikan ke nama DB, no_absen urut per kelas).
- `generate-cek-nis.js` + `cek-nis-santri.sql` — verifikasi NIS vs DB.
- `generate-sql-mapping.js` + `mapping-santri-kelas.sql` — alternatif SQL mentah.

### Status production (2026-07-27): SUDAH di-upload. 225 enrollment + 12 kelas masuk.

---

## 5. Jurnal KBM Diniyyah

### Tabel inti: `diniyyah_class_journals`
Kolom kunci:
- `diniyyah_teacher_assignment_id` — FK ke assignment guru **asli** (pemilik kelas+mapel).
- `substitute_teacher_id` — nullable FK ke `teachers`. Isi hanya jika jurnal ini diisi oleh **guru pengganti**.
- `date`, `session_hour`, `material`, `jp_count`.
- Unique index `(diniyyah_teacher_assignment_id, date, session_hour)` → satu slot jadwal hanya boleh satu jurnal (reguler atau pengganti).

### Model `DiniyyahClassJournal`
- Relasi `teacherAssignment` (guru asli), `substituteTeacher` (pengganti), `absences`.
- `effectiveTeacher()` → `substituteTeacher ?? teacherAssignment?->teacher` = **guru yang JP-nya dihitung untuk gaji**.

### Alur reguler (guru asli mengisi sendiri)
- Menu guru → Jurnal Kelas → pilih kelas+mapel (miliknya) → isi jam/materi/presensi.
- Controller `GuruDiniyyahJournalController::store` memaksa `assignment->teacher_id === teacher->id`.
- `destroy()` melarang hapus jurnal pengganti (403 jika `substitute_teacher_id !== null`).

---

## 6. Fitur Baru: Jurnal Guru Pengganti

### Tujuan
Saat guru diniyyah berhalangan (sakit), guru lain dapat mengisi jurnal menggantikannya. JP tercatat ke **pengganti** (untuk penghitungan gaji), dan di akun guru asli muncul tanda "Anda sudah digantikan oleh Guru XXX".

### Siapa boleh isi
**Semua guru** (akun terhubung ke `Teacher`, termasuk yang tidak punya assignment diniyyah). Tidak boleh menggantikan diri sendiri.

### Alur
1. Guru pengganti login → dashboard ada widget "Jurnal Guru Pengganti" (amber).
2. Buka menu → pilih kelas → pilih assignment dari daftar "Mapel — Guru Asli: {nama}" (diri sendiri dikecualikan).
3. Isi jam/materi/presensi → simpan.
4. Jurnal tersimpan dgn `diniyyah_teacher_assignment_id` = assignment asli, `substitute_teacher_id` = pengganti, `jp_count` = 1.
5. Di akun guru asli (menu Jurnal Kelas reguler), baris jurnal tsb muncul dgn badge merah **"Anda sudah digantikan oleh {nama pengganti}"** dan **tanpa tombol hapus**.
6. Hanya pengganti yang bisa hapus (lewat menu pengganti). Guru asli coba hapus via route reguler → 403.

### File fitur
- `app/Http/Controllers/GuruDiniyyahSubstituteJournalController.php` — index/store/destroy.
- `app/Http/Controllers/GuruDiniyyahJournalController.php` — eager `substituteTeacher` di index; larang hapus jurnal pengganti di destroy.
- `app/Models/DiniyyahClassJournal.php` — fillable `substitute_teacher_id` + relasi `substituteTeacher` + `effectiveTeacher()`.
- `routes/web.php` — 3 route guru pengganti (`guru.diniyyah-substitute-journals.{index,store,destroy}`).
- `resources/views/guru/diniyyah-substitute-journals/index.blade.php` — halaman pengganti.
- `resources/views/guru/diniyyah-journals/index.blade.php` — badge "digantikan oleh" + sembunyikan tombol hapus utk jurnal pengganti.
- `resources/views/guru/dashboard.blade.php` — widget "Jurnal Guru Pengganti".
- `database/migrations/2026_07_27_120000_add_substitute_teacher_to_diniyyah_class_journals_table.php`.

### Validasi di `store` pengganti
- `assignment->classSubject->classroom_term_id` harus cocok `classroom_term_id` input.
- `assignment->teacher_id !== teacher->id` (tidak boleh menggantikan diri sendiri) → redirect dgn error.
- `exists()` check `(assignment_id, date, session_hour)` → tolak dobel submit.
- `try/catch QueryException` + `isDuplicateKeyException` backstop (unique index).

### Tes (PHPUnit, semua lolos)
- `tests/Feature/DiniyyahSubstituteJournalTest.php` (6 tes)
- `tests/Feature/DiniyyahSubstituteJournalBadgeTest.php` (2 tes)
- `tests/Feature/DiniyyahJournalExportTest.php` (5 tes)
- Suite penuh 165/166 (hanya `AuthenticationTest::wali_santri` pre-existing fail, di luar cakupan).

---

## 7. Admin: Lihat & Ekspor Semua Jurnal

### Filament resource `DiniyyahClassJournalResource`
- Kolom: Pengganti (`substituteTeacher.name`) + **Guru Mengajar (gaji)** (`effectiveTeacher.name` via `getStateUsing`).
- Filter `tipe_jurnal`: `regular` (whereNull substitute) / `substitute` (whereNotNull).
- Form: select `substitute_teacher_id` (admin bisa koreksi manual).
- Header action: Export Excel + Export CSV (buka route export di tab baru).

### Route export
`admin/diniyyah-journals/export` (`admin.diniyyah-journals.export`) — gate `['admin','kabag_diniyyah','kepala_sekolah']`, guru → 403.

### Controller `DiniyyahJournalExportController`
- Filter: `date_from`, `date_until`, `guru` (teacher_id → where `teacherAssignment.teacher_id` OR `substitute_teacher_id`), `tipe` (all/regular/substitute), `format` (excel default / csv).
- Kolom export: No, Tanggal, Jam, Kelas, Mapel, Guru Asli, Pengganti, **Guru Mengajar (untuk gaji)** (= pengganti ?? asli), Materi, JP, Hadir, Sakit, Izin, Alpa, Bolos.
- Kolom "Guru Mengajar (untuk gaji)" inilah basis penghitungan gaji guru — otomatis = pengganti jika jurnal diisi sebagai pengganti.
- Excel: `resources/views/admin/diniyyah-journals/export-excel.blade.php` (HTML table .xls).
- CSV: dibangun di `php://temp` + BOM UTF-8, return regular `response()` (bukan StreamedResponse) biar testable.

---

## 8. Presensi Santri/Kelas — Cara Kerja Tanggal

Presensi **tidak punya setting tanggal sendiri**. Semua tanggal diturunkan dari `academic_terms.starts_at` / `ends_at`. Atur tanggal mulai KBM cukup dengan edit periode di **admin/academic-terms** → field **Dimulai Pada** / **Selesai Pada**.

Lokasi kode: `app/Http/Controllers/AttendanceController.php` + halaman Filament `AttendanceShortcut` (menu **Struktur Kelas → Presensi Kelas**).

Aturan tanggal (semua dari `term.starts_at`/`ends_at`):
- **Bulan default** saat buka presensi = bulan dari `term.starts_at` (`selectedMonth()`).
- **Rentang per bulan** mulai dari `max(awal bulan, term.starts_at)`, sampai `min(akhir bulan, term.ends_at)` (`dateRangeForMonth()`). Jadi kalau `starts_at = 2026-07-13`, di Juli presensi mulai tanggal 13, bukan 1.
- **Dropdown bulan** menampilkan bulan dari `starts_at` sampai `ends_at` (`availableMonths()`). **Kedua field wajib diisi** — kalau salah satu kosong, dropdown hanya tampilkan bulan berjalan.
- **Validasi tanggal** (`isValidAttendanceDate()`): menolak tanggal sebelum `starts_at`, setelah `ends_at`, Sabtu/Minggu (`schoolAttendanceDays`), libur sekolah (`school_holidays` tabel), dan masa depan.

Status production (2026-07-27): semester ganjil `starts_at = 2026-07-13` (Senin). Presensi kelas otomatis mulai hari pertama = Senin 13 Juli 2026.

Catatan: `dateRangeForMonth` hanya membatasi rentang tampilan/penyimpanan baru — baris presensi lama (kalau ada) tetap di DB, tidak terhapus.

---

## 9. Verifikasi & Troubleshooting

### Cek data mapping sudah masuk
```sql
SELECT c.name AS kelas, count(e.id) AS santri
FROM class_enrollments e
JOIN classroom_terms ct ON ct.id = e.classroom_term_id
JOIN classrooms c ON c.id = ct.classroom_id
JOIN academic_terms t ON t.id = e.academic_term_id
WHERE t.name = 'Tahun Ajaran 2026/2027 Ganjil'
GROUP BY c.name ORDER BY c.name;
-- Harusnya 12 baris, total 225.
```

### Cek tahun ajaran + periode
```sql
SELECT ay.name, t.name, t.semester, t.is_active
FROM academic_years ay JOIN academic_terms t ON t.academic_year_id = ay.id;
```

### Cek NIS santri vs CSV
Lihat `cek-nis-santri.sql` (WITH csv_nis VALUES ... → `matched` harus = total).

### Jalankan tes lokal
```bash
cd "D:/Project Sekolah GQ dan PKBM/nilai-sekolah"
php artisan test --filter=DiniyyahSubstituteJournalTest
php artisan test --filter=DiniyyahSubstituteJournalBadgeTest
php artisan test --filter=DiniyyahJournalExportTest
php artisan test      # suite penuh
```

---

## 10. Catatan & di Luar Cakupan

- **Rekap JP per-guru** SUDAH diimplementasikan: Filament Page `app/Filament/Pages/RekapJurnalGuru.php` + `app/Services/RekapJurnalGuruService.php` + `app/Http/Controllers/RekapJurnalGuruExportController.php`. Basis JP = `DiniyyahClassJournal::effectiveTeacher()` (pengganti dapat JP; jurnal Tafsir serentak di-dedup per `(guru,tanggal)` = 1 JP). Lihat juga menu Ringkasan Penugasan Guru (§11).
- **Validasi input sesi jurnal** SUDAH diimplementasikan di sisi form (bukan di controller `store`):
  - commit `83eea54` — batasi pilihan sesi sesuai jadwal mengajar guru (penugasan + mapel + hari + sesi).
  - commit `37f05bf` — tampilkan weekday & matikan form di hari non-mengajar guru (Jurnal Kelas).
  - Catatan: validasi *tanggal* (term aktif / bukan libur / bukan masa depan) pada controller `store` reguler maupun pengganti **belum** diimplementasikan; jurnal tetap bergantung pada pilihan sesi yang sudah difilter form.
- `AuthenticationTest::wali_santri` pre-existing fail — di luar cakupan, belum diperbaiki.
- DB lokal hanya 10 santri demo; data asli 225 santri ada di production.

---

## 11. Fitur Terbaru (2026-07-30 s/d 2026-07-31)

### Jurnal Tafsir Serentak per-Classroom
- Admin centang beberapa kelas sekaligus di **Jurnal Tafsir serentak** + menu **Jurnal Pengganti Tafsir** (commit `015fa4d`). Tombol **Centang Semua/Kosongkan** + hitungan tercentang berfungsi (commit `b6b87f1`).
- **Prasyarat:** admin set `DiniyyahClassSubject` bernama "Tafsir" untuk M2-M6 + assign guru ke kelas/mapel tsb.
- **⚠️ Konstanta mesin `tafsir` jangan diubah.** Di production `ClassSession` Tafsir bernama "Tafsir (M2 - M6)" sedangkan mesin memakai konstanta `'tafsir'`; matching di Wali monitoring sudah tafsir-aware (commit `32568be`). Migrasi `000006` sengaja no-op — jika konstanta diubah akan muncul duplikat jadwal. Lihat memori `tafsir-jam-tanda-mismatch-prod`.

### Ringkasan Penugasan Guru (audit per kelas)
- Filament Page `app/Filament/Pages/RingkasanPenugasanGuru.php` — tabel interaktif untuk audit penugasan guru per kelas (commit `0985e93` → refactor tabel interaktif `c89138e` → rapikan section periode `5bc107d` → gaya Perbandingan Sesi `6939f91`).

### Atur Jadwal Sesi per-Kelas (admin UI)
- Halaman Filament mengatur jadwal sesi per-kelas langsung dari aplikasi (commit `ad12b20`), plus rapian halaman Atur Jadwal Sesi & Perbandingan (`feece8a`).

### Cari Mapel Kelas / Tugas Mengajar by nama
- commit `8bd040c` — pencarian `DiniyyahClassSubject` & `DiniyyahTeacherAssignment` di-match by nama kelas & mapel (case/space-tolerant).

### Fix "Jam ?" di Pantau Jurnal Kelas (wali)
- commit `32568be` — jurnal Tafsir serentak kini mengisi slot jadwal di Pantau Jurnal, menghilangkan tanda "Jam ?". Matching tafsir-aware di Wali monitoring; Tafsir di-skip di form reguler.

### Mapping santri→kelas via artisan
- commit `f83d0fb` — command `php artisan santri:map-kelas {path?} {--term=} {--dry-run}` (file `app/Console/Commands/MapSantriKelas.php`). Jalur ketiga selain web UI (§4) & SQL mentah. Pakai `PlacementService::assignClass()` — idempoten, find-or-create Classroom + ClassroomTerm.

---

## 12. Fitur Tasmi' (2026-08-11)

### Konsep
Tasmi' = ujian setoran hafalan tahfidz. PJ Tasmi' adalah guru yang ditugaskan menerima ujian tasmi'. Ada 2 jenis ujian:
- **Tasmi' 1 Juz** — setoran 1 juz full (juz awal = juz akhir, pilihan juz 1-30).
- **Tasmi' 5 Juz** — setoran 5 juz full (rentang juz, mis. Juz 26-30; harus tepat 5 juz).

Predikat: `Maqbul`, `Jayyid`, `Jayyid Jiddan`, `Mumtaz`.

### Aturan Gender (PENTING)
- **Ustadz (gender=male)** hanya bisa menguji kelas **ikhwan** (`classroom.gender_group='male'`).
- **Ustadzah (gender=female)** hanya bisa menguji kelas **akhwat** (`classroom.gender_group='female'`).
- Filtering gender otomatis dari `teacher.gender`, BUKAN dari field di tabel penugasan. Kelas `mixed` sengaja dikecualikan (tidak ada gender pasti).
- Logika: `App\Services\TasmiService::expectedGenderScope()` + `eligibleClassroomTerms()`.

### Skema Database
Migrasi `2026_08_11_000001_create_tasmi_tables.php`:

1. `tasmi_examiner_assignments` — penugasan PJ Tasmi' (unique `(academic_term_id, teacher_id)`). Admin assign via panel.
2. `tasmi_records` — record ujian tasmi' per santri. Unique `(academic_term_id, student_id, exam_type, exam_date)` mencegah dobel input. Field: `exam_type` (1_juz/5_juz), `juz_start`+`juz_end`, `exam_date` (masehi), `hijri_date`, `predicate`, `examiner_teacher_id`, audit fields (`input_by`, `input_at`, `last_updated_by`), soft delete.

### Model & Relasi
- `App\Models\TasmiExaminerAssignment` → `Teacher`, `AcademicTerm`, `TasmiRecord`.
- `App\Models\TasmiRecord` → `Student`, `AcademicTerm`, `ClassroomTerm`, `ClassEnrollment`, `Teacher` (examiner), `TasmiExaminerAssignment`, `User` (inputBy, lastUpdatedBy). Konstanta `EXAM_TYPE_*` & `PREDICATE_*` + helper `predicateLabel()`, `juz_range_label`.
- Relasi baru di: `Teacher::isTasmiExaminer()` + `tasmiExaminerAssignments()` + `tasmiRecordsAsExaminer()`; `User::isTasmiExaminer()`; `Student/ClassEnrollment/ClassroomTerm/AcademicTerm` → `tasmiRecords()`.

### Alur Input (Portal Guru)
1. Admin assign PJ Tasmi' via Filament: **Tahfidz → Penugasan PJ Tasmi'** (`/admin/tasmi-examiner-assignments`). Pilih periode + guru (status: active).
2. Guru yang ditugaskan login → dashboard → menu **"Tasmi'"** muncul di nav portal guru (kondisional `User::isTasmiExaminer()`).
3. Klik "Input Tasmi' Baru" → pilih kelas (otomatis filter sesuai gender guru) → pilih santri (otomatis dari `class_enrollments` aktif di kelas tsb) → isi hari, tanggal masehi, tanggal hijriyah, jenis ujian (radio), juz (dropdown), predikat, catatan → simpan.
4. UI juz dinamis: 1 juz → 1 dropdown (auto-set juz_start=juz_end); 5 juz → 2 dropdown (dari→sampai, auto-compute end = start+4).
5. Validasi controller: rentang juz sesuai tipe (1_juz → start=end; 5_juz → end-start+1=5). Gender kelas sesuai gender guru. Santri aktif di kelas. Anti-dobel via unique index + backstop `QueryException`.

### Laporan & Audit (Filament + Portal)
- **Filament `/admin/tasmi-records`** (Laporan Tasmi'): admin & kabag_tahfidz bisa lihat semua + edit semua; kepala_sekolah read-only. Filter: tipe, predikat, periode, penguji, kelas, rentang tanggal. Detail view + edit form.
- **Portal guru `/guru/tasmi/records`**: PJ Tasmi' lihat record miliknya, filter (search/tipe/predikat/tanggal), edit/hapus record sendiri.
- **Audit log**: `TasmiRecordObserver` → `score_change_logs` (old/new predicate, reason `created_tasmi`/`updated_tasmi`) + `activity_log` (detail perubahan field + soft-delete).

### Wali Kelas (Read-only)
- Menu **"Tasmi' Kelas Saya"** muncul di nav guru yang adalah homeroom teacher (`canAccessAttendance()`).
- `WaliKelasTasmiController` → filter record tasmi' santri di kelas yang diwalikan (via `homeroom_assignments`), read-only. Tidak bisa edit/hapus.
- Halaman detail view (`/guru/tasmi-wali/{record}`) dengan gate: wali kelas hanya boleh lihat record santri di kelasnya saat tanggal ujian.

### Permission (Spatatie)
RolePermissionSeeder diperbarui:
- `manage_tasmi_examiners` → admin.
- `input_tasmi_records` → guru (dipakai sebagaimana role guru sudah punya input_diniyyah_scores; akses portal di-enforce via `User::isTasmiExaminer()` bukan permission tunggal).
- `view_all_tasmi_records` → kabag_tahfidz.

### File Inti
- Migrasi: `database/migrations/2026_08_11_000001_create_tasmi_tables.php`
- Model: `app/Models/TasmiExaminerAssignment.php`, `app/Models/TasmiRecord.php`
- Service: `app/Services/TasmiService.php` (gender scope + eligible classrooms)
- Observer: `app/Observers/TasmiRecordObserver.php` (audit log)
- Controller: `app/Http/Controllers/GuruTasmiController.php` (index/create/store/records/edit/update/destroy), `app/Http/Controllers/WaliKelasTasmiController.php` (index/show read-only)
- Filament: `app/Filament/Resources/TasmiExaminerAssignments/*` (admin assign PJ), `app/Filament/Resources/TasmiRecords/*` (laporan + edit)
- Views: `resources/views/guru/tasmi/{index,create,records,edit,wali-view,show}.blade.php`
- Routes: `routes/web.php` prefix `guru/tasmi*` + `guru/tasmi-wali*`
- Test: `tests/Feature/TasmiFeatureTest.php` (16 test)

### Tes
`php artisan test --filter=TasmiFeatureTest` — 16 test covering: akses tanpa penugasan (403), gender filtering ustadz/ustadzah, input 1 juz & 5 juz (valid/invalid range), anti-dobel, edit own/other record, delete soft, audit log, akses Filament resource (admin/kabag_tahfidz/guru).

---

*Dokumen ini ditulis 2026-07-27, diperbarui 2026-08-11. Update saat ada perubahan signifikan (fitur baru, migrasi, perubahan alur).*