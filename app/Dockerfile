FROM wallos-php-base:latest

WORKDIR /var/www/html

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
