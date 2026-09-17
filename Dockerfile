FROM php:8.3-apache-bookworm

# The official PHP image already includes mbstring and XMLReader.
# We only compile the two extensions this CRM actually needs in addition:
#   - pdo_mysql for MySQL
#   - zip for XLSX/ZipArchive imports
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libzip-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql zip; \
    php -m | grep -qi '^mbstring$'; \
    php -m | grep -qi '^xmlreader$'; \
    php -m | grep -qi '^pdo_mysql$'; \
    php -m | grep -qi '^zip$'; \
    a2enmod headers; \
    printf 'upload_max_filesize=20M\npost_max_size=22M\nmemory_limit=256M\nmax_execution_time=180\n' > /usr/local/etc/php/conf.d/crm.ini; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=5 CMD php -r "exit(@file_get_contents('http://127.0.0.1/health.php')===false?1:0);"
