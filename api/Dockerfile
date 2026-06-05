FROM php:8.4-cli

# System deps
RUN apt-get update -qq && apt-get install -y -qq \
    libpq-dev libzip-dev libexif-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql \
        zip exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
