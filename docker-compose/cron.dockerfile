FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    oniguruma-dev

RUN docker-php-ext-install pdo pdo_mysql bcmath

COPY docker-compose/crontab /etc/crontabs/root

CMD ["crond", "-f"]
