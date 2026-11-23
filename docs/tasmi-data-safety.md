# Pencatatan Tasmi dan keamanan data

- Akses input mengikuti penugasan PJ Tasmi aktif pada periode aktif. Guru tanpa penugasan tidak memiliki menu input Tasmi. Kelas yang tersedia mengikuti gender guru.
- Predikat Tasmi (`maqbul`, `jayyid`, `jayyid_jiddan`, `mumtaz`) adalah teks. Jangan memasukkannya ke `score_change_logs.old_score/new_score`, karena kedua kolom tersebut bertipe DECIMAL untuk nilai numerik.
- Kejadian pembuatan/perubahan tetap dicatat di `score_change_logs` dengan kolom angka kosong. Predikat awal tersimpan di `activity_log.properties.attributes`, sedangkan perubahan predikat dan data ujian tersimpan di `activity_log.properties.changes`. Riwayat lama tidak dikonversi atau ditimpa.
- Input, edit, dan soft delete melalui portal guru memakai transaksi agar hasil ujian, audit, dan notifikasi tersimpan atau dibatalkan bersama. Bentrok kunci unik dikembalikan sebagai pesan validasi tanpa menimpa hasil lain.
- Filter juz mencakup rentang naik maupun turun, termasuk Juz 30 sampai 26. Pengelompokan kondisi filter tetap mempertahankan batas akses penguji/wali dan filter lain.
- Perbaikan audit awal tidak mengonversi atau menjalankan perubahan massal pada data lama. Alur persetujuan berikut memakai migrasi tambahan khusus metadata.

## Persetujuan wali kelas (23 November 2026)

- Input baru berstatus `pending`. Hanya wali kelas yang memiliki penugasan untuk kelas dan tanggal ujian yang bisa memeriksa hasil. Batas yang sama berlaku pada daftar, detail, popup, tindakan persetujuan, dan ekspor wali kelas.
- Wali kelas dapat menyetujui atau meminta perbaikan lewat popup dengan alasan wajib (maksimal 2.000 karakter). PJ menerima notifikasi dan popup alasan perbaikan pada dashboard. Setelah diperbaiki, status kembali menunggu pemeriksaan wali kelas.
- Popup dan banner wali mengikuti status hasil, bukan status dibaca/arsip notifikasi. Menutup popup hanya menyembunyikannya sementara; banner tetap ada selama masih ada hasil yang belum disetujui. Perubahan hasil memunculkan popup lagi, termasuk perubahan dalam detik yang sama.
- Perubahan hasil, identitas santri/kelas, atau label hari membatalkan persetujuan. Santri/kelas pengganti harus berasal dari enrollment aktif pada semester yang sama dan gender kelas yang sesuai PJ. Catatan tetap memakai ID hasil yang sama; riwayat perubahan tersimpan.
- Persetujuan memakai transaksi, penguncian baris, dan nomor versi agar wali tidak menyetujui data yang sudah diubah. Form edit PJ dan admin juga memeriksa versi. Persetujuan/perbaikan dicatat pada audit tanpa memasukkan teks ke kolom DECIMAL.
- Admin/Kabag masih dapat melihat seluruh status dalam laporan pengawasan. **Unduhan admin/Kabag (Excel dan PDF) hanya berisi hasil yang disetujui wali kelas**, untuk proses piagam. Unduhan riwayat PJ/wali tetap memuat status pemeriksaan sehingga hasil yang belum disetujui tidak disalahartikan sebagai hasil final.
- PJ dengan riwayat penugasan dapat memperbaiki hasilnya pada semester tersebut meskipun penugasan sudah tidak aktif. Input hasil baru tetap membutuhkan penugasan aktif.

## Keamanan data dan pemasangan

Statistik admin tersedia melalui **Rekap & Ekspor Tasmi** atau tombol **Statistik & unduh Tasmi'** di daftar Hasil Tasmi. Jumlah hasil yang diinput, menunggu persetujuan, disetujui/siap unduh, dan perlu perbaikan dipisahkan menurut gender kelas (Ikhwan/Akhwat). Kelas campuran atau kelas yang belum tercatat ditampilkan terpisah agar hasil lama tidak hilang dari total. Status lama `NULL` masuk hitungan menunggu persetujuan. Angka menghitung hasil, bukan santri unik, dan mengikuti filter laporan selain status persetujuan; klik angka untuk menyaring rincian. Filter kelompok kelas juga berlaku pada unduhan yang tetap hanya memuat hasil disetujui. Statistik tidak menulis atau mengubah data dan tidak memerlukan migrasi tambahan.

Verifikasi tambahan statistik: 70 pengujian PHP terkait Tasmi, navigasi admin, dan pemilihan workspace lulus (1.277 assertions). Pemeriksaan khusus statistik, akses, filter unduhan dan PDF juga lulus; angka serta tautan status diperiksa di browser pada lebar 1.440 px dan 390 px tanpa overflow halaman. Build aset dan pemeriksaan format lulus. Uji statistik membandingkan seluruh hasil termasuk data lama/soft delete sebelum dan sesudah pembacaan dan memastikan tidak ada perubahan data atau audit.

Migrasi `2026_11_23_000001_add_wali_review_to_tasmi_records.php` hanya menambahkan status, versi, pemeriksa, waktu pemeriksaan, dan alasan perbaikan. Tidak mengubah nilai/predikat, tanggal, identitas, soft delete, maupun audit lama. Status hasil lama tetap `NULL`, ditampilkan sebagai **Data lama — belum diverifikasi**; persetujuan tidak dibuat otomatis. Data lama tetap dapat dilihat dan diperiksa sebelum diunduh admin.

Sebelum memasang di server, buat dan verifikasi backup database. Jalankan migrasi tambahan berikut, lalu build aset dan bersihkan cache tampilan sesuai prosedur deployment proyek:

```sh
php artisan migrate --path=database/migrations/2026_11_23_000001_add_wali_review_to_tasmi_records.php --force
npm run build
php artisan view:clear
```

Jangan menjalankan `migrate:fresh`, reset database, atau seeder untuk memasang perubahan ini. Uji migrasi membandingkan seluruh kolom asli serta audit sebelum/sesudah migrasi, termasuk hasil yang sudah di-soft-delete. Uji transaksi juga memastikan kegagalan notifikasi membatalkan persetujuan dan audit secara utuh.

Verifikasi: `php artisan test --compact --filter='TasmiFeatureTest|TasmiReportFeatureTest|NotificationDispatchTest|DiniyyahScoreAuditTest|StudentIdentityEditTest'`.

Hasil verifikasi lokal perubahan ini: 82 pengujian PHP lulus (578 assertions), 4 pengujian JavaScript navigasi lulus, serta 8 pemeriksaan tampilan/interaksi pada lebar desktop 1.440 px dan ponsel 390 px lulus tanpa error JavaScript. Build aset, kompilasi Blade, dan pemeriksaan format juga lulus. Pemeriksaan browser memakai hasil render dari data sintetis SQLite `:memory:`.

Migrasi lokal diterapkan setelah backup SQLite dengan pemeriksaan integritas. Isi 94 tabel, termasuk seluruh kolom asli, dibandingkan dan tetap sama setelah migrasi (selain penambahan entri pada tabel migrasi). Database lokal belum memuat hasil Tasmi'; preservasi hasil lama diuji terpisah menggunakan data sintetis, termasuk soft delete dan audit. Catatan ini tidak menyatakan bahwa migrasi atau pemeriksaan data telah dijalankan di server produksi.

Pengujian wajib memakai SQLite `:memory:`. Karena SQLite menerima teks pada kolom DECIMAL, pengujian Tasmi memasang trigger khusus dalam database pengujian untuk menolak teks pada kolom angka. Pengujian juga memaksakan kegagalan audit untuk memeriksa rollback dan membandingkan data sebelum/sesudahnya.

Jumlah hasil kosong tidak membuktikan guru lupa mengisi. Bedakan penugasan yang belum dibuat, hasil yang belum tersimpan, dan kegagalan sistem berdasarkan bukti; pemeriksaan ini tidak merekam riwayat percobaan input yang tidak pernah mencapai server.
