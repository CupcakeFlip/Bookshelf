FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install pdo pdo_mysql curl gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

RUN printf 'date.timezone = Europe/Copenhagen\n' \
    > /usr/local/etc/php/conf.d/99-bookshelf-timezone.ini

RUN mkdir -p /var/cache/bookshelf/covers \
    && chown -R www-data:www-data /var/cache/bookshelf/covers

WORKDIR /var/www/html
