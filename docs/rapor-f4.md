# Rapor otomatis dari Legger

## Penggunaan oleh wali kelas

1. Buka **Leger Kelas Saya**, periksa kelengkapan nilai Diniyyah, praktik, Tahfidz, KKM, dan presensi.
2. Klik **Kunci leger final**. Penguncian leger gabungan sekaligus membuat rapor santri aktif yang belum memiliki rapor. Keduanya berada dalam transaksi: kegagalan pembuatan rapor membatalkan penguncian dan seluruh penambahan dalam transaksi tersebut.
3. Buka **Rapor Kelas Saya** atau **Rapor kelas · F4** dari halaman leger.
4. Pilih **Tinjau rapor**. Tanggal ditentukan admin melalui **Data Sekolah → Periode → Tanggal Rapor**, wali kelas dari penugasan aktif, nama kepala/tempat penerbitan dari konfigurasi. Wali kelas dapat melengkapi catatan dan identitas tanda tangan, tetapi tidak mengubah tanggal, nilai atau KKM.
5. Untuk semester genap, lengkapi keputusan naik kelas, tinggal kelas, atau lulus. Kelas tujuan wajib diisi jika naik kelas. Keputusan ini sengaja tidak ditebak dari nilai.
6. Saat seluruh pelengkap tersedia, pilih **Cetak F4** atau **Unduh PDF F4**. Cetak/unduh seluruh kelas tersedia jika semua santri mempunyai rapor dan semua rapor siap.

Leger yang sudah terkunci sebelum fitur ini dipasang dapat diproses dengan **Buat rapor yang belum ada**. Tombol ini tidak menyegarkan rapor yang telah ada, termasuk draf lama.

Wali kelas hanya mendapat akses untuk kelas dan periode pada penugasan yang aktif. Penugasan yang berakhir mencabut akses. Orang tua tetap hanya dapat melihat rapor anak yang telah terbit; mereka tidak mendapat akses formulir pelengkap.

## Data lama

- Migrasi tambahan hanya menambahkan kolom nullable `academic_terms.report_issue_date` dan tabel baru `tahfidz_report_entries`. Tidak ada perintah mengubah/menghapus nilai lama, KKM, presensi, rapor atau backfill data.
- Semua jalur pembuatan dari portal, panel admin, dan job antrean memakai `ReportCardAutomation::createMissing()`. Rapor lama dilewati tanpa perubahan, apa pun statusnya.
- Lembar Excel sumber tidak disalin ke aplikasi. Layout JSON tidak berisi nilai contoh, formula Excel, identitas santri, atau nama wali kelas contoh. Logo dan gambar tanda tangan berasal dari template yang diberikan.
- Payload cetak tersimpan di `report_card_snapshots.snapshot_data.print`; nilai, KKM/revisi KKM, rata-rata kelas, Tahfidz, presensi dan identitas cetak dibekukan. Membuka/mencetak ulang rapor tidak membaca nilai terkini dari tabel sumber.
- Penyimpanan pelengkap membuat snapshot baru dan log aktivitas, dengan tetap menyimpan semua snapshot sebelumnya. Rapor terkunci/terbit menolak perubahan pelengkap.
- Rapor lama yang belum memiliki payload F4 menggunakan tampilan/cetakan arsip. PDF yang tersimpan tetap tersedia. Sistem tidak mengubahnya diam-diam menjadi format baru.
- Koreksi rapor terbit melalui **Buka revisi**, dengan alasan tercatat. Pengelola kemudian dapat memilih **Perbarui Nilai Revisi dari Leger**. Ini tindakan khusus untuk revisi; proses otomatis biasa tidak menimpa rapor. Pelengkap manual dan snapshot lama tetap disimpan.

Pengujian menggunakan SQLite `:memory:`. `Tests/TestCase.php` menolak menjalankan tes jika konfigurasi mengarah ke database aplikasi. Database operasional tidak dipakai untuk pengujian.

## Pemetaan template

Acuan: `SAMPLE RAPOR KELAS 5 AKHWAT - TA 2025-2026.xlsx`, SHA-256:

`621243eacbd22eb32270cb2f024a3604164021142f7991230bcff47f93f93e9f`

