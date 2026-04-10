#!/bin/bash
set -e

echo "Starting Laravel Application..."

# Ejecutar migraciones
echo "Running migrations..."
php artisan migrate --force --no-interaction || echo "Migrations already applied or failed"

# Crear symlink de storage si no existe
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage symlink..."
    php artisan storage:link --force
fi

# Limpiar y cachear configuracion
echo "Caching configuration..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimizar autoloader
composer dump-autoload --optimize --no-dev --classmap-authoritative 2>/dev/null || true

echo "Application ready!"
exec "$@"
