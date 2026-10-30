# Koreksi satu kali Fiqih Ibadah → Fiqih

Keputusan: satu mapel utama **Fiqih**, berlaku untuk seluruh semester termasuk arsip. Hentikan seluruh koreksi jika ada konflik atau dokumen terkunci. Tidak ada menu baru atau migrasi data otomatis saat deployment.

## Pratinjau production

Setelah kode tersedia di container aplikasi, jalankan dari terminal aplikasi Coolify:

```sh
php artisan diniyyah:correct-fiqih
```

Tanpa `--apply` perintah hanya membaca database. Pratinjau tidak membuat scope KKM, tidak mengubah data, tidak mengirim notifikasi, dan tidak membuat backup. Hasil JSON berisi master sumber/tujuan, ID mapel kelas dan KKM yang akan dialihkan, jumlah penugasan/jurnal/absensi/penilaian/revisi KKM sumber yang dipertahankan, `blockers`, dan `fingerprint`. Output tidak berisi password, API key, nama santri, atau rincian nilai.

`blockers` harus kosong sebelum koreksi dapat diterapkan. Apabila perintah berhenti karena master tidak ditemukan, jangan membuat master baru untuk memaksanya: periksa bahwa terminal tersambung ke database aplikasi yang benar. Nama dicocokkan tanpa membedakan kapital dan spasi awal/akhir, tetapi harus ada tepat satu master sumber dan satu tujuan yang tidak terarsip.

## Pengaman

- ID mapel kelas, penugasan guru, jadwal, jurnal, dan presensi tetap. Hanya `subject_id` pada mapel kelas dan scope KKM yang dialihkan ke master Fiqih. Master Fiqih Ibadah dinonaktifkan setelah seluruh relasinya dialihkan; baris dan nama lamanya tetap ada untuk penelusuran.
- Angka KKM, ID/status/penyusun/revisi ACC, materi jurnal, nilai santri, absensi, snapshot rapor/leger, dan JP tidak dihitung ulang atau ditulis ulang.
- Sesudah koreksi, guru kedua nama mapel pada Mustawa dan semester yang sama masuk kelompok KKM Fiqih yang sama. Penugasan penyusun/angka KKM selanjutnya diperbarui melalui alur KKM dan ACC yang sudah ada.
- Dua mapel pada kelas yang sama, termasuk arsip, menghentikan seluruh koreksi. Dua scope KKM pada semester/Mustawa yang sama juga menghentikannya, meskipun angka KKM sama. Penyusun atau revisi tidak dipilih otomatis.
- Perbedaan angka KKM kelas paralel/revisi aktif dan jenjang Mustawa yang belum dikenali juga memblokir koreksi. Periksa hasil pratinjau untuk menentukan perbaikan yang tepat.
- Leger/rapor Diniyyah terkunci atau terbit dalam kelompok yang terdampak menghentikan koreksi. Perintah tidak membatalkan kunci dokumen.
- Sidik data diverifikasi ulang dalam transaksi dengan penguncian. Jika data berbeda dari pratinjau, jalankan pratinjau ulang. Kegagalan backup, pemeriksaan, atau penulisan membatalkan seluruh transaksi.
- Isi semua tabel persisten lain dibandingkan sebelum/sesudah. Cache, sesi, antrean, dan activity log dikecualikan dari pembandingan; koreksi berhasil dicatat pada activity log.

## Penerapan setelah hasil pratinjau diperiksa

1. Pastikan backup database lengkap production tersedia dan dapat dipulihkan melalui prosedur backup yang ada. Snapshot otomatis perintah ini hanya mencakup tiga tabel yang diizinkan berubah dan hash tabel yang dilindungi; snapshot bukan pengganti backup database lengkap.
2. Pilih jalur snapshot tambahan:
   - Default: `BACKUP_DISK` menunjuk S3 privat dengan akses baca/tulis.
   - Backup penuh Coolify sudah diunduh: sertakan nama file dan SHA-256 melalui opsi Coolify di bawah. Salinan tiga tabel sebelum perubahan disimpan di `activity_log` dan diverifikasi setelah dibaca ulang dari database, tanpa memerlukan S3 aplikasi.
   Perintah tidak membersihkan backup lama.
3. Aktifkan maintenance, lalu jalankan pratinjau lagi agar fingerprint mencerminkan data terakhir:

```sh
php artisan down
php artisan diniyyah:correct-fiqih
```

