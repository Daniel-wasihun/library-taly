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

# Direct logs to stderr so they show up directly in Render's dashboard logs
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

# Ensure APP_KEY is valid base64 key
case "$APP_KEY" in
    base64:*)
        echo "APP_KEY is set."
        ;;
    *)
        echo "Notice: APP_KEY is missing or not a base64 key, generating a valid key..."
        GENERATED_KEY=$(php artisan key:generate --show)
        export APP_KEY="$GENERATED_KEY"
        echo "Generated APP_KEY: $APP_KEY"
        ;;
esac

# Test Database Connectivity
echo "Testing Database Connection to $DB_HOST:$DB_PORT..."
php artisan tinker --execute="
try {
    \DB::connection()->getPdo();
    echo '>>> Database connected successfully!' . PHP_EOL;
} catch (\Throwable \$e) {
    echo '>>> DATABASE CONNECTION ERROR: ' . \$e->getMessage() . PHP_EOL;
}
" || true

# Run database migrations if enabled
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Warning: Migration failed, check database credentials."

    if [ "$RUN_SEEDERS" = "true" ]; then
        echo "Running database seeders..."
        php artisan db:seed --force || echo "Warning: Database seeder failed."
    fi
fi

# Cache config, routes, and views for maximum production performance
if [ "$APP_ENV" = "production" ]; then
    echo "Caching Laravel configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "=========================================================="
echo " Starting UniLibrary Web Service on port $PORT"
echo " Environment: ${APP_ENV:-production}"
echo " Log Channel: $LOG_CHANNEL"
echo "=========================================================="

# Start PHP-FPM in background and Nginx in foreground
php-fpm -D
exec nginx -g "daemon off;"
