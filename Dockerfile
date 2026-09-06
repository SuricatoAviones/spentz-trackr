FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM serversideup/php:8.5-fpm-nginx AS wayfinder
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
USER www-data

RUN php artisan wayfinder:generate --with-form

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

FROM serversideup/php:8.5-fpm-nginx
WORKDIR /var/www/html

USER root
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY --chown=www-data:www-data . .
USER www-data

RUN php artisan package:discover --ansi && php artisan storage:link

EXPOSE 80