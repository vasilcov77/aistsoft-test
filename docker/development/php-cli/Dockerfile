FROM php:8.5-cli

ARG USER_ID='1000'
ENV USER_ID=${USER_ID}

ARG GROUP_ID='1000'
ENV GROUP_ID=${GROUP_ID}

# Сначала скопируйте install-php-extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql intl zip sockets \
    && git clone --depth 1 --branch 6.3.0 --recurse-submodules https://github.com/phpredis/phpredis.git /tmp/phpredis-src \
    && install-php-extensions /tmp/phpredis-src xdebug/xdebug@3.5.0 \
    && rm -rf /var/lib/apt/lists/* /tmp/phpredis-src \
    && apt-get purge -y --auto-remove git

COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

# Создаём группу и пользователя с нужными ID (если таких ещё нет)
RUN groupadd -g ${GROUP_ID} appuser 2>/dev/null || true \
    && useradd -u ${USER_ID} -g ${GROUP_ID} -m -s /bin/bash appuser 2>/dev/null || true \
    && mkdir -p /app \
    && chown -R ${USER_ID}:${GROUP_ID} /app

USER appuser

# --- Настройка Composer: зеркало Packagist + отключение официального репозитория ---
RUN composer config -g repo.packagist composer https://mirrors.tencent.com/composer/ \
    && composer config -g repo.packagist.org false
#cat ~/.composer/config.json \

RUN echo "alias art='php artisan'" >> ~/.bashrc

USER root

WORKDIR /app