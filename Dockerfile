FROM composer as builder
WORKDIR /app/
COPY composer.* ./
RUN composer install --ignore-platform-reqs

FROM php:7.3-fpm

RUN apt-get update -y \
    && apt-get install -y nginx

COPY nginx.conf /etc/nginx/sites-enabled/default

WORKDIR /var/www/html/
EXPOSE 80
RUN mkdir cache \
  && chown -R www-data:www-data cache
VOLUME ./apps
ENTRYPOINT service nginx start && php-fpm
COPY --from=builder /app/vendor ./vendor
COPY ./public_html ./public_html
COPY ./src ./src
