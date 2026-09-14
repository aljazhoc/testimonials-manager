FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/app/public/uploads \
    && chown -R www-data:www-data /var/www/app \
    && chmod 777 /var/www/app/public/uploads || true
