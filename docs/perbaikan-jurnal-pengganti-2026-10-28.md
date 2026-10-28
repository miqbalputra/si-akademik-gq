# Perbaikan jurnal guru pengganti — 28 Oktober 2026

Laporan: halaman 500 saat akan mengisi jurnal pengganti untuk Senin, 19 Oktober. Kelas, mapel, dan tahap terjadinya error belum diketahui. Log production belum dapat dibaca; hubungan pasti antara laporan dan masing-masing temuan di bawah masih perlu diverifikasi di server.

## Temuan yang berhasil direproduksi

- Jurnal lama yang memiliki referensi guru asli, guru pengganti, atau santri yang sudah diarsipkan dapat membuat halaman input mengembalikan HTTP 500 (`Attempt to read property "name" on null`).
- Penanganan benturan jurnal belum mengenali PostgreSQL SQLSTATE `23505`. Simulasi exception PostgreSQL pada penyimpanan menghasilkan HTTP 500 sebelum perbaikan.
- Jika penyimpanan absensi gagal setelah jurnal dibuat, jurnal dan sebagian absensi baru tertinggal. Pengujian kegagalan pada absensi kedua membuktikan kondisi ini sebelum perbaikan.

## Perubahan

- Pada halaman jurnal reguler dan pengganti, referensi guru dan santri yang diarsipkan ikut dibaca khusus untuk jurnal yang sudah ada. Status arsip tidak berubah; santri arsip tetap tidak masuk daftar input absensi baru.
- Jurnal reguler/pengganti beserta absensinya disimpan dalam satu transaksi. Kegagalan membatalkan perubahan dari permintaan baru tersebut.
- Keempat controller jurnal (reguler, pengganti, Tafsir, dan pengganti Tafsir) mengenali `UniqueConstraintViolationException` Laravel. Benturan menampilkan pesan atau melewati kelas yang sudah terisi; jurnal lama tidak ditimpa. Kesalahan database lain tetap diteruskan.

## Verifikasi

- 90 pengujian terkait lulus, 324 assertions: jurnal reguler/pengganti/Tafsir, arsip guru/santri, benturan penyimpanan, transaksi, jadwal, edit, nilai, dan notifikasi.
- Pengujian membandingkan isi jurnal lama sebelum/sesudah dan memastikan absensi lama tetap tersimpan.
- PostgreSQL `23505` diuji melalui simulasi exception; pengujian memakai SQLite `:memory:`. Pengaman di `tests/TestCase.php` menolak koneksi uji selain database tersebut.
- Pemeriksaan sintaks empat controller dan `git diff --check` lulus.

## Cakupan penerapan

Paket perbaikan ini tidak membawa migrasi baru. Pemeriksaan dan pengujian lokal tidak menjalankan reset, penghapusan, pemulihan arsip, atau perubahan data aplikasi/production. Perubahan dua tampilan Tafsir dan `pesan.md` yang sudah ada sebelum pekerjaan tetap dipertahankan dan tidak disertakan dalam commit perbaikan ini. Push kode tidak menjadi bukti bahwa deployment production sudah berhasil.

Sebelum rollout, cocokkan log production dengan laporan, pastikan backup dapat dipulihkan, dan periksa migrasi pending. `docker-entrypoint.sh` menjalankan migrasi pending saat startup; status migrasi production belum diperiksa dalam pekerjaan ini.
