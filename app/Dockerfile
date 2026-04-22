FROM php:8.3-fpm-alpine AS php-ext-builder

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        imagemagick-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        sqlite-dev && \
    docker-php-ext-install -j"$(nproc)" calendar pdo_sqlite && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j"$(nproc)" gd intl zip && \
    pecl install imagick && \
    docker-php-ext-enable imagick

FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        curl \
        dcron \
        dumb-init \
        freetype \
        icu-data-full \
        icu-libs \
        imagemagick \
        libgomp \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        nginx \
        shadow \
        sqlite \
        tzdata

COPY --from=php-ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY . .
COPY nginx.conf /etc/nginx/nginx.conf
COPY nginx.default.conf /etc/nginx/http.d/default.conf

RUN rm -f /var/www/html/nginx.conf /var/www/html/nginx.default.conf && \
    cp /var/www/html/cronjobs /etc/cron.d/cronjobs && \
    sed -i 's/\r$//' /etc/cron.d/cronjobs && \
    chmod 0644 /etc/cron.d/cronjobs && \
    /usr/bin/crontab /etc/cron.d/cronjobs && \
    mkdir -p /var/log/cron && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/startup.sh && \
    printf 'pm.max_children = 15\npm.max_requests = 500\n' >> /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 80

ENTRYPOINT ["dumb-init", "--"]

HEALTHCHECK --interval=2m --timeout=2s --start-period=20s --start-interval=5s --retries=3 \
    CMD ["curl", "-fsS", "http://127.0.0.1/health.php"]

CMD ["/var/www/html/startup.sh"]
