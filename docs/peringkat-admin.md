# Peringkat semua kelas untuk admin

Menu **Panel Admin → Leger & Rapor → Peringkat Semua Kelas** membuka `/admin/peringkat-kelas`. Halaman ini khusus akun dengan peran admin pada sesi admin; tidak memerlukan penugasan guru/wali kelas. Hak akses wali kelas pada endpoint Leger sebelumnya tetap berlaku dan tidak diperluas.

Pilih periode akademik dan **Semua kelas** atau satu kelas, lalu klik **Tampilkan peringkat**. Periode awal mengikuti periode aktif terbaru pada sistem. Opsi **Sertakan santri arsip** tersedia untuk pemeriksaan data historis; secara default hanya santri berstatus aktif ditampilkan. Nomor peringkat tersimpan dipertahankan, sehingga dapat ada jeda ketika santri arsip disembunyikan.

Tabel setiap kelas menampilkan peringkat, nama/NIS, total Diniyyah, total Tahfidz, total akhir, rata-rata dan kelengkapan. Pengurutan memakai peringkat yang tersimpan; peserta tanpa peringkat diletakkan terakhir. Draf diberi tanda **DRAF — belum final**, leger terkunci/terbit bertanda **FINAL**, dan kelas tanpa leger tetap muncul dengan keterangan belum dibuat.

## Sumber nilai dan keamanan data

- Fitur ini hanya membaca `diniyyah_ledger_snapshots`, baris, dan sel tersimpannya. Tidak membuat/mengunci leger, menghitung ulang peringkat, mengubah nilai, atau menimpa rapor.
- Untuk `combined-v1`, total akhir/rata-rata/peringkat memakai `combined_total`, `combined_average`, `combined_rank`. Untuk leger lama, nilai berasal dari kolom total/rata-rata/peringkat Diniyyah yang tersimpan; jenis leger dinyatakan pada setiap kelas.
- Total Diniyyah mengikuti nilai tersimpan pada baris. Total Tahfidz merupakan penjumlahan empat nilai tersimpan: UAS, Sabaq, Sabqi, Manzil; hanya tersedia bila keempatnya ada. Juz dan presensi tidak dijumlahkan. Total akhir selalu memakai angka Leger, bukan dihitung ulang dari subtotal pada halaman ini.
- Kosong ditampilkan sebagai **—**, tidak diubah menjadi nol. Angka nol yang memang tersimpan tetap ditampilkan sebagai nol. Nama/NIS berasal dari salinan identitas saat Leger dibuat.
- Semua baris tetap tampil walaupun ringkasan nilainya belum lengkap; kelengkapan memerlukan total, rata-rata dan peringkat tersimpan.
- Data setelah Leger disusun belum tercermin sampai wali kelas menyegarkan Leger yang masih terbuka. Halaman ini selalu memperlihatkan waktu penyusunan dan status sumber.
- Setiap endpoint tampilan/unduhan memeriksa sesi dan peran admin. Filter kelas divalidasi berada pada periode yang dipilih. Berkas unduhan bersifat privat (`private, no-store`), dan tindakan ekspor dicatat di log aktivitas.
- Tidak ada migrasi database atau perubahan pada data lama untuk fitur ini.

## Unduhan

**Excel:** unduhan semua kelas terdiri dari sheet ringkasan dan satu sheet per kelas. Unduhan satu kelas berisi satu sheet. Nama sheet dibatasi dan disanitasi agar valid, nama/NIS ditulis sebagai teks (termasuk angka nol di depan dan nama yang diawali `=`), sedangkan nilai tetap numerik. Ada filter tabel, pembekuan kolom identitas, pembungkusan teks, judul berulang, warna berbeda untuk data belum lengkap, dan pengaturan cetak Folio/F4 landscape satu halaman lebar. Tinggi cetak boleh lebih dari satu halaman sehingga teks tidak dipaksa terlalu kecil.

**PDF:** Folio/F4 landscape, kelas dipisahkan ke halaman baru. Kelas panjang dapat berlanjut ke halaman berikutnya; judul kolom tabel berulang. Identitas kelas/periode, status sumber, tanggal data dan nomor halaman disertakan. Filter tampilan yang dipilih juga berlaku pada Excel dan PDF. Tombol unduh per kelas tersedia pada tampilan semua kelas.

## Komponen dan pengujian

`ClassRankingReport` membentuk data baca saja; `ClassRankingExporter` menangani Excel; `AdminClassRankingController` memeriksa filter/izin dan menyajikan tampilan/PDF. View berada di `resources/views/admin/class-rankings/`. Navigasi panel didaftarkan melalui `AdminPanelProvider`.

```powershell
php artisan test --compact --filter='AdminClassRankingTest|DiniyyahLedgerExportTest|DiniyyahLedgerPresentationTest'
php vendor/bin/pint --dirty --test
npm run build
```

Pengujian memakai SQLite `:memory:` dengan penolakan eksplisit jika koneksi mengarah ke database operasional. Cakupan meliputi akses admin lintas kelas, penolakan peran lain, pemisahan periode, angka gabungan vs leger lama, peserta arsip/tidak lengkap, tipe nilai/NIS Excel, pemisahan halaman PDF, dan tidak berubahnya sumber nilai setelah ekspor.
