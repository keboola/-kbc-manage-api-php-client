FROM php:7.1 as common
MAINTAINER Martin Halamicek <martin@keboola.com>
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
  && apt-get install unzip git -y

RUN echo "memory_limit = -1" >> /usr/local/etc/php/php.ini

RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && ln -s /root/composer.phar /usr/local/bin/composer

ADD ./ /code

WORKDIR /code
RUN composer install --prefer-dist --no-interaction

FROM common as dev

WORKDIR /code

RUN composer install \
    --prefer-dist \
    --no-interaction \
    --dev

FROM common as prod





