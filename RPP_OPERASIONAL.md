# Operasional Modul RPP

## Konfigurasi produksi

Dokumen RPP disimpan pada bucket **private** Cloudflare R2. Tidak ada berkas RPP
yang dipublikasikan langsung; aplikasi membuat tautan unduhan bertanda tangan dan
bermasa berlaku terbatas.

Di dashboard Cloudflare, buat bucket misalnya `rpp-griyaquran-production`, lalu
buat **R2 API Token** bertipe *Object Read & Write* yang dibatasi hanya ke bucket
tersebut. Simpan secret key saat dibuat karena Cloudflare hanya menampilkannya
sekali. Ambil Account ID untuk menyusun endpoint.

Set environment berikut di Coolify/VPS (jangan gunakan Global API Key Cloudflare):

```env
RPP_FILESYSTEM_DISK=s3
RPP_R2_BUCKET=rpp-griyaquran-production
RPP_R2_ENDPOINT=https://<CLOUDFLARE_ACCOUNT_ID>.r2.cloudflarestorage.com
RPP_R2_ACCESS_KEY_ID=...
RPP_R2_SECRET_ACCESS_KEY=...
RPP_R2_REGION=auto
RPP_R2_USE_PATH_STYLE_ENDPOINT=true
RPP_SHARE_MINUTES=60
QUEUE_CONNECTION=database
```

Setelah deploy, jalankan `php artisan optimize:clear`. Pastikan konfigurasi disk
tetap bernama `rpp`; tidak perlu mengubah `FILESYSTEM_DISK` global aplikasi.
Untuk memeriksa koneksi tanpa mengunggah dokumen produksi, gunakan Tinker:

```bash
php artisan tinker --execute="Storage::disk('rpp')->put('healthcheck.txt', 'ok'); echo Storage::disk('rpp')->get('healthcheck.txt'); Storage::disk('rpp')->delete('healthcheck.txt');"
```

`Dockerfile` sudah memasang Chromium, GD, ZIP, ekstensi S3, dan `pdo_mysql`. Worker queue dijalankan oleh entrypoint; pastikan tabel jobs tersedia dan proses scheduler menjalankan `php artisan schedule:run` setiap menit agar pengingat RPP pukul 06:30 WIB terkirim.

## Migrasi data RPP lama

1. Pulihkan dump MariaDB aplikasi RPP lama ke database sementara yang hanya dapat dibaca oleh proses impor.
2. Set `LEGACY_RPP_DB_*` ke database sementara tersebut. Salin folder upload/ekspor lama ke satu folder lokal yang dapat dibaca container.
3. Jalankan validasi terlebih dahulu:

```bash
php artisan rpp:import-legacy --connection=legacy_rpp --files=/data/rpp-legacy --dry-run
```

4. Periksa berkas rekonsiliasi di `storage/app/private/rpp-import/`. Baris konflik harus dipetakan di data sekolah terlebih dahulu; importer tidak pernah menebak guru, mapel, kelas, atau penugasan.
5. Jalankan impor nyata dengan perintah yang sama tanpa `--dry-run`. Perintah aman diulang karena memakai `legacy_source_id` dan `rpp_import_records`.
6. Bandingkan jumlah RPP, Promes, dan checksum file sebelum mengarahkan domain aplikasi RPP lama ke modul ini.

Kredensial AI tidak ikut diimpor. Admin mengisi endpoint OpenAI-compatible, model vision, dan API key baru lewat menu **Kurikulum & RPP → Konfigurasi AI RPP**.
