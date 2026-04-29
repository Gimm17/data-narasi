# ── Build stage ──
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Production stage ──
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip intl bcmath opcache pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project
COPY . .

# Copy built frontend from build stage
COPY --from=frontend /app/public/build public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx config
COPY deploy/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config (nginx + php-fpm + queue worker)
COPY deploy/supervisord.conf /etc/supervisord.conf

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php-custom.ini "$PHP_INI_DIR/conf.d/99-custom.ini"

# Expose port (Railway assigns $PORT, Nginx listens on it)
EXPOSE ${PORT:-8080}

# Entrypoint: run migrations + cache + start supervisor
COPY deploy/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
