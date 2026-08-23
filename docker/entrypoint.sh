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

# MediaMTX doesn't persist paths added via its API across restarts, so
# re-register every camera on every boot of this container too - keeps the
# two in sync regardless of which one restarts. Best-effort: never block
# startup over it (handles its own errors internally; `|| true` is just a
# backstop against an unexpected non-zero exit).
php artisan cameras:sync || true

exec "$@"
