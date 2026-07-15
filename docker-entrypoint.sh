#!/bin/sh
# Runtime entrypoint for the FrankenPHP/Octane container.
#
# Config/view/route caches MUST be (re)built here — not at image build time —
# because the production environment variables (APP_KEY, DB_*, GOOGLE_*,
# N8N_API_TOKEN, etc.) are injected by Coolify only at container start. Building
# the config cache during `docker build` (when no .env is present) would bake
# empty/null values into bootstrap/cache/config.php and silently break the app
# at runtime even though the real env vars are present.
set -e

# Ensure the storage symlink exists (idempotent; safe to re-run).
php artisan storage:link --force || true

# Run pending migrations (force = no confirmation prompt in production).
# Safe to remove if your deploy pipeline runs migrations separately.
php artisan migrate --force || true

# Cache config/routes/views/events using the REAL runtime env.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Hand off to Octane (FrankenPHP). Exec so the process replaces the shell
# and receives signals (graceful reload / shutdown) correctly.
#
# --admin-port is explicit (not auto-calculated). Octane's default admin port
# formula is `2019 + (app_port - 8000)`, which goes NEGATIVE when the app port
# is below 5981 (e.g. Coolify injecting PORT=80/3000). A negative value throws
# "Unable to determine admin port" and crashes the container on every start.
# Pinning admin port to 2019 (the FrankenPHP default) avoids that entirely.
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port="${PORT:-8000}" --admin-port=2019