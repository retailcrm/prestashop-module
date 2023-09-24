FROM php:7.2-fpm

RUN apt-get update

RUN apt-get install -y zlib1g-dev libpq-dev git libicu-dev libxml2-dev libpng-dev libjpeg-dev libmcrypt-dev libxslt-dev libfreetype6-dev unzip nano\
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-install zip \
    && docker-php-ext-install xml \
    && docker-php-ext-configure gd --with-png-dir=/usr/local/ --with-jpeg-dir=/usr/local/ --with-freetype-dir=/usr/local/ \
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