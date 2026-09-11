# --- Etapa 0: base PHP + extensiones (compartida por las etapas con PHP) ---
FROM serversideup/php:8.5-fpm-nginx AS php-base

USER root
# install-php-extensions compila gd e intl desde fuente con `make -j$(nproc)`. En un VPS
# pequeño (2 GB, con Dokploy + Traefik + MySQL encima) los gcc en paralelo agotan la RAM y
# el kernel mata el build ("Killed", exit 137). IPE_PROCESSOR_COUNT=1 lo compila en serie.
# Además, al vivir en su propia etapa base, este paso corre ANTES del `npm run build`
# (la etapa frontend depende de wayfinder, que depende de esta) en vez de competir con él.
RUN IPE_PROCESSOR_COUNT=1 install-php-extensions bcmath gd intl
USER www-data

# --- Etapa 1: dependencias PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# --- Etapa 2: types TypeScript de Wayfinder (la etapa frontend no tiene PHP) ---
FROM php-base AS wayfinder
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
USER www-data

RUN php artisan wayfinder:generate --with-form

# --- Etapa 3: assets frontend (Vite/Inertia/React) ---
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=wayfinder /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /var/www/html/resources/js/wayfinder ./resources/js/wayfinder
ENV SKIP_WAYFINDER=1
RUN npm run build

# --- Etapa 4: runtime ---
FROM php-base
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --chown=www-data:www-data . .
# APP_KEY persistente + migraciones al arrancar
COPY --chmod=755 ./docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

RUN php artisan package:discover --ansi && php artisan storage:link

EXPOSE 80
