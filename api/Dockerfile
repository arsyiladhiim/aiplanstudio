FROM php:8.3-fpm-alpine

# 1. Compile extensions (needs dev headers)
RUN apk add --no-cache \
        libpq-dev oniguruma-dev libzip-dev \
        autoconf dpkg-dev file gcc g++ make pkgconf re2c \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    # 2. Purge build deps
    && apk del libpq-dev oniguruma-dev libzip-dev autoconf dpkg-dev file gcc g++ make pkgconf re2c \
    # 3. Re-add runtime libs separately (survives the del above)
    && apk add --no-cache libpq libzip \
    && rm -rf /var/cache/apk/* /tmp/pear /tmp/php*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV PATH="/usr/bin/composer:${PATH}"

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 9000
CMD ["php-fpm", "-F"]