4. Jika `blockers` kosong dan hasilnya sesuai, gunakan nilai fingerprint baru:

```sh
php artisan diniyyah:correct-fiqih --apply --fingerprint=SALIN_64_KARAKTER_DARI_PRATINJAU
```

Jika memakai backup Coolify yang sudah diunduh:

```sh
php artisan diniyyah:correct-fiqih --apply --fingerprint=SIDIK_PRATINJAU --coolify-backup="NAMA_FILE_BACKUP.gz" --coolify-backup-sha256=SHA256_FILE_BACKUP
```

Nama file harus berupa nama berkas, bukan URL atau path. Kedua opsi Coolify wajib lengkap. Nama file dan hash backup penuh dicatat sebagai konfirmasi operator (`operator_confirmed_download`); server aplikasi tidak mengaku telah membuka file yang berada di komputer operator. Keberadaan, integritas, isi database, serta kemampuan pemulihan backup penuh tetap harus diperiksa sebelum memakai opsi ini.

Pada jalur Coolify, arsip tambahan berisi seluruh kolom lama tiga tabel yang boleh berubah beserta hash tabel persisten lain. Arsip dibuat pada koneksi/transaksi database yang sama sebelum pembaruan relasi dan dibaca ulang untuk verifikasi checksum. Kegagalan penyimpanan/verifikasi membatalkan koreksi. Jika transaksi dibatalkan, baris arsipnya ikut dibatalkan. Jika berhasil, hasil JSON menyertakan `activity_log_id` dan `snapshot_verified: true`. Arsip bertahan ketika container aplikasi diganti, mengikuti kebijakan retensi activity log. Simpan backup penuh Coolify di luar server aplikasi.

Jalur ini tidak memakai file sementara container sebagai satu-satunya cadangan dan tidak bergantung pada status pencatatan/buffering activity log. Penulisan arsip keselamatan dilakukan langsung pada database; log keberhasilan koreksi tetap memakai mekanisme audit yang sudah ada. Maintenance, fingerprint, pemeriksaan konflik, serta pembandingan jurnal/presensi/nilai tetap berlaku pada kedua jalur.

5. Periksa hasil, daftar guru/penugasan KKM Fiqih, jurnal, presensi, nilai, dan rekap JP. Aktifkan aplikasi kembali:

```sh
php artisan up
```

Jika koreksi ditolak atau gagal, pastikan aplikasi diaktifkan kembali setelah pemeriksaan. Jangan menghapus master, kelas/mapel, penugasan, jurnal, atau riwayat KKM untuk menghindari konflik. Kirim daftar `blockers` untuk penanganan khusus.

## Status verifikasi

Database lokal yang diperiksa tidak memuat master Fiqih maupun Fiqih Ibadah. Perintah lokal berhenti tanpa perubahan. Data production belum diperiksa atau dikoreksi; hasil pratinjau dari terminal production diperlukan.

Verifikasi: 14 pengujian khusus lulus (79 assertions), ditambah 80 regresi KKM, penugasan, rekap JP, dan jurnal yang lulus. Cakupan koreksi meliputi semester lama, mapel kelas arsip, dokumen terbit yang tidak terdampak, penolakan dokumen terkunci, konflik kelas/KKM, sidik data basi, backup gagal, idempotensi, dan rollback ketika trigger mencoba mengubah jurnal. Laporan pengujian khusus tersimpan di `storage/logs/fiqih-correction-final.xml`.

Pembaruan jalur Coolify: **23 pengujian lulus, 119 assertions**, termasuk mode production tanpa S3 dengan maintenance, referensi/hash tidak lengkap, salinan database yang dirusak trigger, dan rollback jurnal pada kedua jalur. Laporan: `storage/logs/fiqih-coolify-verification.xml`.

Pengujian memakai SQLite `:memory:`. Operator telah memberikan pratinjau production tanpa konflik: enam mapel kelas, enam penugasan, 165 jurnal termasuk arsip, 148 catatan ketidakhadiran, enam set penilaian, dan tanpa scope KKM sumber. Backup penuh Coolify yang diunduh diperiksa secara lokal: CRC/ukuran gzip valid, penanda akhir dump cluster ada, tabel terkait lengkap, master mapel sesuai, dan jumlah data sumber cocok dengan pratinjau. Uji pemulihan PostgreSQL penuh serta penerapan koreksi production belum dilakukan.
