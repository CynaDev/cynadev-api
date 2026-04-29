FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libpng-dev \
    libxml2-dev \
    nginx

RUN docker-php-ext-install pdo pdo_mysql intl zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN php bin/console cache:clear --env=prod --no-debug || true
RUN php bin/console cache:warmup --env=prod --no-debug || true

RUN chown -R www-data:www-data var public

COPY nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]