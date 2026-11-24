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

# Direct logs to stderr so they show up in Render's dashboard logs
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

# Sanitize DB_HOST if user accidentally pasted https:// or trailing slashes
if [ -n "$DB_HOST" ]; then
    CLEAN_HOST=$(echo "$DB_HOST" | sed -E 's|^https?://||; s|/.*$||')
    if echo "$CLEAN_HOST" | grep -qE '(^|\.)supabase\.co$'; then
        SUPABASE_REF=$(echo "$CLEAN_HOST" | sed -E 's/^(db\.)?([a-z0-9]+)\.supabase\.co$/\2/')
        echo "Detected Supabase Project Reference: $SUPABASE_REF"
        # Render requires IPv4 pooler instead of IPv6-only direct host
        export DB_HOST="aws-0-eu-central-1.pooler.supabase.com"
        export DB_PORT="${DB_PORT:-6543}"
        case "$DB_USERNAME" in
            *"."*)
                # Already has project ref in username
                ;;
            *)
                export DB_USERNAME="${DB_USERNAME:-postgres}.${SUPABASE_REF}"
                ;;
        esac
        echo "Auto-configured Supabase IPv4 Pooler: $DB_HOST:$DB_PORT with user $DB_USERNAME"
    else
        export DB_HOST="$CLEAN_HOST"
    fi
fi

# Ensure APP_KEY is valid base64 key
case "$APP_KEY" in
    base64:*)
        echo "APP_KEY is configured."
        ;;
    *)
        echo "Notice: APP_KEY is missing or invalid, generating fresh base64 key..."
        export APP_KEY=$(php artisan key:generate --show)
        echo "Generated APP_KEY successfully."
        ;;
esac

# Create container .env file if missing so php artisan commands have APP_KEY
if [ ! -f /var/www/html/.env ]; then
    touch /var/www/html/.env
fi
grep -q "^APP_KEY=" /var/www/html/.env 2>/dev/null || echo "APP_KEY=${APP_KEY}" >> /var/www/html/.env

# Clear any cached configuration from build stage
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Start PHP-FPM early so fastcgi backend is ready
php-fpm -D

# Test Database Connectivity
echo "Testing Database Connection ($DB_HOST:$DB_PORT)..."
timeout 10 php artisan tinker --execute="
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
    timeout 30 php artisan migrate --force || echo "Warning: Migration failed or timed out."

    if [ "$RUN_SEEDERS" = "true" ]; then
        echo "Running database seeders..."
        timeout 45 php artisan db:seed --force || echo "Warning: Database seeder failed or timed out."
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

# Start Nginx in foreground
exec nginx -g "daemon off;"
