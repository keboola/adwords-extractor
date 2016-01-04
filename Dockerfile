FROM keboola/base-php56
MAINTAINER Jakub Matejka <jakub@keboola.com>

WORKDIR /home

RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data