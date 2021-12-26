FROM php:7.4-fpm

# используем apt-get вместо apt, чтобы не получать: WARNING: apt does not have a stable CLI interface. Use with caution in scripts.
RUN apt-get update

# чтобы при установке apt-пакетов не возникало предупреждения: debconf: delaying package configuration, since apt-utils is not installed
RUN apt install -y apt-utils

# Чтобы composer install не выдавал ошибку: Failed to download XXX from dist: The zip extension and unzip command are both missing, skipping.
RUN apt-get install -y \
    zip \
    libzip-dev
RUN docker-php-ext-configure zip \
    && docker-php-ext-install zip

# mysql
RUN docker-php-ext-install mysqli pdo_mysql

# postgresql
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql

RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# мне удобно в докер-контейнере иметь те же права, что и на хост-машине, поэтому создаю подобного пользователя в контейнере
ENV UID=1000
ENV GID=1000
ENV USER=www-data
ENV GROUP=www-data

RUN usermod -u $UID $USER && groupmod -g $GID $GROUP
USER $USER

