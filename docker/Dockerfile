# syntax=docker/dockerfile:1
#
# Dev/test image for Phoenix. Bakes in the PHP extensions the app and its
# optional libraries need (mysqli; gd for the authenticatron 2FA QR code),
# Composer, and a MariaDB-compatible mysqldump (the backup engine shells out to
# `mysqldump`, and Oracle MySQL's dumper trips over MariaDB's information_schema
# — so we shim it to mariadb-dump, exactly as the smoke-test CI does).
#
# The application is NOT copied in: docker-compose.dev.yml mounts the working
# tree and runs docker/entrypoint.sh, so this image only needs rebuilding when
# the toolchain changes.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libpng-dev \
        mariadb-client \
        unzip \
    && docker-php-ext-install -j"$(nproc)" gd mysqli \
    && ln -sf "$(command -v mariadb-dump)" /usr/local/bin/mysqldump \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Run as an unprivileged user; the entrypoint owns /app for the copied tree.
RUN useradd --create-home --uid 1000 phoenix \
    && mkdir -p /app \
    && chown phoenix:phoenix /app
WORKDIR /app
USER phoenix

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=5 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/admin.php') === false ? 1 : 0);"
