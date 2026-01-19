FROM php:8.2-apache

# Enable Apache Rewrite
RUN a2enmod rewrite headers

# Install Dependencies and Extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    pkg-config \
 && docker-php-ext-install pdo pdo_sqlite opcache \
 && rm -rf /var/lib/apt/lists/*

# Optimize PHP Config (Production Settings)
RUN echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache-recommended.ini \
 && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache-recommended.ini \
 && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache-recommended.ini \
 && echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/conf.d/opcache-recommended.ini \
 && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /var/www/html
COPY . /var/www/html

# Permissions
RUN mkdir -p /var/www/html/data \
  && chown -R www-data:www-data /var/www/html/data \
  && chmod -R 775 /var/www/html/data