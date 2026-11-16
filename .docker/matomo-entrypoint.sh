#!/bin/sh
# Same idea as the official Matomo image: populate the /var/www/html volume on first start.
set -e
if [ ! -e /var/www/html/matomo.php ]; then
  tar cf - --one-file-system -C /usr/src/matomo . | tar xf - -C /var/www/html
  mkdir -p /var/www/html/tmp
  chown -R www-data:www-data /var/www/html/config /var/www/html/tmp
fi
exec "$@"
