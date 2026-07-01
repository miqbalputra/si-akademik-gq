FROM dunglas/frankenphp:php8.3-alpine

# Set working directory
WORKDIR /app

# Install system dependencies & PHP extensions required by Laravel & PostgreSQL
RUN install-php-extensions \
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

# Copy the application source code
COPY . /app

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Clear caches and optimize for production
RUN php artisan optimize:clear
RUN php artisan optimize
RUN php artisan view:cache
RUN php artisan event:cache

# Create storage link if not exists
RUN php artisan storage:link || true

# Set appropriate permissions
RUN chown -R root:root /app && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Expose port for Coolify
EXPOSE 8000

# Set environment variable for Octane
ENV OCTANE_SERVER=frankenphp

# Run Octane server
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
