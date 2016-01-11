FROM keboola/base-php56
MAINTAINER Jakub Matejka <jakub@keboola.com>

WORKDIR /tmp
rpm -Uvh https://mirror.webtatic.com/yum/el6/latest.rpm && \
	rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm && \
	yum -y --enablerepo=epel,remi,remi-php56 upgrade && \
	yum -y --enablerepo=epel,remi,remi-php56 install \
		php-soap \
		&& \
	yum clean all

ADD . /code
WORKDIR /code

RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data