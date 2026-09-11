#!/usr/bin/env bash
#
# Runtime entrypoint for the Phoenix dev container (docker-compose.dev.yml).
# Copies the read-only working tree into a writable, throwaway /app, installs
# dependencies, clears any config so /admin.php opens in installer mode, then
# serves public/ with PHP's built-in server.
set -euo pipefail

echo '>>> Copying the working tree and installing dependencies...'
mkdir -p /app
tar -C /repo --exclude=./vendor --exclude=./.git --exclude=./docker -cf - . |
    tar -C /app -xf -
cd /app
composer install --no-interaction --prefer-dist

# Start clean: with no custom config, /admin.php opens in installer mode.
rm -f config/phoenix.custom.php

cat <<'BANNER'

============================================================
  Phoenix:  http://localhost:8000/admin.php
  Installer DB ->  host: db  user: phoenix  pass: phoenix_pass  name: phoenix
============================================================
BANNER

exec php -S 0.0.0.0:8000 -t public
