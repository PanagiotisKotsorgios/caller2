FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod headers

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/health.php')===false?1:0);"
