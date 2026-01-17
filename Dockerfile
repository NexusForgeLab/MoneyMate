FROM php:8.2-apache
RUN a2enmod rewrite headers
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev pkg-config \
  && docker-php-ext-install pdo pdo_sqlite \
  && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p /var/www/html/data \
  && chown -R www-data:www-data /var/www/html/data \
  && chmod -R 775 /var/www/html/data
