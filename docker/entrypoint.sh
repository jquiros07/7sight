#!/usr/bin/env sh
set -e

# storage/ and bootstrap/cache are bind-mounted from the host, so their
# ownership follows the host user, not the image. php-fpm's workers run as
# www-data, which usually isn't that owner or its group, so give write access
# to everyone here rather than relying on baked-in image permissions.
chmod -R o+rwX storage bootstrap/cache

if [ "$AUTO_MIGRATE" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
