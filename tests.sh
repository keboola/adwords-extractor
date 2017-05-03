#!/bin/sh
php --version \
  && php composer.phar --version \
  && ./vendor/bin/phpcs --standard=psr2 -n --ignore=vendor . \
  && ./vendor/bin/phpstan analyse -l 5 src tests \
  && ./vendor/bin/phpunit -c ./phpunit.xml.dist