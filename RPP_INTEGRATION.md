# Integrasi Project RPP

Project RPP adalah sumber data RPP. Aplikasi sekolah menyimpan salinan baca-saja untuk monitoring, ekspor, dan distribusi.

## Konfigurasi

Set token API dan secret webhook yang sama pada kedua deployment:

```env
# Project RPP
SCHOOL_INTEGRATION_API_TOKEN=<token-acak-panjang>
SCHOOL_INTEGRATION_WEBHOOK_URL=https://sekolah.example/api/integrations/rpp/webhook
SCHOOL_INTEGRATION_WEBHOOK_SECRET=<secret-hmac-acak>

# Aplikasi sekolah
RPP_SYNC_ENABLED=true
RPP_SOURCE_URL=https://rpp.example
RPP_SOURCE_API_TOKEN=<token-acak-panjang>
RPP_SYNC_WEBHOOK_SECRET=<secret-hmac-acak>
```

Jalankan `php artisan optimize:clear`, pastikan worker queue aktif, lalu lakukan initial sync dengan `php artisan rpp:sync-source`. Scheduler merekonsiliasi perubahan tiap lima menit.

## Konflik pemetaan

Master guru, mapel, kelas, dan penugasan tidak dibuat oleh sinkronisasi. Konflik muncul pada **Kurikulum & RPP → Sinkronisasi RPP**. Admin dapat memperbaiki master agar pencocokan otomatis berhasil atau menyimpan pemetaan eksplisit:

- `teacher`: ID guru Project RPP → ID `teachers` aplikasi sekolah.
- `class_subject`: `<ID mapel Project RPP>|<ID kelas Project RPP>` → ID `diniyyah_class_subjects` aplikasi sekolah.
