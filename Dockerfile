# ============================================
# Stage 1: Build frontend assets
# ============================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Install PHP (minimal) for composer — needed for Ziggy route generation
RUN apk add --no-cache php83 php83-phar php83-iconv php83-mbstring php83-openssl composer

# Copy composer files and install Ziggy (needed by vite build)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --ignore-platform-reqs 2>/dev/null || true

# Copy package files (cache layer)
COPY package.json package-lock.json* ./

# Install deps — allow legacy peer deps for version conflicts
RUN npm ci --legacy-peer-deps || npm install --legacy-peer-deps

# Copy source needed for build
COPY vite.config.js ./
COPY postcss.config.js* tailwind.config.js* ./
COPY resources/ ./resources/
COPY public/ ./public/

# Vite build
ENV NODE_ENV=production
RUN npm run build

# ============================================
# Stage 2: PHP production image
# ============================================
FROM php:8.3-fpm-alpine

# Install system deps in ONE layer
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng \
    libjpeg-turbo \
    libzip \
    icu-libs \
    oniguruma \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
    && apk del .build-deps \
    && rm -rf /tmp/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first (cache layer)
COPY composer.json composer.lock ./

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy rest of project
COPY . .

# Run composer scripts (post-autoload-dump)
RUN composer dump-autoload --optimize

# Copy built frontend from stage 1
COPY --from=frontend /app/public/build public/build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p storage/app/public/charts

# Nginx config
COPY deploy/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY deploy/supervisord.conf /etc/supervisord.conf

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php-custom.ini "$PHP_INI_DIR/conf.d/99-custom.ini"

# Entrypoint
COPY deploy/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
