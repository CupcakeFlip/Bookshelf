FROM php:8.4-apache

# Install only the PHP extensions required by the PDO database layer and APIs.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql curl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Use a valid Danish timezone identifier for PHP date and session timestamps.
RUN printf 'date.timezone = Europe/Copenhagen\n' > /usr/local/etc/php/conf.d/99-bookshelf-timezone.ini

WORKDIR /var/www/html