| Bagian | Sumber sistem | Penempatan template |
| --- | --- | --- |
| Semester ganjil | `academic_terms.semester = ganjil` | `CETAK SMT1`, area asli A4:Q56 |
| Semester genap | `academic_terms.semester = genap` | `CETAK SMT2`, area asli A4:Q58 |
| Nama/NIS/kelas/periode | Salinan identitas pada leger dan periode | D8, D9, D10, Q8, Q9 |
| Mapel dan nama Arab | Metadata penugasan/set penilaian yang disalin ke kolom leger | B16, E16 dan baris berikutnya |
| KKM | KKM beserta revisi yang tersimpan pada leger | H16 dan baris berikutnya |
| Nilai dan terbilang | Nilai sel leger, dibulatkan satu desimal | I16, J16 dan baris berikutnya |
| Rata-rata kelas per mapel | Rata-rata nilai tersimpan untuk mapel yang sama di leger tersebut | Q16 dan baris berikutnya |
| Rata-rata Diniyyah | Rata-rata nilai mapel reguler yang tampil, termasuk Suluk; praktik terpisah | I22, dua desimal |
| UAS Tahfidz/juz | `tahfidz_juz`, `tahfidz_uas` dari sel leger gabungan | A26, J26 |
| Harian Tahfidz | `tahfidz_sabaq`, `tahfidz_sabqi`, `tahfidz_manzil` | J28:J30 |
| Ujian praktik | Penugasan bermetode `practical` atau mapel praktik, beserta materi ujian | A33, J33; baris bertambah jika lebih dari satu |
| Jumlah dan peringkat | `combined_total` dan `combined_rank` yang sudah tersimpan di leger | J35 dan kalimat A36 |
| Presensi | `attendance_sick`, `attendance_permission`, `attendance_absent` pada leger | F39:F41 |
| Keputusan kenaikan | Pelengkap yang diputuskan wali kelas | N42/N43, hanya semester genap |
| Tanggal/tanda tangan | Pelengkap cetak yang dibekukan | Posisi berbeda mengikuti masing-masing sheet |

Flag tampil di rapor pada penugasan dan set penilaian disalin ke leger baru dan dihormati saat membentuk rapor. Tidak ada daftar tetap enam mapel maupun KKM kelas 5 yang ditanam di generator. Enam baris pada template adalah tata letak dasar, bukan pembatas penugasan.

Baris mapel/praktik bertambah sesuai data. Nama mapel, materi ujian atau catatan panjang dapat mempertinggi baris; bagian berikutnya dan tanda tangan bergeser bersama. Jika konten memerlukan skala kurang dari 80% skala dasar template, sistem meminta versi layout yang lebih panjang dan tidak menyatakan rapor siap cetak.

Formula template asli di A36: ganjil menyebut peringkat 1–2; genap peringkat 1–3. Sesuai permintaan terbaru, payload **rapor baru** bertanda `note_policy=top-three-v2` menyebut peringkat 1, 2, dan 3 pada kedua semester. Peringkat lainnya mendapat kalimat penyelesaian KBM. Payload lama tetap mengikuti aturan tersimpan, tanpa mengubah catatan yang sudah diisi. Peringkat dan jumlah mengikuti Legger final, tanpa menghitung ulang peringkat saat mencetak. Tahun ajaran rapor baru dibaca dari periode kelas dan dinormalisasi menjadi `YYYY/YYYY`; semester cetak tetap `1 (Satu)` atau `2 (Dua)`.

## Nilai final Tahfidz dan pemeriksaan wali kelas

- Menu **Nilai Final Tahfidz Rapor** tersedia untuk guru aktif yang gendernya sudah dilengkapi. Ustadz hanya melihat/mengisi santri laki-laki; ustadzah hanya santri perempuan. Pemeriksaan juga dilakukan di server untuk URL/POST langsung; peran tambahan admin tidak menjadi pengecualian untuk jalur input guru.
- Isian: juz yang diujikan, nilai ujian, Sabaq, Sabqi, Manzil. Nilai dibatasi 0–100. Draf boleh belum lengkap; final wajib lengkap. Angka nol sah dan tidak dianggap kosong.
- Semua penyimpanan menghasilkan **versi baru** di tabel terpisah. Nilai pada tabel lama `tahfidz_uas_results` dan `tahfidz_semester_recaps` tidak ditimpa. Formulir lama yang sudah tertinggal versi ditolak agar tidak menimpa isian guru lain.
- Wali kelas membuka **Periksa isian Tahfidz** dari Leger atau Rapor Kelas Saya. Seluruh santri aktif di kelas terlihat, termasuk yang belum diisi, masih draf, final menunggu pemeriksaan, dan sudah diperiksa. Nama guru pengisi ditampilkan. Guru yang bukan wali kelas tersebut tidak dapat memeriksa.
- Tombol **Sudah diperiksa** mencatat wali kelas serta waktunya pada versi baru. Setelah ada isian final khusus rapor, hanya versi terakhir yang sudah diperiksa yang boleh masuk Legger. Penyuntingan berikutnya membatalkan kesiapan pemeriksaan pada versi terbaru.
- Data lama yang sudah lengkap dan tervalidasi tetap dapat digunakan ketika belum ada isian final rapor baru, tanpa memaksa guru menyalin ulang atau mengubah data lama.
- Sesudah pemeriksaan, wali kelas **menyegarkan leger**, kemudian menguncinya. Perubahan nilai atau pemeriksaan setelah leger disegarkan membuat fingerprint berubah sehingga leger harus disegarkan lagi. Leger/rapor terkunci menolak isian final baru untuk kelas itu.
- Ketidakhadiran tetap berasal dari `student_attendances` aplikasi (Sakit/Izin/Alpa), direkap menurut periode dan enrollment, lalu dibekukan pada leger final. Perubahan presensi sebelum penguncian terdeteksi dan meminta penyegaran. Rapor lama tidak diganti dengan rekap live yang berbeda ketika dicetak ulang.

