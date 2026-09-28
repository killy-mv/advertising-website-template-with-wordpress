FROM wordpress:php8.3-fpm

# PHP extension for talking to Redis (object cache)
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# PHP settings: upload limits for food photos, opcache for speed
COPY php.ini /usr/local/etc/php/conf.d/zz-restaurant.ini
# More PHP workers than the default 5
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf
