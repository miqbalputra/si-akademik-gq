# Laporan Presensi Kelas

## Cara menggunakan

- **Admin:** buka kartu **Laporan Presensi Kelas** pada Dasbor Filament atau halaman **Presensi Kelas**. Pilih semester, lalu satu kelas atau **Semua kelas**.
- **Wali kelas:** buka **Presensi → Buka Presensi → Unduh Laporan**. Laporan mengikuti kelas dan bulan yang sedang dibuka. Akses tetap membutuhkan role guru dan penugasan wali kelas yang masih berlaku pada tanggal WIB saat mengunduh.
- Pilih **Bulanan** atau **Rentang tanggal / satu hari**. Untuk satu hari, samakan tanggal awal dan akhir. Periode harus berada dalam semester kelas.
- Periksa ringkasan hari efektif, lalu pilih **Download Excel** atau **Download PDF**. Ringkasan diperbarui saat filter berubah; tombol unduh hanya aktif setelah ringkasan berhasil dimuat.

## Cara membaca angka

Hari efektif adalah Senin–Jumat dalam semester, dikurangi Libur Sekolah. Agenda Tanpa KBM tetap dihitung. Ringkasan menampilkan hari efektif sebulan, hari efektif dalam periode pilihan, serta jumlah yang sudah berlangsung sampai hari ini di Asia/Jakarta.

| Kode | Arti dan perhitungan |
| --- | --- |
| H | Hadir yang tersimpan; masuk jumlah Hadir dan pembagi. |
| H* | Dianggap Hadir karena tanggal efektif sudah berlangsung dan belum ada catatan; masuk jumlah Hadir dan pembagi. |
| S / I / A | Sakit / Izin / Alpa yang tersimpan; masuk pembagi. |
| L | Libur. L khusus yang tersimpan pada hari efektif masuk kolom L rekap, tetapi dikeluarkan dari pembagi. Libur kalender ditampilkan di rincian dan sudah dikeluarkan melalui kalender. |
| — | Belum berlangsung, di luar periode, atau tanpa catatan setelah keluar; tidak masuk pembagi. |

Persentase = jumlah Hadir ÷ hari yang dihitung untuk anak × 100%, satu angka desimal. Pembagi adalah H + S + I + A; nilai nol ditampilkan **—**. Contoh: H=4, S=1, I=1, A=1, L=1 menghasilkan pembagi 7 dan **57,1%**. H* adalah bagian dari H, bukan penambahan kedua kali.

Enrollment semester tetap disertakan meskipun tidak aktif atau profil santri diarsipkan. Status dan tanggal keluar ditampilkan. Sejak tanggal keluar, tanggal tanpa catatan tidak dianggap Hadir. Catatan yang sudah tersimpan tetap dibaca. Untuk riwayat keluar yang dipulihkan, anggapan Hadir kembali berlaku sejak tanggal pemulihan jika enrollment aktif.

Tanggal masuk kelas belum tersedia dalam model. Laporan memakai keanggotaan enrollment sepanjang semester dan mencantumkan asumsi tersebut. H* hanya nilai laporan; tidak dibuat menjadi catatan presensi.

## Format

- **Excel:** pasangan sheet Rekap/Harian untuk setiap kelas; nama sheet menyertakan ID kelas agar unik. NIS dan nama memakai tipe teks, persentase numerik memakai format `0.0%`. Rekap memiliki filter; nama/NIS dibekukan saat digulir. Harian dikelompokkan menurut bulan, dengan warna status, border, nama terbungkus, dan metadata/legenda/catatan.
- **PDF:** A4 landscape, DejaVu Sans lokal, rekap kemudian rincian harian maksimal 15 tanggal per tabel. Header tabel dan identitas santri diulang; bagian baru untuk setiap kelas dan nomor halaman berurutan untuk seluruh dokumen. Rumus dan asumsi ditempatkan sebelum rekap agar tidak menjadi halaman catatan terpisah.

## Implementasi dan akses

