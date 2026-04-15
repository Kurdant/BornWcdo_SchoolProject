FROM php:8.2-fpm-alpine

WORKDIR /app

RUN apk add --no-cache \
    git \
    curl \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN chmod +x /usr/bin/composer

EXPOSE 9000

CMD ["php-fpm"]
