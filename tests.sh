#!/bin/sh
php --version \
  && /code/vendor/bin/phpcs --standard=psr2 -n --ignore=/code/vendor /code \
  && /code/vendor/bin/phpstan analyse -l 5 /code/src \
  && /code/vendor/bin/phpunit -c /code/phpunit.xml.dist
