FROM php:8.4-fpm-alpine

LABEL maintainer="patrimoine"

# Packages + PHP extensions
RUN apk add --no-cache \
        git curl zip unzip \
        libzip-dev libpq-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev oniguruma-dev \
        su-exec \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure pcntl \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql \
        zip gd intl bcmath opcache pcntl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
