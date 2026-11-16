FROM php:8.3-fpm-alpine
RUN docker-php-ext-install pdo_pgsql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
