#!/bin/sh
set -e

echo "🚀 Starting DataNarasi..."

# Replace $PORT in nginx config
export PORT="${PORT:-8080}"
sed -i "s/\${PORT:-8080}/$PORT/g" /etc/nginx/http.d/default.conf

# Create storage directories if needed
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p storage/app/public/charts

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Generate .env from Railway environment variables if not exists
# Laravel needs this file for artisan commands even when config is cached
if [ ! -f .env ]; then
    echo "📝 Creating .env from environment..."
    touch .env
    # Write critical vars so artisan doesn't fail
    echo "APP_KEY=${APP_KEY}" >> .env
    echo "APP_ENV=${APP_ENV:-production}" >> .env
    echo "APP_DEBUG=${APP_DEBUG:-false}" >> .env
fi

# Run migrations (allow failure on first deploy if DB not ready)
echo "📦 Running migrations..."
php artisan migrate --force || echo "⚠️ Migration failed, continuing..."

# Clear and rebuild caches
echo "⚡ Caching config..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache || echo "⚠️ Config cache failed, using runtime config"
php artisan route:cache || echo "⚠️ Route cache failed, using runtime routes"
php artisan view:cache || echo "⚠️ View cache failed, using runtime views"

# Create storage link
php artisan storage:link --force 2>/dev/null || true

echo "✅ DataNarasi ready on port $PORT"

# Start supervisor (nginx + php-fpm + queue)
exec /usr/bin/supervisord -c /etc/supervisord.conf
