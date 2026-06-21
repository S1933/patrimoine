#!/bin/sh
set -e

# Ensure Laravel writable dirs exist and are owned by www-data (dev bind-mounts).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# PHP-FPM master must run as root to manage workers and write its error log.
# Other commands (artisan, queues, shell) run as www-data to match file ownership.
if [ "$1" = "php-fpm" ] || [ "$1" = "php-fpm8.3" ] || [ "$1" = "php-fpm8.2" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"
