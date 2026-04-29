FROM php:8.2-fpm-alpine

# Install dependencies (ajuste selon tes besoins, ex: pdo_pgsql)
RUN apk add --no-cache libpng-dev libzip-dev zip unzip git nginx
RUN docker-php-ext-install pdo pdo_mysql gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Setup production
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN chown -R www-data:www-data var public

EXPOSE 80
# Lancer php-fpm ET nginx dans le conteneur
CMD php-fpm -D && nginx -g 'daemon off;'