Tanggal rapor bukan tanggal akhir periode. Admin menetapkannya sendiri pada pengaturan periode; tanpa tanggal ini rapor baru belum siap cetak. Draf baru mengikuti pengaturan admin, lalu tanggal dibekukan saat rapor dikunci. Rapor lama tanpa `date_policy=admin-period-v1`, dan semua rapor yang sudah terkunci/terbit, mempertahankan tanggal tersimpan. Menyimpan pelengkap lain tidak mengubah tanggal arsip.

### Temuan formula Excel

Pada `NILAI SMT2!AB3`, formula `SUM(G3,H3,I3,J3,L3,V3,W3,X3,Y3,Z3,AA3)` melewatkan kolom K (Tajwid). Untuk baris contoh pertama, total tersimpan adalah 825,3; penjumlahan seluruh nilai yang ditampilkan menghasilkan 912,7.

Excel sumber tidak diubah. Sistem tidak menyalin formula yang keliru: hasil cetak baru memakai `combined_total` dari leger. Contoh PDF untuk perbandingan visual sengaja mempertahankan angka cache pada Excel sumber; ini pratinjau template, bukan hasil penilaian operasional.

## Ukuran dan cetak

Page setup Excel memakai `paperSize=14` (Folio), setara 8,5 × 13 inci / 215,9 × 330,2 mm. PDF terverifikasi mempunyai MediaBox 612 × 936 pt, satu halaman per santri. Skala dasar mengikuti sheet: 106% untuk SMT1 dan 103% untuk SMT2. Margin sumber kiri/kanan 6 mm, atas 9 mm.

Pada dialog printer pilih **Folio/F4 8,5 × 13 inci**, skala **100% / actual size**, dan matikan header/footer browser. Jangan memilih A4 atau Legal. Mesin cetak yang menamai F4 sebagai 210 × 330 mm perlu ukuran kertas khusus 215,9 × 330,2 mm agar mengikuti template ini.

Layout, warna, ukuran sel, garis, tipografi, gambar dan posisi diambil dari sheet asli. Teks nasihat dibuat membungkus agar tidak keluar dari bingkainya. Hasil lokal kedua semester telah dirender dan ditinjau secara visual. Kesamaan piksel dengan ekspor Microsoft Excel dan hasil fisik printer belum diuji. Font pengganti pada server Linux dapat mengubah bentuk huruf; image Docker memasang Carlito, Liberation dan font Arab. Jika harus identik dengan font Microsoft pada template (Calibri/Ebrima), server perlu font tersebut dengan lisensi yang sesuai.

Penyesuaian tampilan cetak: area rapor dipusatkan secara horizontal menggunakan lebar **setelah skala cetak**, sehingga margin kiri dan kanan seimbang pada kedua semester maupun saat jumlah mapel bertambah. Logo cetak memakai aset PNG transparan `griya-quran-transparent.png`, melalui konfigurasi `report-cards.logo_file`; gambar hasil ekstraksi Excel tetap disimpan sebagai sumber asli.

Gambar tanda tangan kepala dari template hanya tampil jika nama kepala sama dengan nama penandatangan pada template. Mengganti nama kepala menyembunyikan gambar tersebut sehingga tanda tangan milik orang lama tidak melekat pada nama baru.

## Struktur implementasi

