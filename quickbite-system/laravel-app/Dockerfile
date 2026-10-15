
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libonig-dev \
    libsqlite3-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring bcmath pcntl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    && rm -f bootstrap/cache/config.php \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD ["sh", "-c", "php artisan config:clear && touch database/database.sqlite && php artisan migrate --force && php artisan db:seed --class=QuickBiteSeeder --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