`ClassAttendanceReportService` membentuk satu hasil perhitungan untuk preview, Excel, dan PDF dari StudentAttendance, ClassEnrollment, SchoolHoliday, dan StudentDeparture. Query menggunakan tanggal inklusif agar catatan pada hari terakhir tetap terbaca, termasuk pada SQLite dengan cast tanggal yang menyimpan jam.

| Endpoint GET | Sesi dan akses |
| --- | --- |
| `/admin/class-attendance-reports/preview` | guard admin + role admin |
| `/admin/class-attendance-reports/export/{xlsx\|pdf}` | guard admin + role admin |
| `/attendance-reports/preview` | guard web + role guru + penugasan wali kelas aktif |
| `/attendance-reports/export/{xlsx\|pdf}` | guard web + role guru + penugasan wali kelas aktif |

Kelas/semester diverifikasi ulang pada server. Semua kelas hanya tersedia untuk admin. Respons memakai `private, no-store`. Jalur laporan portal dikecualikan dari service worker supaya browser menangani unduhan langsung.

PhpSpreadsheet dan DomPDF yang sudah terpasang tetap dipakai. Ditambahkan FPDI 2.6.8 dan FPDF 1.8.6 untuk menggabungkan bagian PDF yang dirender DomPDF secara terpisah. Tabel dibatasi 20 santri per proses render; dokumen gabungan tetap satu file. Pendekatan ini menghindari render seluruh kelas sekaligus yang melampaui batas memori PHP 128 MB pada contoh kelas besar. Font/tabel tidak dikecilkan untuk memaksakan satu halaman. Library lain dalam composer.lock tidak diperbarui.

Tidak ada migrasi, perubahan model bisnis, penyimpanan H*, perubahan presensi/jurnal, atau sinkronisasi rapor. Dua perbaikan markup pada halaman presensi menghapus penutup main berlebih dan teks CSS yang terselip dalam ekspresi Alpine; input dan penyimpanan otomatis tetap memakai endpoint yang sudah ada.

## Verifikasi lokal — 13 Oktober 2026

- **75 pengujian / 444 assertions lulus** pada SQLite `:memory:`: akses guard/role, kelas lain, assignment kedaluwarsa, batas WIB, bulanan/satu tanggal/rentang, semester, libur, Tanpa KBM, L, tanggal mendatang, pembagi nol, arsip, NIS nol awal, teks diawali `=`, semua kelas, widget dan PDF beberapa halaman. Regresi mencakup autentikasi, workspace, presensi, jurnal/nilai, arsip santri, impor, tipografi, dan PWA.
- SQL listener memastikan preview dan kedua format ekspor tidak menulis tabel bisnis.
- Build Vite, kompilasi Blade, lint PHP, dan Pint berhasil; `git diff --check` bersih.
- Browser memakai database contoh terisolasi: admin dashboard/Presensi Kelas dan portal wali kelas, viewport desktop 1440px, tablet 768px, ponsel 390px, light/dark. Kontrol laporan tidak menambah overflow halaman. Tombol Excel/PDF menghasilkan unduhan nyata; perubahan status presensi pada data contoh tersimpan otomatis, kemudian dikembalikan. Konsol halaman presensi bersih setelah perbaikan ekspresi Alpine.
- Contoh dua kelas dengan 57 santri, termasuk nama panjang dan santri arsip, menghasilkan workbook empat sheet dan PDF 27 halaman pada batas PHP 128 MB. Render Excel dan PDF ditinjau untuk nama, angka, status, border, header berulang, dan nomor halaman.
- SHA256 database lokal asli sebelum/sesudah pekerjaan: `5B27A4802CD7D98752951DF103A36749502FB5BE592F67F21E611124F4294993`. Production tidak diakses selama implementasi dan pengujian.

Perubahan membutuhkan pemasangan dependency sesuai composer.lock saat build deployment biasa. Fitur ini tidak membutuhkan migrasi baru. Status push/redeploy dilaporkan terpisah pada saat dilakukan.
