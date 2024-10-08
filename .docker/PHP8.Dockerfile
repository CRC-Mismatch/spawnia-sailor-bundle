ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}

RUN <<EOT
    set -eux
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive RUNLEVEL=1 apt-get upgrade -y -f --no-install-recommends
    apt-get install --no-install-recommends --no-install-suggests -y \
        gnupg \
        gpg \
        gpg-agent \
        lsb-release \
        wget
    apt-get install --no-install-recommends --no-install-suggests -y \
        ssh-client \
        unzip \
        procps \
        vim-nox \
        ca-certificates \
        libmcrypt4 \
        libmcrypt-dev \
        libssl-dev \
        libxml2-dev \
        icu-devtools \
        libicu-dev \
        libxslt1-dev \
        libxslt1.1 \
        libcurl4-openssl-dev \
        libzip4 \
        libzip-dev \
        git \
        libpq-dev
    docker-php-ext-install \
        bcmath \
        soap \
        zip \
        intl \
        opcache \
        xsl \
        sockets \
        pcntl
    pecl install pcov xdebug
    docker-php-ext-enable xdebug pcov
EOT

RUN <<EOT
    set -eux
    useradd -m -s /bin/bash -G www-data app
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --lts
    apt-get update
    apt-get install --no-install-recommends --no-install-suggests -y \
        gettext-base
    echo 'deb [trusted=yes] https://repo.symfony.com/apt/ /' | tee /etc/apt/sources.list.d/symfony-cli.list
    apt-get update && apt-get install symfony-cli
EOT
COPY config/php/php.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY config/php/pcov.ini /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
ARG PROJECT_PATH=/var/www/html
ENV PROJECT_PATH=${PROJECT_PATH}
WORKDIR $PROJECT_PATH
USER app
