# Penerapan TailAdmin dan pemeriksaan data

Tanggal pemeriksaan: 8 Oktober 2026.

## Cakupan implementasi

- Tema bersama mencakup 93 template Blade web, termasuk komponen dan partial. Resource serta widget Filament menggunakan theme panel bersama.
- Role yang dicakup: admin, kepala sekolah, kabag Diniyyah, kabag Tahfidz, guru, dan wali santri.
- Halaman publik, autentikasi, pemulihan sandi, pemilihan ruang kerja, portal, notifikasi, kalender, presensi, jurnal, RPP/Promes, penilaian, Tahfidz, Tasmi', leger, rapor web, halaman custom Filament, dan halaman error menggunakan token serta komponen visual yang sama.
- Warna primary, info, success, warning, dan danger disimpan global melalui Pengaturan Tampilan yang hanya dapat diakses admin. Pilihan light/dark disimpan pada perangkat dengan kunci terpisah menurut pengguna.
- Layanan palet memakai scope per request/job agar proses Octane tidak menyimpan palet lama lintas request.
- Rute, controller, layanan bisnis, resource bisnis Filament, dan aturan akses tidak diubah. Pengaturan tampilan menambah satu tabel tersendiri.
- Hash 12 template PDF, Excel, dan cetak sama dengan snapshot sebelum port tampilan.

## Pemeriksaan visual

Pemeriksaan browser menggunakan database salinan `storage/app/private/ui-verification/qa.sqlite`, bukan database asli. Data contoh dari seeder proyek hanya ditambahkan pada salinan tersebut.

| Pemeriksaan | Hasil |
| --- | --- |
| Halaman awal enam role | Dapat ditampilkan pada database preview |
| Desktop 1440 × 1000, tablet 768 × 1024, ponsel 390 × 844 | Shell responsif, sidebar dapat dibuka dan ditutup |
| Form Tasmi' pada ponsel | Pilihan kelas/santri dan isian tetap bekerja |
| Grafik Tahfidz Wali | Empat grafik tampil; perbaikan ukuran mencegah grafik melebar keluar layar ponsel |
| Tabel dan pagination Filament | Tabel lebar digulir di dalam kontainer |
| Modal Filament pada ponsel | Form modal tampil dan dapat dibatalkan |
| Notifikasi Guru | Dropdown dan isi notifikasi tampil |
| Palet admin | Perubahan primary pada preview tersimpan dan terbaca oleh Kepala Sekolah, Guru, dan halaman publik/error |
| Mode pengguna | Mode gelap tetap setelah reload; pengguna lain memiliki pilihan mode tersendiri |
| Error 403, 404, 419, 429, 500, 503 | Tema konsisten; status HTTP dan izin tetap |

Contoh tangkapan layar tersedia di `storage/app/private/ui-verification/`. Palet biru pada screenshot desktop merupakan warna yang diubah untuk memeriksa pengaturan pada salinan database.

## Pengujian

- `npm run build`: berhasil.
- `php artisan view:cache`: berhasil.
- `git diff --check`: berhasil.
- Suite awal: 480 pengujian, 474 lulus, 5 gagal, 1 dilewati. Lima kegagalan terkait label aksesibilitas, format deklarasi CSS, dan pembandingan atribut model pada pengujian baru telah diperbaiki. Pengujian ulang yang mencakup seluruh kegagalan tersebut: 7 lulus, 32 assertion.
- Pemeriksaan tambahan navigasi portal, workspace, akses panel, Tahfidz, pengaturan tema, dan halaman error: 29 lulus, 112 assertion.
- Pengujian halaman error setelah penambahan tampilan 403/419/429/503: 3 lulus, 18 assertion. Satu pengujian baru mempertahankan status HTTP keempat halaman.
- Satu pengujian suite awal dilewati karena PHP GD/FreeType tidak tersedia. Suite penuh tidak diulang setelah perbaikan; pengujian yang gagal dan area terkait diperiksa ulang.
- Pengujian dipaksa memakai SQLite `:memory:` dan dihentikan sebelum RefreshDatabase bila konfigurasi menunjuk database lain.

## Integritas data asli

Snapshot SHA-256 per baris tabel dan per file dilakukan sebelum dan setelah penyelesaian UI. Hasil akhir:

- 83 tabel persisten, 55 baris total: identik.
- 2 file tersimpan pada disk private/public: identik.
- Tidak ada tabel persisten yang berubah atau hilang.
- Tabel cache, session, dan antrean sementara tidak termasuk pembandingan data bisnis.

Cadangan database asli:

`storage/app/private/ui-verification/database-before-tailadmin-20261008-105739.sqlite`

Ukuran cadangan: 1.536.000 byte. Snapshot pembanding: `baseline.json` dan `final.json` pada folder yang sama. Artefak verifikasi berada di storage private yang diabaikan Git.

## Kendala database lokal yang sudah ada

Database asli memiliki delapan migrasi bisnis lama yang belum diterapkan. Kode saat ini sudah membutuhkan sebagian skemanya, termasuk `users.deleted_at`, sehingga login pada database asli dapat gagal. Migrasi tersebut hanya dijalankan pada salinan preview untuk pemeriksaan seluruh role. Database asli tetap utuh.

Migrasi yang masih pending pada database asli:

1. `2026_09_25_000001_add_date_effective_versions_to_diniyyah_teaching_schedules`
2. `2026_09_25_000002_add_schedule_review_metadata_to_diniyyah_schedule_change_logs`
3. `2026_10_03_000001_enforce_one_attendance_per_student_per_day`
4. `2026_10_03_000002_add_validation_workflow_to_diniyyah_class_journals`
5. `2026_10_03_000003_create_report_card_revision_logs_table`
6. `2026_10_03_000004_add_soft_deletes_to_users_table`
7. `2026_10_03_000005_audit_student_attendance_changes`
8. `2026_10_07_090000_create_student_departures_table`

Migrasi `2026_10_03_000001_enforce_one_attendance_per_student_per_day` memeriksa presensi ganda dan menghentikan migrasi bila ditemukan; migrasi ini tidak menghapus baris presensi. Penyelarasan database asli perlu ditinjau sebagai pekerjaan tersendiri agar data historis dipertahankan. Implementasi UI selesai; kesiapan runtime database asli belum dapat dinyatakan penuh sebelum kendala skema ini ditangani.

## Pemeriksaan jalur deployment production

Pemeriksaan lanjutan menunjukkan `docker-entrypoint.sh` menjalankan `php artisan migrate --force` saat container dimulai. Perubahan UI hanya menambahkan migrasi tabel pengaturan tampilan, tetapi skrip deployment tersebut juga akan menjalankan migrasi bisnis lain yang masih pending di database tujuan.

Migrasi lama `2026_07_27_100000_add_unique_index_to_diniyyah_class_journals_table` menghapus jurnal duplikat dan baris absence terkait sebelum menambahkan indeks unik. Migrasi ini sudah masuk riwayat lokal, tetapi statusnya di production belum diverifikasi. Tidak ada migrasi production yang dijalankan dalam pekerjaan UI ini.

Sebelum redeploy production, periksa `php artisan migrate:status` pada aplikasi production dan tinjau migrasi yang pending. Backup sendiri tidak menghapus isi database. Pembuatan tabel pengaturan tampilan juga tidak menghapus data presensi atau jurnal.
