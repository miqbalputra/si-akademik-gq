# Setup Lokal Aplikasi Nilai Sekolah

## Status Fondasi

Fondasi awal sudah dibuat berdasarkan `PRD.md`, `DATABASE_SCHEMA.md`, dan `stack.md`.

Yang sudah tersedia:

- Laravel 13.
- Filament 5 admin panel di `/admin`.
- Spatie Permission.
- Spatie Activity Log.
- Migration data inti sekolah, periode, siswa, wali, guru, kelas, penempatan siswa, dan wali kelas.
- Model Eloquent dan relasi utama tahap awal.
- Seeder role dan permission dasar.
- Resource Filament data master inti.
- Master diniyyah: mata pelajaran, mapel per kelas/periode, dan penugasan guru.
- Seeder mata pelajaran diniyyah awal dari PRD.
- Struktur nilai diniyyah: assessment set, komponen, input skor, hasil hitung, dan validasi.
- Service perhitungan nilai diniyyah untuk metode weighted 40/60, practical (multi-blok), dan direct final.
- Test perhitungan nilai diniyyah (weighted, practical, direct_final).
- Generator komponen default assessment set (weighted, practical, direct_final).
- Resource Filament struktur nilai sudah memiliki form/tabel dasar dan aksi generate komponen.
- Aksi hitung ulang nilai assessment set.
- Policy awal akses nilai diniyyah untuk admin, PJ diniyyah, dan guru berdasarkan penugasan.
- Halaman input nilai guru mobile-first di `/guru/diniyyah-scores`.
- Filter query resource assessment dan skor agar guru hanya melihat data assignment miliknya.
- Monitoring progres input nilai diniyyah di `/diniyyah/monitoring`.
- Workflow submit, validasi, dan perlu revisi untuk assessment set.
- Audit log khusus perubahan nilai di `score_change_logs`.
- Observer `DiniyyahScoreObserver` mencatat nilai lama, nilai baru, user pengubah, waktu, dan alasan.
- Leger diniyyah: snapshot, rows, dan cells dinamis.
- Generator leger dari hasil assessment diniyyah.
- Preview leger di `/diniyyah/ledger/{snapshot}`.
- Resource Filament dasar untuk snapshot leger.
- Workflow leger: validasi dan lock snapshot.
- Snapshot leger yang sudah `locked` atau `published` tidak bisa digenerate ulang.
- Rapor diniyyah dari snapshot leger.
- Preview rapor per santri di `/report-cards/{reportCard}`.
- Workflow rapor: lock dan publish.
- Resource Filament dasar untuk rapor.
- Tanda tangan rapor dikelola via Filament RelationManager (`SignaturesRelationManager`).
- Dashboard wali santri di `/wali`.
- Policy rapor: wali hanya bisa melihat rapor `published` milik anak yang terhubung.
- Akses panel Filament `/admin` dibatasi untuk admin, kepala bagian diniyyah, dan kepala sekolah.
- Navigation Filament sudah dikelompokkan: Data Sekolah, Struktur Kelas, Diniyyah, serta Leger & Rapor.
- Dashboard ringkasan admin/kepala bagian/kepala sekolah sudah tersedia di `/admin`.
- Export PDF rapor via DomPDF (`barryvdh/laravel-dompdf`) di `/report-cards/{reportCard}/download-pdf`.
- Export Excel leger (HTML-based .xls, tanpa package tambahan) di `/diniyyah/ledger/{snapshot}/export-excel`.
- Queue jobs untuk proses berat: `GenerateDiniyyahLedger`, `GenerateReportCards`, `GenerateReportCardPdf`, `ExportDiniyyahLedgerExcel`.
- Tabel panel support: `dashboard_metric_snapshots`, `panel_saved_filters`, `panel_user_preferences`, `panel_notifications`, `report_export_requests`.
- PWA: manifest.json, service worker (`sw.js`), offline page, SVG icons, auto-registration di halaman utama.
- Command `php artisan pwa:generate-icons` untuk generate PNG icons (butuh GD extension).

## Login Admin Lokal

- URL: `/admin`
- Email: `admin@example.com`
- Password: `password`

Ganti password sebelum digunakan di lingkungan selain lokal.

## Command Penting

Karena PHP winget belum masuk PATH shell saat ini, command lokal dapat dijalankan dengan path PHP absolut:

```powershell
& "C:\Users\LENOVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan serve
```

Migration ulang dan seed:

```powershell
& "C:\Users\LENOVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan migrate:fresh --seed
```

Seed demo data lengkap (leger + rapor published):

```powershell
& "C:\Users\LENOVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan db:seed --class=DemoSeeder
```

Test:

```powershell
& "C:\Users\LENOVO\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan test
```

Generate PWA PNG icons (butuh GD extension):

```powershell
php artisan pwa:generate-icons
```

## Catatan Database

Development lokal masih memakai SQLite bawaan Laravel agar cepat diverifikasi.

Production sesuai dokumen harus memakai PostgreSQL dengan env:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=nilai_sekolah
DB_USERNAME=nilai_user
DB_PASSWORD=change_me
```

## Catatan PWA

- Manifest dan service worker sudah tersedia.
- SVG icons tersedia sebagai fallback di `public/icons/`.
- Untuk PNG icons yang lebih kompatibel, jalankan `php artisan pwa:generate-icons` di server yang punya GD extension.
- PWA meta tags dan SW registration ada di partial `resources/views/partials/pwa-head.blade.php`, di-include di halaman login, guru, wali, attendance, dan welcome.

## Catatan Export

- PDF rapor menggunakan `barryvdh/laravel-dompdf`. Download PDF tersedia di tombol "Download PDF" pada halaman rapor.
- Excel leger menggunakan HTML-based `.xls` (kompatibel dengan Excel, LibreOffice, Google Sheets) tanpa package tambahan. Export tersedia di tombol "Export Excel" pada halaman leger.
- Untuk leger besar (30+ santri) dengan queue aktif, export di-dispatch via queue job (`ExportDiniyyahLedgerExcel`) dan hasil disimpan di `storage/app/exports/`.
- PDF rapor juga bisa di-generate async via queue (`GenerateReportCardPdf`), hasil disimpan di `storage/app/rapor/`.

## Tahap Berikutnya

Tahap berikutnya yang direkomendasikan:

1. Siapkan modul tahfidz tahap 2 (schema sudah disiapkan placeholder).
2. Aktivasi queue worker dan scheduler di production (Coolify).
3. Generate PNG PWA icons di production (`php artisan pwa:generate-icons`).
4. Integrasi `dashboard_metric_snapshots` untuk caching dashboard berat.
5. Integrasi `panel_notifications` untuk notifikasi internal panel.