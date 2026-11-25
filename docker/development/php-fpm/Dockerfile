FROM php:8.5-fpm

ARG USER_ID='1000'
ENV USER_ID=${USER_ID}

ARG GROUP_ID='1000'
ENV GROUP_ID=${GROUP_ID}

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis-6.3.0 xdebug-3.5.0 \
    && docker-php-ext-enable redis xdebug \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

RUN groupmod -g $GROUP_ID www-data \
 && usermod -u $USER_ID -g $GROUP_ID www-data

WORKDIR /app