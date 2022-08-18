FROM php:7.3.11-alpine

ENV env_path=/syncer

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR ${env_path}

COPY ./ ./

RUN composer install

ENTRYPOINT ["php", "/syncer/syncer"]
