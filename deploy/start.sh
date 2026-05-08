#!/bin/sh
# NO set -e — we must reach supervisord no matter what

echo "🚀 Starting DataNarasi..."

# ---- 1. Nginx port config ----
export PORT="${PORT:-8080}"
# Use a more robust sed pattern
sed -i "s|\${PORT:-8080}|${PORT}|g" /etc/nginx/http.d/default.conf 2>/dev/null || true

# ---- 2. Storage directories ----
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs
mkdir -p storage/app/public/charts

# ---- 3. Permissions ----
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ---- 4. Create .env from Railway environment ----
if [ ! -f .env ]; then
    echo "📝 Creating .env from environment..."
    # Dump ALL relevant env vars into .env so Laravel can read them
    env | grep -E '^(APP_|DB_|MYSQL_|REDIS_|QUEUE_|MAIL_|LOG_|PYTHON_|GEMINI_|KIMI_|GLM_|NVIDIA_|MINIMAX_|CLAUDE_|OPENROUTER_|AI_|FILESYSTEM_|MAX_UPLOAD|ALLOWED_)' > .env 2>/dev/null || true
    # Ensure critical vars exist
    echo "APP_ENV=${APP_ENV:-production}" >> .env
    echo "APP_DEBUG=${APP_DEBUG:-false}" >> .env
fi

# ---- 5. Laravel setup (all optional — must not block startup) ----
echo "📦 Running Laravel setup..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

php artisan migrate --force 2>&1 || echo "⚠️  Migration skipped"
php artisan db:seed --force 2>&1 || echo "⚠️  Seeding skipped"
php artisan config:cache 2>&1 || echo "⚠️  Config cache skipped"
php artisan route:cache 2>&1 || echo "⚠️  Route cache skipped"
php artisan view:cache 2>&1 || echo "⚠️  View cache skipped"
php artisan storage:link --force 2>/dev/null || true

echo "✅ DataNarasi starting on port $PORT"

# ---- 6. Start supervisor — THIS MUST ALWAYS RUN ----
exec /usr/bin/supervisord -c /etc/supervisord.conf
