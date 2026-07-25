#!/bin/sh
# Install runtime PostgreSQL lib before starting PHP-FPM
apk add --no-cache libpq
exec php-fpm -F
