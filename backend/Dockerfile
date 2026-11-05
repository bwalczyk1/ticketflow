FROM php:8.5-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions bcmath gd intl pcntl pdo_mysql zip

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ticketflow.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-ticketflow.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
