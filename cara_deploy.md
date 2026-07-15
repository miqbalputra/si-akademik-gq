# Panduan Deploy ke Coolify

Aplikasi: **SI Akademik GQ** (Laravel 13 + Filament 5 + Octane/FrankenPHP).
Image dibangun dari `Dockerfile` (base `dunglas/frankenphp:php8.4-alpine`, PHP 8.4
sesuai `composer.lock`). Server
`octane:start --server=frankenphp` dipakai sebagai entrypoint runtime via
`docker-entrypoint.sh`.

> Prinsip penting: secret & konfigurasi lingkungan **tidak** di-bake ke image. Semua
> di-injeksi Coolify sebagai environment variable saat container start. Entry point
> otomatis menjalankan `config:cache`, `route:cache`, `view:cache`, `event:cache`,
> `migrate --force`, dan `storage:link` setiap container up, menggunakan env yang
> baru saja diinjeksi.

---

## 0. Siapkan resource database di Coolify (WAJIB sebelum deploy)

Repo **tidak menyertakan `docker-compose.yml`**, jadi Coolify tidak otomatis
membuat database. Anda harus membuatnya dulu sebagai resource Coolify terpisah.
Kedua container (app + DB) akan berada di jaringan internal yang sama dan saling
terhubung via hostname internal Docker.

1. **Buat PostgreSQL**: Coolify → **New Resource → Databases → PostgreSQL**.
   - Catat: **nama database**, **username**, **password**, dan **internal hostname**
     (Coolify menampilkan connection string + Docker hostname seperti
     `postgresql:<container-name>` atau `postgres-xxxx` di halaman resource).
   - Nilai `DB_HOST` nanti diambil dari hostname internal ini — **bukan `localhost`**.
2. **(Opsional) Buat Redis** bila ingin `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis`:
   - Coolify → **New Resource → Databases → Redis**. Catat host/port/password.
   - Bila tidak dibuat, biarkan `CACHE_STORE=database` & `QUEUE_CONNECTION=database`
     (lihat tabel di langkah 2) — tetap berfungsi, hanya tidak secepat Redis.
3. Pastikan resource database berstatus **running** sebelum lanjut ke langkah 1.

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

> **Jangan klik Deploy dulu** — selesaikan langkah 2 (env vars) dan langkah 3
> (persistent storage) terlebih dahulu agar container pertama tidak gagal koneksi
> DB atau kehilangan upload saat rebuild.

---

## 2. Set Environment Variables (WAJIB)

> **Generate `APP_KEY` dulu (sebelum deploy pertama):** jalankan di lokal
> `php artisan key:generate` (atau `php -r "echo 'base64:'.Illuminate\Support\Str::random(32);"`)
> dan salin nilainya. Entrypoint **tidak** generate otomatis — tanpa key, session
> & enkripsi akan gagal. **Jangan pakai key dev.**

Buka tab **Environment Variables** di resource Coolify, lalu tambahkan.

> ⚠️ **PENTING — tandai secret sebagai "Runtime only":** untuk semua variabel
> sensitif (`APP_KEY`, `DB_PASSWORD`, `GOOGLE_CLIENT_SECRET`, `N8N_API_TOKEN`,
> `MAIL_PASSWORD`, `REDIS_PASSWORD`), **jangan** biarkan Coolify meng-injeksinya
> sebagai build-time `ARG`. Bila ikut build ARG, nilai akan **ter-bake ke metadata
> image** (terlihat siapa pun yang punya akses ke image / `docker history`) — log
> build sendiri memunculkan peringatan `SecretsUsedInArgOrEnv`. Di Coolify, set
> variabel tersebut ke mode **"Runtime only"** (hanya di-injeksi saat container
> start, bukan saat `docker build`). Dockerfile repo **tidak** memakai ARG/ENV
> untuk secret — jadi selama secret ditandai Runtime only, image tetap bersih.

### Quickstart — copy-paste block (WAJIB)

Paste seluruh blok ini sebagai bulk/multiline env di Coolify, lalu ganti nilai
placeholder (`APP_KEY`, `APP_URL`, `DB_*`). Untuk yang sensitif (`APP_KEY`,
`DB_PASSWORD`, dll.) centang **encrypted/secret** di Coolify.

```env
APP_NAME=SI Akademik GQ
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GANTI_DENGAN_KEY_HASIL_KEY_GENERATE
APP_URL=https://domain-anda.com
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_MAINTENANCE_DRIVER=database
BCRYPT_ROUNDS=12

DB_CONNECTION=pgsql
DB_HOST=GANTI_INTERNAL_HOSTNAME_POSTGRES_COOLIFY
DB_PORT=5432
DB_DATABASE=si_akademik
DB_USERNAME=postgres
DB_PASSWORD=GANTI_PASSWORD_POSTGRES

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

OCTANE_SERVER=frankenphp
```

> Cara generate `APP_KEY` lokal:
> ```bash
> php artisan key:generate
> # atau tanpa Laravel terinstall:
> php -r "echo 'base64:'.base64_encode(random_bytes(32));"
> ```
> Salin hasilnya (sudah ber-prefix `base64:`) ke env `APP_KEY` di atas.

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
| `DB_HOST` | **internal hostname** container Postgres dari halaman resource PostgreSQL Coolify (bukan `localhost`) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | nama database (sesuai resource Postgres di langkah 0) |
| `DB_USERNAME` | user (sesuai resource Postgres di langkah 0) |
| `DB_PASSWORD` | password (set sebagai secret/encrypted var di Coolify) |

