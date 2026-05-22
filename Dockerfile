FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libpq-dev \
    && docker-php-ext-install curl mbstring pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN rm -f /var/www/html/config.php \
    && mkdir -p /var/www/html/assets/images/uploads \
    && chown -R www-data:www-data /var/www/html/assets/images/uploads

EXPOSE 80
