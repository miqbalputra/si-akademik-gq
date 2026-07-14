# Panduan Deploy ke Coolify

Aplikasi: **SI Akademik GQ** (Laravel 13 + Filament 5 + Octane/FrankenPHP).
Image dibangun dari `Dockerfile` (base `dunglas/frankenphp:php8.3-alpine`). Server
`octane:start --server=frankenphp` dipakai sebagai entrypoint runtime via
`docker-entrypoint.sh`.

> Prinsip penting: secret & konfigurasi lingkungan **tidak** di-bake ke image. Semua
> di-injeksi Coolify sebagai environment variable saat container start. Entry point
> otomatis menjalankan `config:cache`, `route:cache`, `view:cache`, `event:cache`,
> `migrate --force`, dan `storage:link` setiap container up, menggunakan env yang
> baru saja diinjeksi.

---

## 1. Tambahkan aplikasi di Coolify

1. **New Resource → Docker (Build from Git)**.
2. Hubungkan repo GitHub: `miqbalputra/si-akademik-gq`.
3. Pilih branch `main` (atau branch deploy yang dipilih).
4. **Base Directory**: biarkan kosong (root repo) atau isi `nilai-sekolah/` bila
   repo menyimpan aplikasi di subfolder.
   > Catatan: repo ini adalah direktori aplikasi langsung, jadi base directory
   > biasanya kosong. Sesuaikan dengan struktur repo Anda.
5. **Dockerfile Location**: `Dockerfile`.
6. Port exposenya `8000` (sesuai `EXPOSE` di Dockerfile). Bila Coolify butuh port
   custom, set env `PORT` — entrypoint membaca `PORT` (default 8000).
7. Aktifkan **Build Pack: Dockerfile**.

---

## 2. Set Environment Variables (WAJIB)

Buka tab **Environment Variables** di resource Coolify, lalu tambahkan:

### Inti Laravel
| Variable | Nilai | Keterangan |
|---|---|---|
| `APP_NAME` | `SI Akademik GQ` | Nama app |
| `APP_ENV` | `production` | **Jangan `local`** |
| `APP_DEBUG` | `false` | **Penting**: `true` akan membocorkan stack trace & env |
| `APP_KEY` | `base64:...` | Generate baru: jalankan `php artisan key:generate` di lokal, lalu salin nilainya. **Jangan pakai key dev.** |
| `APP_URL` | `https://domain-anda.com` | URL produksi tanpa trailing slash |
| `APP_LOCALE` | `id` | |
| `APP_FALLBACK_LOCALE` | `id` | |
| `APP_MAINTENANCE_DRIVER` | `database` | Rekomendasi prod (bukan `file`) |
| `BCRYPT_ROUNDS` | `12` | |

### Database (gunakan PostgreSQL di prod)
| Variable | Nilai |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | host database (contoh: nama container Postgres di Coolify) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | nama database |
| `DB_USERNAME` | user |
| `DB_PASSWORD` | password |

> Image sudah terpasang ekstensi `pdo_pgsql` dan `pdo_sqlite`. SQLite hanya untuk
> dev; di prod gunakan PostgreSQL/MySQL. Entry point menjalankan `migrate --force`
> otomatis saat container up.

### Session, Cache, Queue, Filesystem
| Variable | Nilai | Keterangan |
|---|---|---|
| `SESSION_DRIVER` | `database` | |
| `SESSION_ENCRYPT` | `true` | Rekomendasi prod |
| `SESSION_LIFETIME` | `120` | |
| `CACHE_STORE` | `database` | Bila ada Redis, set `redis` |
| `QUEUE_CONNECTION` | `database` | Bila ada Redis, set `redis` |
| `FILESYSTEM_DISK` | `local` | Atau `s3` bila pakai object storage |
| `BROADCAST_CONNECTION` | `log` | |

### Mail (opsional — bila ada fitur email)
Set sesuai provider (mis. SMTP/Postmark/Resend):
`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

### Google OAuth (login Google)
| Variable | Nilai |
|---|---|
| `GOOGLE_CLIENT_ID` | client id OAuth produksi |
| `GOOGLE_CLIENT_SECRET` | client secret OAuth produksi |
| `GOOGLE_REDIRECT_URI` | `${APP_URL}/auth/google/callback` |
| `GOOGLE_HOSTED_DOMAIN` | `griyaquran.sch.id` |

> **Penting — `GOOGLE_HOSTED_DOMAIN`**: bila dikosongkan, **siapa pun** dengan akun
> Google bisa login. Set ke domain Workspace sekolah agar hanya akun `@griyaquran.sch.id`
> yang diizinkan. Pastikan juga redirect URI produksi terdaftar di Google Cloud Console.

### Integrasi n8n (opsional)
| Variable | Nilai |
|---|---|
| `N8N_API_TOKEN` | token acak yang kuat, mis. 40–60 karakter |

> Endpoint `GET /api/v1/diniyyah/journals/missing-reminders` bersifat **fail-closed**:
> bila `N8N_API_TOKEN` kosong, endpoint selalu menolak akses (401) — tidak ada token
> default. Set nilai kuat bila integrasi dipakai; biarkan kosong bila tidak dipakai.

### Octane
| Variable | Nilai |
|---|---|
| `OCTANE_SERVER` | `frankenphp` | (sudah default di `config/octane.php`, tetap disarankan eksplisit) |

---

## 3. Deploy

1. Klik **Deploy**.
2. Build akan menjalankan `composer install --no-dev` + `npm ci && npm run build`.
   Karena `.dockerignore` memblokir `.env`, `vendor/`, `node_modules/`, sqlite, log,
   dan file scratch/debug, image tetap ringan & tanpa secret ter-embed.
3. Saat container up, `docker-entrypoint.sh` otomatis: `storage:link`,
   `migrate --force`, lalu cache config/route/view/event, lalu start Octane.

---

## 4. Verifikasi pascar-deploy

- `GET /up` → harus **200 OK** (health check Laravel).
- `GET /login` → halaman login muncul.
- Login Google → berhasil & membatasi ke domain `griyaquran.sch.id`.
- `GET /api/v1/diniyyah/journals/missing-reminders` tanpa token → **401** (fail-closed).
- Cek log Coolify: tidak ada error `APP_KEY` / `DB connection` / `Octane`.

---

## 5. Catatan operasional

- **Migrasi otomatis**: entry point menjalankan `php artisan migrate --force` tiap
  start. Aman untuk app ini. Bila ingin migrasi manual, edit `docker-entrypoint.sh`
  dan hapus baris tersebut (atau ganti dengan command terpisah via job Coolify).
- **Storage**: file upload disimpan di `/app/storage/app`. Untuk persistensi antar
  rebuild, gunakan persistent volume Coolify yang di-mount ke `/app/storage`.
- **Reload tanpa rebuild**: bila hanya env yang berubah, cukup restart container di
  Coolify — entry point akan membangun ulang cache config dengan env baru.
- **Build ulang cache saat deploy**: cache config/route/view/event dibangun ulang
  setiap container start, jadi perubahan env langsung efektif tanpa rebuild image.