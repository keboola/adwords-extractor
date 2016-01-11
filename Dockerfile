FROM keboola/base-php56
MAINTAINER Jakub Matejka <jakub@keboola.com>

yum -y --enablerepo=epel,remi,remi-php56 install php-soap

ADD . /code
WORKDIR /code

RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data