#!/bin/sh
set -e

echo "🚀 Starting DataNarasi..."

# Replace $PORT in nginx config
sed -i "s/\${PORT:-8080}/$PORT/g" /etc/nginx/http.d/default.conf

# Create storage directories if needed
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p storage/app/public/charts

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Cache config for production
echo "⚡ Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link --force 2>/dev/null || true

echo "✅ DataNarasi ready on port $PORT"

# Start supervisor (nginx + php-fpm + queue)
exec /usr/bin/supervisord -c /etc/supervisord.conf
