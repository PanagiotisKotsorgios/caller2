FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip mbstring xmlreader \
    && a2enmod headers \
    && printf 'upload_max_filesize=20M\npost_max_size=22M\nmemory_limit=256M\nmax_execution_time=180\n' > /usr/local/etc/php/conf.d/crm.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/health.php')===false?1:0);"