| Berkas/komponen | Tanggung jawab |
| --- | --- |
| `DiniyyahLedgerGenerator` | Membekukan metadata penugasan, flag tampil, nama Arab, nilai dan KKM pada leger |
| `DiniyyahLedgerWorkflow` | Penguncian leger serta pemicu pembuatan rapor dalam transaksi yang sama |
| `ReportCardAutomation` | Pembuatan idempoten tanpa menimpa data; jalur khusus penyegaran revisi |
| `ReportCardGenerator` | Menyalin baris leger ke rapor dan snapshot dengan penguncian database |
| `ReportCardTemplateData` | Menyusun payload cetak, rata-rata reguler/kelas, terbilang dan pemeriksaan pelengkap |
| `ReportCardTemplateRenderer` | Memetakan payload ke geometri sheet sesuai semester |
| `ReportCardPrintOptions` | Menyimpan pelengkap dengan snapshot baru dan audit |
| `ReportCardPrintService` | Pratinjau, kesiapan dan PDF Chromium untuk satu/seluruh kelas |
| `TahfidzReportWorkflow` | Input final berdasarkan gender, kontrol versi dan pemeriksaan wali kelas |
| `TahfidzReportSources` | Memilih versi final yang diperiksa atau sumber lama tervalidasi, tanpa menulis ke sumber lama |
| `TahfidzReportController` | Daftar input guru dan monitoring semua santri kelas untuk wali kelas |
| `ReportCardPolicy` + `DiniyyahLedgerAccess` | Izin per penugasan wali kelas, pengelola dan orang tua |
| `resources/report-cards/layout-v1.json` dan PNG | Layout tersanitasi serta gambar template asli |
| `resources/views/report-cards/template-print.blade.php` | Satu layout HTML untuk cetak browser dan PDF |
| `config/report-cards.php` | Ukuran kertas, default penerbitan, versi layout dan lokasi Chromium |
| `tests/Feature/ReportCardF4Test.php` | Integrasi otomatisasi, arsip, izin, pelengkap, ekspansi layout dan PDF |

## Pemasangan dan pemeliharaan

Migrasi yang diperlukan saat deployment: `2026_11_17_120000_add_report_input_settings.php`. Jalankan melalui prosedur deployment/backup proyek yang sudah ada, sebelum kode baru melayani request; lalu admin mengisi tanggal rapor pada periode yang digunakan. Migrasi ini diuji pada SQLite dalam memori. Database operasional tidak dimigrasikan atau diubah selama pekerjaan implementasi lokal ini. Jangan menjalankan `migrate:fresh`, rollback, atau seeder ulang pada database yang berisi data sekolah.

Rollback migrasi ini juga menolak berjalan jika tabel isian final atau tanggal rapor sudah terisi, agar data fitur baru tidak ikut terhapus ketika kode dikembalikan.

Deploy kode melalui prosedur proyek yang sudah ada; rebuild image Docker agar font tambahan tersedia, muat ulang worker antrean/Octane dan cache konfigurasi sesuai prosedur deployment.

Konfigurasi opsional terdapat pada `.env.example`: `REPORT_CARD_CHROMIUM_BINARY`, `REPORT_CARD_ISSUE_PLACE`, `REPORT_CARD_PRINCIPAL_NAME`, `REPORT_CARD_PRINCIPAL_TITLE`. Chromium dideteksi otomatis pada Docker dan instalasi Chrome/Edge Windows. PDF memakai direktori sementara acak dan dibersihkan sesudah render. Berkas PDF bersifat privat.

Layout versi lama harus tetap disimpan. Untuk mempelajari template pengganti:

```powershell
php tools/report-cards/extract-template.php 'lokasi-template-baru.xlsx' 'direktori-keluaran-baru'
```

Extractor membaca Excel tanpa mengubahnya dan menolak menimpa layout yang sudah ada. Buat versi renderer/layout baru untuk perubahan bentuk; jangan mengganti `layout-v1.json` yang dipakai arsip. Sesuaikan pemetaan posisi jika struktur sheet baru berbeda, lalu verifikasi kedua semester serta kelas dengan jumlah mapel dan praktik berbeda.

Pemeriksaan terkait:

```powershell
php artisan test --compact --filter='ReportCard|Tahfidz|DiniyyahLedger|DiniyyahKkm|AttendanceRecap'
php vendor/bin/pint --dirty --test
```

Rujukan pustaka yang diperiksa: [transaksi dan penguncian Laravel 13](https://github.com/laravel/docs/blob/13.x/queries.md), [pembacaan template PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/docs/topics/reading-and-writing-to-file.md), [font Carlito Alpine](https://pkgs.alpinelinux.org/package/v3.23/community/armhf/font-carlito).