> Image sudah terpasang ekstensi `pdo_pgsql` dan `pdo_sqlite`. SQLite hanya untuk
> dev; di prod gunakan PostgreSQL/MySQL. Entry point menjalankan `migrate --force`
> otomatis saat container up. Ambil semua nilai `DB_*` dari halaman resource
> PostgreSQL yang dibuat di langkah 0 — Coolify menampilkannya di bagian
> **Connection** resource tersebut.

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

### Quickstart — copy-paste block (OPSIONAL, sesuai fitur yang dipakai)

**Login Google:**
```env
GOOGLE_CLIENT_ID=GANTI_CLIENT_ID
GOOGLE_CLIENT_SECRET=GANTI_CLIENT_SECRET
GOOGLE_REDIRECT_URI=https://domain-anda.com/auth/google/callback
GOOGLE_HOSTED_DOMAIN=griyaquran.sch.id
```

**Integrasi n8n** (kosongkan `N8N_API_TOKEN` bila tidak dipakai — endpoint jadi 401/fail-closed):
```env
N8N_API_TOKEN=GANTI_TOKEN_40_60_KARAKTER_ACAK
```

**Email** (kalau ada fitur email):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.com
MAIL_PORT=587
MAIL_USERNAME=GANTI_USERNAME
MAIL_PASSWORD=GANTI_PASSWORD
MAIL_FROM_ADDRESS=noreply@domain-anda.com
MAIL_FROM_NAME="SI Akademik GQ"
```

**Pakai Redis** (kalau Anda buat resource Redis di langkah 0 — ganti juga
`CACHE_STORE` & `QUEUE_CONNECTION` di blok WAJIB jadi `redis`):
```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=GANTI_INTERNAL_HOSTNAME_REDIS_COOLIFY
REDIS_PORT=6379
REDIS_PASSWORD=GANTI_PASSWORD_REDIS
REDIS_CLIENT=phpredis
```
Bila tidak membuat Redis, biarkan `CACHE_STORE=database` & `QUEUE_CONNECTION=database`
seperti blok WAJIB.

---

## 3. Setup Persistent Storage (WAJIB)

File upload (rapor PDF, import Excel, dll.) disimpan di `/app/storage/app`.
Tanpa persistent volume, isi `/app/storage` hilang setiap rebuild container.

1. Di resource aplikasi Coolify → tab **Persistent Storage** (atau **Storages**).
2. Tambahkan volume dengan **Mount Path** `/app/storage`.
   - Pakai volume Coolify (docker volume) atau bind mount — keduanya dapat dipakai.
   - Bila ingin lebih sempit, gunakan `/app/storage/app` (hanya file upload yang
     dipertahankan; cache/framework tetap ephemeral). Default aman: `/app/storage`.
3. Simpan. Entrypoint menjalankan `php artisan storage:link --force` tiap start,
   jadi symlink `public/storage` tetap tercipta ulang dengan benar.

---

## 4. Deploy

1. Klik **Deploy**.
2. Build akan menjalankan `composer install --no-dev` + `npm ci && npm run build`.
   Karena `.dockerignore` memblokir `.env`, `vendor/`, `node_modules/`, sqlite, log,
   dan file scratch/debug, image tetap ringan & tanpa secret ter-embed.
3. Saat container up, `docker-entrypoint.sh` otomatis: `storage:link`,
   `migrate --force`, lalu cache config/route/view/event, lalu start Octane.

---

## 5. Verifikasi pascar-deploy

- `GET /up` → harus **200 OK** (health check Laravel).
- `GET /login` → halaman login muncul.
- Login Google → berhasil & membatasi ke domain `griyaquran.sch.id`.
- `GET /api/v1/diniyyah/journals/missing-reminders` tanpa token → **401** (fail-closed).
- Cek log Coolify: tidak ada error `APP_KEY` / `DB connection` / `Octane`.

---

## 6. Troubleshooting build

- **`composer install` gagal: "your lock file does not contain a compatible set
  of packages" / "requires php >=8.4 -> your php version does not satisfy"**:
  `composer.lock` dibuat dengan PHP 8.4, tapi image Docker memakai PHP 8.3. Pastikan
  Dockerfile memakai base `dunglas/frankenphp:php8.4-alpine` (sesuai `composer.lock`)
  — **bukan** `php8.3-alpine`. Setelah ganti, commit+push lalu rebuild di Coolify.
- **Peringatan `SecretsUsedInArgOrEnv` di log build**: secret ter-bake sebagai
  build-time `ARG`. Tandai variabel sensitif sebagai **Runtime only** di Coolify
  (lihat peringatan di langkah 2), lalu rotasi secret yang sempat terbocorkan
  (`DB_PASSWORD`, `GOOGLE_CLIENT_SECRET`, `APP_KEY`) di sumber masing-masing.

---

## 7. Catatan operasional

- **Migrasi otomatis**: entry point menjalankan `php artisan migrate --force` tiap
  start. Aman untuk app ini. Bila ingin migrasi manual, edit `docker-entrypoint.sh`
  dan hapus baris tersebut (atau ganti dengan command terpisah via job Coolify).
- **Storage & persistensi**: file upload disimpan di `/app/storage/app`; gunakan
  persistent volume Coolify yang di-mount ke `/app/storage` (lihat langkah 3 di atas)
  agar data bertahan antar rebuild.
- **Reload tanpa rebuild**: bila hanya env yang berubah, cukup restart container di
  Coolify — entry point akan membangun ulang cache config dengan env baru.
- **Build ulang cache saat deploy**: cache config/route/view/event dibangun ulang
  setiap container start, jadi perubahan env langsung efektif tanpa rebuild image.