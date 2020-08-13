FROM php:7

RUN set -eux ; \
    apt-get update -yqq &&  \
    apt-get install -yqq    \
    libmcrypt-dev           \
    libpq-dev               \
    librabbitmq-dev         \
    git                     \
    libzip-dev              \
    unzip                   \
    wget					\
	cron 					\
    rsyslog

RUN set -eux ; docker-php-ext-install bcmath sockets zip

RUN set -eux ; pecl install amqp

RUN set -eux ; docker-php-ext-enable amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY cron /etc/cron.d/sample
COPY composer.json /app/composer.json
COPY deamon.php /app/deamon.php

WORKDIR /app

CMD composer install && service rsyslog start && service cron start && touch /var/log/cron.log && tail -f /var/log/cron.log