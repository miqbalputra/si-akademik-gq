# Verifikasi jurnal dihapus lalu diisi ulang — 29 Oktober 2026

Laporan menyebut jurnal Fiqih Mustawa 5 Akhwat untuk 26 Oktober: setelah dihapus, pengisian ulang ditolak dengan pesan bahwa jurnal sudah pernah diisi.

## Hasil pemeriksaan

Bug berhasil direproduksi melalui alur HTTP pada empat jenis jurnal: reguler, pengganti, Tafsir, dan pengganti Tafsir. Penghapusan memakai `SoftDeletes`, sehingga jurnal dan absensi lama tetap menjadi arsip. Indeks unik lama berlaku untuk semua baris, termasuk arsip; akibatnya formulir melihat slot kosong, tetapi database menolak jurnal baru pada slot yang sama.

Alur edit normal untuk kelas/mapel/tanggal dalam laporan dapat dijalankan dalam pengujian lokal. Penyebab kegagalan edit yang dialami pelapor belum dapat dipastikan tanpa detail request/log server. Pemeriksaan tambahan menemukan bahwa kegagalan menyimpan absensi saat edit dapat meninggalkan materi berubah dan absensi lama terhapus; kondisi ini juga direproduksi sebelum diperbaiki.

## Perbaikan dan perlindungan data

- Migrasi baru `2026_10_29_000001_limit_journal_slot_uniqueness_to_active_records` mengganti indeks lama dengan indeks unik hanya untuk jurnal aktif (`deleted_at IS NULL`). Indeks pengganti dibangun sebelum indeks lama dilepas.
- Migrasi tidak menghapus atau menulis ulang jurnal, absensi, maupun status arsip. Jurnal baru memakai ID baru; arsip beserta absensinya tetap tersimpan utuh.
- Duplikat jurnal aktif tetap ditolak oleh database. Aturan berlaku bagi keempat jalur pengisian jurnal.
- Pemulihan arsip ditolak dengan pesan validasi bila slot sudah terisi jurnal aktif, sehingga tidak menimpa jurnal pengganti arsip tersebut. Pemulihan saat slot kosong tetap diperbolehkan.
- Rollback migrasi ditolak bila indeks lama akan berbenturan dengan riwayat. Rollback tidak menghapus arsip untuk memaksa keberhasilan.
- Perubahan materi dan sinkronisasi absensi pada edit dibungkus satu transaksi; kegagalan mengembalikan seluruh data semula. Catatan santri yang sudah tidak aktif tetap dipertahankan.

## Validasi dan penerapan

112 pengujian terkait lulus (532 assertions), meliputi hapus/isi ulang berulang, keempat menu jurnal, penolakan duplikat aktif, edit, pemulihan arsip, pelestarian seluruh isi tabel jurnal/absensi saat migrasi, rollback yang aman, jadwal, pengingat jurnal, rekap JP, nilai, dan notifikasi. Pengujian memakai SQLite `:memory:` dan tidak menyentuh database aplikasi.

Migrasi ditujukan untuk PostgreSQL production dan SQLite lokal/pengujian. Bentuk lama PostgreSQL berupa indeks mandiri maupun UNIQUE constraint ditangani. DDL PostgreSQL diperiksa terhadap dokumentasi resmi; belum diuji terhadap instance PostgreSQL dalam pekerjaan ini. Driver lain ditolak sebelum perubahan indeks.

Saat rollout, migrasi indeks baru wajib diterapkan agar slot arsip bisa diisi ulang. Push kode bukan bukti bahwa deployment production sudah berhasil; status deploy, backup terbaru, dan migrasi pending production belum diverifikasi dalam pekerjaan ini. Pastikan backup production tersedia dan periksa migrasi pending sebelum deploy, karena entrypoint menjalankan migrasi pending otomatis. Jangan menjalankan ulang migrasi indeks lama Juli 2026 yang memiliki langkah penghapusan duplikat.
