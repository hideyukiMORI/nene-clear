# syntax=docker/dockerfile:1

# NeNe Clear — single production-like app image.
# Builds the React/Vite admin SPA, then serves it together with the PHP API on
# one port (8384) via the PHP built-in server. See docker-compose.yml.

# ---------------------------------------------------------------------------
# Stage 1: build the admin SPA → public_html/assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
# vite.config.ts reads NENE_CLEAR_* from the project-root .env; the build itself
# is static (no proxy/backend needed), so only the frontend sources are required.
COPY frontend ./frontend
RUN mkdir -p public_html \
    && cd frontend \
    && npm ci \
    && npm run build
# → /app/public_html/assets (build.outDir = ../public_html/assets, emptyOutDir)

# ---------------------------------------------------------------------------
# Stage 2: PHP runtime serving SPA + API
# ---------------------------------------------------------------------------
FROM php:8.4-cli AS app

# System libs for the PHP extensions we need:
#   pdo_mysql (default DB) · pdo_pgsql (DB_ADAPTER=pgsql) · mbstring · curl.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libonig-dev libpq-dev libzip-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (pinned major).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# NENE2 framework — composer.json references it as a path repo (../NENE2,
# symlink: true), so it must live next to the app at /NENE2 and persist at
# runtime. Public repo; cloned the same way CI does.
RUN git clone --depth=1 https://github.com/hideyukiMORI/NENE2.git /NENE2

WORKDIR /app

# Install PHP deps first (better layer caching). dev deps are kept on purpose:
# phinx (require-dev) runs migrations from the entrypoint.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Application source (vendor/node_modules/etc. excluded via .dockerignore).
COPY . .

# Re-run to wire autoloading for the now-present src/ and finalise.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p var

# Built SPA from stage 1.
COPY --from=frontend /app/public_html/assets ./public_html/assets

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8384
ENTRYPOINT ["entrypoint.sh"]
