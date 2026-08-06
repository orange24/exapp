#!/bin/sh
set -e

cd /var/www/html

# Config cache (must be runtime — reads env vars from Cloud Run)
php artisan config:cache

# Run migrations (idempotent)
php artisan migrate --force 2>/dev/null || true

# Create storage link if not exists
php artisan storage:link 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache

echo "==> ExApp ready on port ${PORT:-8080}"

exec "$@"
