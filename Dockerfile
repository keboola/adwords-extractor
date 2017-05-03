FROM php:7.1
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update -q \
  && apt-get install unzip git libxml2-dev -y --no-install-recommends \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /root
RUN cd && curl -sS https://getcomposer.org/installer | php && ln -s /root/composer.phar /usr/local/bin/composer
RUN docker-php-ext-install soap

ADD . /code
WORKDIR /code

RUN composer install --prefer-dist --no-interaction

CMD php ./src/app.php run /data