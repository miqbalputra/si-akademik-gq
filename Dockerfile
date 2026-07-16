FROM dunglas/frankenphp:php8.4-alpine

# Set working directory
WORKDIR /app

# Install system dependencies & PHP extensions required by Laravel & PostgreSQL.
# sqlite pdo_sqlite is kept for local/dev parity; pdo_pgsql/pgsql for production.
RUN install-php-extensions \
    pdo_sqlite \
    pdo_pgsql \
    pgsql \
    pcntl \
    redis \
    gd \
    intl \
    zip \
    bcmath

# Install Node.js and npm for frontend assets build
RUN apk add --no-cache nodejs npm git

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only what .dockerignore allows (no .env, no vendor/, no node_modules/,
# no sqlite db, no logs, no scratch/debug scripts — secrets/runtime data are
# injected by Coolify at runtime).
COPY . /app

# Install PHP dependencies (production, no dev tools)
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Install Node dependencies and build frontend assets
RUN npm ci && npm run build

# Publish Filament panel assets (Alpine components, JS, CSS) into public/.
# Filament serves these as static files from public/ (no route fallback). If this
# step is missing, the browser 404s the JS and Filament buttons (Create/Save/etc.)
# silently do nothing — the server and DB work, but no Livewire request is ever sent.
RUN php artisan filament:assets

# Set appropriate permissions for directories the runtime must write to.
# Run as non-root where possible; storage & bootstrap/cache stay group-writable.
RUN chown -R root:root /app && \
    chmod -R 775 /app/storage /app/bootstrap/cache && \
    chmod +x /app/docker-entrypoint.sh

# Expose port (Coolify maps this). PORT env can override at runtime.
EXPOSE 8000

# Octane server is configured in config/octane.php (default: frankenphp).
ENV OCTANE_SERVER=frankenphp

# Runtime entrypoint builds caches against the real (injected) env, runs
# migrations, then starts Octane/FrankenPHP.
CMD ["/app/docker-entrypoint.sh"]