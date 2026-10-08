# Tipografi TailAdmin untuk Ruang GQ

Pemeriksaan: 9 Oktober 2026.

## Implementasi

- Seluruh layar web Blade memakai Outfit melalui layout/partial font bersama. Theme Filament 5 memakai `LocalFontProvider` dengan stylesheet Outfit yang sama.
- Aset Outfit variable v15, subset Latin dan Latin Extended, disimpan di `public/fonts/outfit/` bersama lisensi SIL OFL. Aset ini merupakan berkas yang digunakan stylesheet Google Fonts pada acuan TailAdmin; browser aplikasi memuatnya dari server aplikasi sendiri.
- Token TailAdmin `title-*`, `theme-sm`, dan `theme-xs` serta kelas berdasarkan fungsi komponen tersedia di `resources/css/ui-typography.css`. Portal dan theme Filament mengimpor berkas ini.
- Judul, kartu, ringkasan, form, tabel, badge, sidebar, dropdown, modal, autentikasi, pemulihan sandi, pemilihan ruang kerja, halaman publik/error, dan halaman offline telah diselaraskan. Label antarmuka memakai Outfit; konten kode JSON tetap dapat memakai monospace.
- Konfigurasi build Instrument Sans dihapus. Tidak ada permintaan font Google Fonts atau Bunny Fonts dari konfigurasi aplikasi baru.
- Chart.js pada portal serta instance Chart.js milik Filament memakai Outfit dan memperbarui gambar setelah font siap. Label grafik portal Tahfidz yang sebelumnya 10–11px menjadi 12px.
- Font lokal dicache sebagai aset statis PWA. Versi cache diperbarui; aturan cache untuk halaman privat dan API tetap.
- Kalender admin memakai area gulir mendatar saat ruang sempit, sehingga ukuran teks baru tidak memotong kolom tanggal. Area kalender dapat menerima fokus keyboard.

## Nilai komponen yang diperiksa

| Komponen | Ukuran / tinggi baris | Ketebalan |
| --- | --- | --- |
| Judul login dan pemulihan sandi | 30/38px; 36/44px mulai 640px | 600 |
| Judul halaman | 20/28px | 600 |
| Judul kartu dashboard | 18/28px | 600 |
| Judul bagian form | 16/24px | 500 |
| Menu, label form, tombol | 14/20px | 500 |
| Input, select, isi tabel | 14/20px | 400 |
| Nama utama tabel | 14/20px | 500 |
| Header tabel, badge | 12/18px | 500 |
| Keterangan dan validasi | 12/18px | 400 |
| Kelompok sidebar | 12/20px | 400 |
| Angka ringkasan | 30/38px | 700 |
| Judul modal | 30/38px | 600 |
| Paragraf modal | 14/24px | 400 |
| Hero publik | 36/44px; 60/72px mulai 1024px | 600 |

Jarak huruf normal. Pengecualian sesuai acuan: tombol login sosial dan label ingat perangkat memakai ketebalan 400. Breadcrumb sekunder memakai 14/20px; heading utama tetap 20/28px.

## Verifikasi

- Acuan [TailAdmin](https://laravel-demo.tailadmin.com/signin) diperiksa setelah login, termasuk computed style login dan form. Pengukuran aplikasi memakai keluarga font, ukuran, tinggi baris, ketebalan, dan jarak huruf.
- Enam role diperiksa di browser: admin, kepala sekolah, kabag Diniyyah, kabag Tahfidz, guru, dan wali santri. Ukuran viewport: desktop 1440 × 1000, tablet 768 × 1024, ponsel 390 × 844.
- Pemeriksaan mencakup halaman awal role, form Tasmi', tabel santri Filament, modal Filament, kalender admin, pemulihan sandi, halaman publik/offline, empat grafik Tahfidz wali, serta pemilihan ruang kerja dengan nama panjang. Halaman yang diperiksa tidak melebar di luar viewport; tabel dan kalender lebar menggunakan gulir di dalam kontainer.
- Login, pembukaan/penutupan sidebar, dropdown notifikasi, pemilihan kelas/santri, pemilihan ruang kerja, pergantian portal guru ke wali, pembatalan modal, serta mode terang/gelap dan reload telah diperiksa. Modal hanya dibuka dan dibatalkan.
- Browser mengonfirmasi `document.fonts.check()` berhasil dan keluarga font `Outfit, sans-serif` pada kedua theme. Link font Outfit mengarah ke aset lokal.
- `npm run build`: berhasil.
- `php artisan view:cache`: berhasil.
- `php -l app/Providers/Filament/AdminPanelProvider.php`: berhasil.
- `git diff --check`: berhasil.
- Pengujian akhir: **122 lulus, 605 assertion**. Cakupan: autentikasi, pemulihan sandi, navigasi portal, workspace, akses panel/role, isolasi guard, palet, halaman publik/error, Tahfidz, Tasmi', presensi, edit jurnal, PWA, jadwal, rekap JP/monitoring wali, input nilai, regresi jurnal/penilaian, dan empat pengujian tipografi baru. Ini merupakan pengujian area terkait, bukan pengulangan seluruh suite proyek.

Hasil pengukuran browser dan contoh screenshot tersimpan di folder private yang diabaikan Git: `storage/app/private/ui-verification/typography-*`. Browser memakai database preview `qa.sqlite`; fixture nama panjang hanya dibuat pada salinan ini.

## Integritas data dan batasan

- Perubahan ini tidak menambah atau mengubah migrasi, model, controller, route, izin, resource/form bisnis Filament, atau entrypoint deployment.
- SHA-256 database lokal asli identik sebelum dan setelah pemeriksaan: `42ECC9680C77AAC7F1C7F1801B28461BB081D622C668025C0379681631ADD70C`.
- SHA-256 12 template PDF, Excel, dan cetak identik. Berkas pengguna `pesan.md` tidak diubah.
- Perubahan Blade diaudit: ekspresi data, binding form, route, serta struktur alur tetap. Perubahan tambahan pada partial runtime Filament mengatur font grafik; atribut aksesibilitas kalender mendukung area gulir.
- Tidak ada akses tulis ke database production atau redeploy production dalam pekerjaan ini.
- Pemeriksaan browser memakai halaman dan komponen yang disebutkan di atas; setiap layar dan variasi data production tidak dibuka satu per satu. Cakupan layar lain berasal dari audit template, komponen bersama, theme panel, dan pengujian otomatis terkait.

Dokumentasi integrasi: [font lokal Filament 5](https://filamentphp.com/docs/5.x/styling/overview), [font Chart.js](https://www.chartjs.org/docs/latest/general/fonts.html).
