#!/bin/sh
set -e

# Substitute PORT in nginx configuration (Render assigns $PORT at runtime, e.g. 10000)
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

# Ensure required storage and bootstrap directories exist with proper permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate application key if missing
if [ -z "$APP_KEY" ]; then
    echo "Notice: APP_KEY is empty, generating fresh key..."
    php artisan key:generate --force
fi

# Run database migrations if enabled
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Warning: Migration failed, check database connectivity."
    
    if [ "$RUN_SEEDERS" = "true" ]; then
        echo "Running database seeders..."
        php artisan db:seed --force || echo "Warning: Database seeder failed."
    fi
fi

# Cache config, routes, and views for maximum production performance
if [ "$APP_ENV" = "production" ]; then
    echo "Caching Laravel configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "=========================================================="
echo " Starting UniLibrary Web Service on port $PORT"
echo " Environment: ${APP_ENV:-production}"
echo "=========================================================="

# Start PHP-FPM in background and Nginx in foreground
php-fpm -D
exec nginx -g "daemon off;"
