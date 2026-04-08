FROM php:8.2-cli
RUN apt-get update && apt-get install -y libpq-dev unzip curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs || true
CMD php -S 0.0.0.0:${PORT:-8080}