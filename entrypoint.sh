#!/bin/sh
set -eu
cd /var/www
php docker/runtime-check.php
if [ -s /run/secrets/jwt_secret ]; then
    JWT_SECRET=$(tr -d '\r\n' < /run/secrets/jwt_secret)
    export JWT_SECRET
fi
: "${JWT_SECRET:?Set the existing shared JWT secret; it is never generated at startup}"
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache
# Configuration belongs to this container and is supplied by Compose env_file.
rm -f bootstrap/cache/config.php
chown -R www-data:www-data storage bootstrap/cache
if [ "$#" -eq 0 ]; then set -- php-fpm -F; fi
if [ "$1" = php-fpm ]; then exec "$@"; fi
exec su-exec www-data "$@"
