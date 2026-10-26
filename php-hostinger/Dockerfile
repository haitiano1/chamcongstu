FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libcurl4-openssl-dev \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install -j2 pdo_mysql gd curl \
 && a2enmod headers rewrite \
 && rm -rf /var/lib/apt/lists/*
RUN printf 'upload_max_filesize=32M\npost_max_size=40M\nmemory_limit=256M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/nhansu.ini
