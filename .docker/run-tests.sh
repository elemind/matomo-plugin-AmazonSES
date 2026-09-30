#!/bin/sh
# Runs the AmazonSES tests inside a Matomo git checkout mounted at /matomo.
# Usage (from the repo root): make test [SUITE=unit|integration]
set -e
cd /matomo

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --no-progress --prefer-dist
fi

if [ ! -f config/config.ini.php ]; then
  cat > config/config.ini.php <<INI
; <?php exit; ?> DO NOT REMOVE THIS LINE
[database]
host = "db"
username = "root"
password = "root"
dbname = "matomo_tests"
tables_prefix = ""
charset = "utf8mb4"

[database_tests]
host = "db"
username = "root"
password = "root"
dbname = "matomo_tests"
tables_prefix = ""
charset = "utf8mb4"

[tests]
http_host = "localhost"
request_uri = "/"
remote_addr = "127.0.0.1"

[General]
salt = "amazonses-tests"
trusted_hosts[] = "localhost"

[Development]
enabled = 1
INI
fi

case "${SUITE:-all}" in
  unit) ./console tests:run --options="--colors=always" plugins/AmazonSES/tests/Unit ;;
  integration) ./console tests:run --options="--colors=always" plugins/AmazonSES/tests/Integration ;;
  *) ./console tests:run --options="--colors=always" AmazonSES ;;
esac
