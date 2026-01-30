# Stage 1: Build PHP dependencies
FROM php:8.3-fpm-alpine as php-builder

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    icu-dev \
    postgresql-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: Build Node assets (if needed, though this app seems to be an API)
FROM node:20-alpine as node-builder
WORKDIR /app
COPY . .
RUN npm install && npm run build

# Stage 3: Final Runtime Image
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Define build-time environment variable
ARG APP_ENV=production
ENV APP_ENV=$APP_ENV

# Install runtime dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng \
    libxml2 \
    oniguruma \
    icu \
    bash \
    libpq

# Install PHP extensions needed for runtime
RUN apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    postgresql-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd intl \
    && apk del .build-deps

# Copy from builder
COPY --from=php-builder /var/www/html /var/www/html
COPY --from=node-builder /app/public /var/www/html/public

# Copy Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
