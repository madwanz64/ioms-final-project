# Image aplikasi IOMS: PHP 8.3 + Apache (mod_php), document root = public/.
# Dibangun dari kondisi bersih dengan: docker compose up --build   (§5.1)
FROM php:8.3-apache

# Ekstensi yang dibutuhkan composer.json: pdo_mysql (fileinfo & mbstring sudah bawaan image).
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# PCOV: driver code coverage untuk laporan SonarQube (composer test:coverage).
# Ringan dan hanya mengumpulkan data saat PHPUnit memintanya.
RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer hanya dipakai untuk autoload & dev dependency (PHPUnit, PHPStan) — §4.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Layer dependency di-cache terpisah: hanya dibangun ulang bila composer.json/lock berubah.
# Dev dependency ikut dipasang agar unit + integration test bisa dijalankan di container (§5.2).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p public/uploads/products \
    && chown -R www-data:www-data public/uploads

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ioms.ini

EXPOSE 80
