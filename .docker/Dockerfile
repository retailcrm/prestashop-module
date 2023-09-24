FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libpq-dev \
    git \
    libzip \
    libicu-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libxslt-dev \
    unzip nano \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-install zip \
    && docker-php-ext-install xml \
    && docker-php-ext-configure gd --with-jpeg=/usr/include/ --with-freetype=/usr/include/ \
    && docker-php-ext-install gd \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install soap \
    && docker-php-ext-install xsl \
    && docker-php-ext-install mbstring \
    && pecl install mcrypt-1.0.1 \
    && docker-php-ext-enable mcryp

RUN curl -sSL https://phar.phpunit.de/phpunit-6.phar -o /usr/bin/phpunit && chmod +x /usr/bin/phpunit \
    && curl -sSL https://getcomposer.org/composer.phar -o /usr/bin/composer && chmod +x /usr/bin/composer

WORKDIR /code