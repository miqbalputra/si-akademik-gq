# Syarat GeoPresensi untuk jurnal — 29 Oktober 2026

Pengisian dan pengeditan jurnal reguler, pengganti, Tafsir, pengganti Tafsir, dan formulir admin/Kabag memeriksa presensi guru yang benar-benar mengajar pada **tanggal jurnal dalam WIB**. Status Hadir, Hadir Terlambat, dan Hadir Izin Terlambat diperbolehkan. Belum presensi, Sakit, Izin, dan Alfa ditolak. Jam masuk/pulang tidak dibandingkan dengan sesi.

## Alur pemeriksaan

- `JournalAttendanceEligibilityService` membaca identitas dan penanda pengecualian terbaru dari database, lalu meminta laporan satu tanggal melalui `AttendanceTeacherReportClient`. Status terbaru diperiksa setiap penyimpanan, tanpa memakai keputusan dari cache atau dari browser.
- Jurnal pengganti memakai identitas pengganti. Formulir admin memakai calon tugas mengajar, guru pengganti, dan tanggal dari formulir; bukan identitas admin atau relasi jurnal sebelum diedit.
- NIY kosong, duplikat, tidak ditemukan, respons tidak valid, status tidak dikenal, dan gangguan API ditolak sebagai belum dapat diverifikasi. Pesannya tidak menganggap guru Alfa.
- Pemeriksaan edit Filament berada di `beforeSave`, sebelum relasi formulir disimpan. Pada portal guru, pemeriksaan berada sebelum transaksi penulisan materi dan absensi santri.
- Tombol **Cek ulang presensi** mempertahankan isian. Portal memakai GET pada halaman yang sudah ada dengan `attendance_check=1`; respons privat tanpa cache hanya berisi `allowed`, `exempt`, `status_label`, dan `message`. API key tetap di server.
- Materi, sesi, kelas, dan absensi yang dikirim dipertahankan saat penyimpanan ditolak. Jurnal lama beserta metadata dan absensinya tetap utuh.

## Mengaktifkan guru khusus

Admin membuka **Data Sekolah → Guru → Edit**, mengaktifkan **Bebas syarat GeoPresensi untuk jurnal**, lalu menyimpan. Penanda juga terlihat pada daftar guru. Kabag, kepala sekolah, dan guru tidak dapat mengubah pengaturan ini.

Kolom `teachers.journal_attendance_exempt` bernilai awal `false` untuk semua guru. Tidak ada guru yang otomatis dikecualikan berdasarkan nama atau NIY. Guru yang ditandai dapat membuat dan mengedit jurnal tanpa NIY, tanpa koneksi GeoPresensi, maupun ketika integrasi dinonaktifkan. Pencabutan pengecualian berlaku pada penyimpanan berikutnya.

Pengecualian tidak membuat presensi Hadir palsu. Jurnal tetap masuk riwayat, ekspor, rekap JP, serta performa; pengingat presensi tidak menagih guru yang dikecualikan. Kewajiban isi jurnal, pembatasan jadwal, duplikat, transaksi, arsip, dan penguncian jurnal tervalidasi tetap berlaku.

## Data lama dan penerapan

Migrasi `2026_10_29_000002_add_journal_attendance_exempt_to_teachers` hanya menambahkan satu kolom boolean guru. Tidak mengubah jurnal, absensi, indeks, maupun status arsip. Tidak ada filter presensi baru pada pembacaan atau perhitungan jurnal lama.

Sebelum penerapan pada lingkungan tujuan:

1. Verifikasi backup yang dapat dipulihkan dan periksa `php artisan migrate:status`.
2. Terapkan hanya migrasi tambahan ini jika migrasi pending lain belum ditinjau:
   `php artisan migrate --path=database/migrations/2026_10_29_000002_add_journal_attendance_exempt_to_teachers.php --force`.
3. Verifikasi integrasi GeoPresensi dan konfigurasi `ATTENDANCE_JOURNAL_INTEGRATION_ENABLED`, base URL, dan API key server. Guru tanpa pengecualian ditolak sementara jika integrasi belum aktif atau belum lengkap.
4. Aktifkan pengecualian guru khusus melalui Data Guru, lalu periksa jurnal dan rekapnya.

Jangan memakai reset database, `migrate:fresh`, atau menjalankan ulang migrasi historis untuk menerapkan fitur ini. Saat rollback kode, kolom tambahan boleh dipertahankan agar pengaturan pengecualian tidak hilang. Entrypoint deployment menjalankan seluruh migrasi pending otomatis; tinjau daftar pending production sebelum redeploy.

## Verifikasi

Pengujian menggunakan SQLite `:memory:` dengan pengaman koneksi pada `tests/TestCase.php`. Cakupannya meliputi status, empat menu jurnal, edit, guru/tanggal yang berubah di admin, Kabag, kegagalan API dan identitas, perubahan status setelah membuka formulir, penanda yang dicabut, hak pengaturan admin, rekap JP, arsip, transaksi, jadwal, dan ekspor.

Pengujian migrasi membandingkan isi jurnal aktif/arsip dan absensinya sebelum/sesudah penambahan kolom. Pengujian edit ditolak membandingkan seluruh baris, termasuk metadata. Regresi lama memakai fixture presensi Hadir melalui API palsu; pemeriksaan aplikasi tetap dijalankan dalam pengujian, tanpa bypass global.

Hasil akhir: **212 pengujian lulus, 1.142 assertions**. Laporan JUnit tersimpan di `storage/logs/journal-attendance-regression.xml`. Pemeriksaan JavaScript tombol cek ulang lulus untuk tanggal yang tetap, penonaktifan tombol selama pengecekan, respons Hadir, dan gangguan koneksi.

Migrasi tambahan sudah diterapkan pada **SQLite lokal workspace**, setelah backup `storage/app/private/backups/journal-attendance-before-20261029-213433.sqlite` lolos `integrity_check` dan dibandingkan dengan sumber. Manifest hash sebelum/sesudah disimpan di samping backup. Seluruh data asli pada **94 tabel** dan seluruh indeks cocok sebelum/sesudah migrasi; nilai baru pengecualian semuanya nonaktif. Migrasi KKM 22 Oktober dan migrasi indeks jurnal 29 Oktober yang sebelumnya pending tidak ikut dijalankan.

Deployment, backup, konfigurasi integrasi, dan migrasi production belum diverifikasi atau diterapkan dalam pekerjaan ini.
