FROM php:8.2-cli

# pdo_pgsql needs libpq's dev headers to compile against.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

# Render sets $PORT; the app must listen on it.
CMD php -S 0.0.0.0:${PORT:-8080} -t .
