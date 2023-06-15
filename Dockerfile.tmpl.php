<?php
$var = getopt('', ['version:', 'dockerfile:']);
$isApacheImage = explode('/', $var['dockerfile'])[1] === 'apache';
$RoundcubeVer = explode('-', $var['version'])[0];
$isMinorVerLt5 = (intval(explode('.', $RoundcubeVer)[1]) < 5);
$isMinorVerGt5 = (intval(explode('.', $RoundcubeVer)[1]) > 5);
$phpVer = $isMinorVerLt5 ? '7.4' : '8.2';
$alpineVer = $isMinorVerLt5 ? '3.16' : '3.18';
?>
# AUTOMATICALLY GENERATED
# DO NOT EDIT THIS FILE DIRECTLY, USE /Dockerfile.tmpl.php

# https://hub.docker.com/_/php
<? if ($isApacheImage) { ?>
FROM php:<?= $phpVer; ?>-apache
<? } else { ?>
FROM php:<?= $phpVer; ?>-fpm-alpine<?= $alpineVer."\n"; ?>
<? } ?>

ARG roundcube_ver=<?= $RoundcubeVer."\n"; ?>
ARG s6_overlay_ver=3.1.5.0


# Install s6-overlay
RUN curl -fL -o /tmp/s6-overlay-noarch.tar.xz \
         https://github.com/just-containers/s6-overlay/releases/download/v${s6_overlay_ver}/s6-overlay-noarch.tar.xz \
 && curl -fL -o /tmp/s6-overlay-bin.tar.xz \
         https://github.com/just-containers/s6-overlay/releases/download/v${s6_overlay_ver}/s6-overlay-x86_64.tar.xz \
 && tar -xf /tmp/s6-overlay-noarch.tar.xz -C / \
 && tar -xf /tmp/s6-overlay-bin.tar.xz -C / \
 && rm -rf /tmp/*

ENV S6_KEEP_ENV=1 \
    S6_BEHAVIOUR_IF_STAGE2_FAILS=2 \
    S6_CMD_WAIT_FOR_SERVICES=1


# Install required libraries and PHP extensions
<? if ($isApacheImage) { ?>
RUN apt-get update \
 && apt-get upgrade -y \
<? } else { ?>
RUN apk update \
 && apk upgrade \
<? } ?>
 && update-ca-certificates \
<? if ($isApacheImage) { ?>
 && apt-get install -y --no-install-recommends --no-install-suggests \
            inetutils-syslogd \
            gnupg \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            libpq5 libodbc1 libsybdb5 \
            libaspell15 \
            libicu<?= $isMinorVerLt5 ? '67' : '72'; ?> \
            libldap-2.<?= $isMinorVerLt5 ? '4-2' : '5-0'; ?> libsasl2-2 \
            libjpeg62-turbo libpng16-16 libfreetype6 \
            libzip4 \
<? } else { ?>
 && apk add --no-cache --virtual .plugin-deps \
        gnupg \
 && apk add --no-cache --virtual .php-ext-deps \
        libpq unixodbc freetds \
        aspell-libs \
        icu-libs \
        libldap \
        libjpeg-turbo libpng freetype \
        libzip \
        zlib \
<? } ?>
    \
<? if ($isApacheImage) { ?>
 && buildDeps=" \
      libpq-dev unixodbc-dev freetds-dev \
      libpspell-dev \
      libicu-dev \
      libldap2-dev \
      libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
      libzip-dev \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $buildDeps \
<? } else { ?>
 && apk add --no-cache --virtual .build-deps \
<? if ($phpVer === '8.2') { ?>
        linux-headers \
<? } ?>
        postgresql-dev unixodbc-dev freetds-dev \
        aspell-dev \
        icu-dev \
        openldap-dev \
        libjpeg-turbo-dev libpng-dev freetype-dev \
        libzip-dev \
        zlib-dev \
<? } ?>
    \
<? if ($isApacheImage) { ?>
 && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
 && docker-php-ext-configure pdo_dblib --with-libdir=lib/x86_64-linux-gnu \
<? } ?>
<? if ($isApacheImage) { ?>
 && docker-php-ext-configure gd --with-jpeg=/usr/include/ \
                                --with-freetype=/usr/include/ \
<? } else { ?>
 && docker-php-ext-configure gd --with-jpeg=/usr/include/ \
                                --with-freetype=/usr/include/ \
<? } ?>
 && docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
 && docker-php-ext-install \
           exif \
           gd \
           intl \
           ldap \
           opcache \
           pdo_mysql pdo_pgsql pdo_odbc pdo_dblib \
           pspell \
           sockets \
           zip \
<? if ($isApacheImage) { ?>
    \
 && a2enmod expires \
            headers \
            rewrite \
<? } ?>
    \
 # Cleanup stuff
<? if ($isApacheImage) { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            $buildDeps \
 && rm -rf /var/lib/apt/lists/*
<? } else { ?>
 && apk del .build-deps \
 && rm -rf /var/cache/apk/*
<? } ?>


# Install Roundcube
RUN curl -fL -o /tmp/roundcube.tar.gz \
         https://github.com/roundcube/roundcubemail/releases/download/${roundcube_ver}/roundcubemail-${roundcube_ver}.tar.gz \
 && tar -xzf /tmp/roundcube.tar.gz -C /tmp/ \
 && rm -rf /app \
 && mv /tmp/roundcubemail-${roundcube_ver} /app \
    \
 # Install Composer to resolve Roundcube dependencies
 && curl -fL -o /tmp/composer-setup.php \
          https://getcomposer.org/installer \
 && curl -fL -o /tmp/composer-setup.sig \
          https://composer.github.io/installer.sig \
 && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { echo 'Invalid installer' . PHP_EOL; exit(1); }" \
 && php /tmp/composer-setup.php --install-dir=/tmp --filename=composer \
    \
 # Install tools for building
<? if ($isApacheImage) { ?>
 && apt-get update \
 && toolDeps=" \
        git \
        unzip \
    " \
 && apt-get install -y --no-install-recommends --no-install-suggests \
            $toolDeps \
<? } else { ?>
 && apk add --update --no-cache --virtual .tool-deps \
        git \
        file \
<? } ?>
    \
 # Resolve Roundcube Composer dependencies
 && mv /app/composer.json-dist /app/composer.json \
 && cd /app/ \
<? if ($isMinorVerLt5) { ?>
 && /tmp/composer config allow-plugins.roundcube/plugin-installer true \
<? } ?>
 && (/tmp/composer suggests --list | xargs -i /tmp/composer require {}) \
<? if ($isMinorVerGt5) { ?>
 && /tmp/composer require roundcube/classic \
 && /tmp/composer require roundcube/larry \
<? } ?>
 && /tmp/composer install --no-dev --optimize-autoloader --no-progress \
    \
 # Resolve Roundcube JS dependencies
 && /app/bin/install-jsdeps.sh \
    \
 # Make default Roundcube configuration log to syslog
 && sed -i -r 's/^([^\s]{9}log_driver[^\s]{2} =) [^\s]+$/\1 "syslog";/g' \
        /app/config/defaults.inc.php \
    \
 # Setup serve directories
 && cd /app/ \
 && ln -sn ./public_html /app/html \
 && rm -rf /var/www \
 && ln -s /app /var/www \
    \
 # Set correct owner
 && chown -R www-data:www-data /app /var/www \
    \
 # Cleanup stuff
 && (find /app/ -name .travis.yml -type f -prune | \
        while read d; do rm -rf $d; done) \
 && (find /app/ -name .gitignore -type f -prune | \
        while read d; do rm -rf $d; done) \
 && (find /app/ -name .git -type d -prune | \
        while read d; do rm -rf $d; done) \
 && (find /app/ -name .github -type d -prune | \
        while read d; do rm -rf $d; done) \
<? if ($isApacheImage) { ?>
 && apt-get purge -y --auto-remove \
                  -o APT::AutoRemove::RecommendsImportant=false \
            $toolDeps \
 && rm -rf /var/lib/apt/lists/* \
<? } else { ?>
 && apk del .tool-deps \
 && rm -rf /var/cache/apk/* \
<? } ?>
           /app/temp/* \
           /root/.composer \
           /tmp/*


# Install configurations
COPY rootfs /

# Fix executable rights
RUN chmod +x /etc/s6-overlay/s6-rc.d/*/run \
             /docker-entrypoint.sh \
<? if ($isApacheImage) { ?>
    \
 # Fix container entrypoint shell usage
 && sed -i -e 's,^#!/bin/sh,#!/bin/bash,' /docker-entrypoint.sh \
<? } ?>
    \
 # Prepare directory for SQLite database
 && mkdir -p /var/db \
 && chown -R www-data:www-data /var/db

ENV PHP_OPCACHE_REVALIDATION=0 \
<? if (intval(explode('.', $phpVer)[0]) >= 8) { ?>
    PHP_OPCACHE_JIT_BUFFER_SIZE=100M \
<? } ?>
    SHARE_APP=0


WORKDIR /var/www

ENTRYPOINT ["/init", "/docker-entrypoint.sh"]

<? if ($isApacheImage) { ?>
CMD ["apache2-foreground"]
<? } else { ?>
CMD ["php-fpm"]
<? } ?>
