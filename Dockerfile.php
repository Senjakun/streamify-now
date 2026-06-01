FROM php:8.1-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN apk add --no-cache curl

WORKDIR /var/www/api
