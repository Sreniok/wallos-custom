#!/bin/sh

set -euo pipefail

echo "Startup script is running..." > /var/log/startup.log

# Default the PUID and PGID environment variables to 82, otherwise
# set to the user defined ones.
PUID=${PUID:-82}
PGID=${PGID:-82}

# Change the www-data user id and group id to be the user-specified ones
groupmod -o -g "$PGID" www-data
usermod -o -u "$PUID" www-data

BACKUP_PATH=${WALLOS_BACKUP_PATH:-/var/www/backups}

mkdir -p /tmp
chmod 1777 /tmp

install -d -o www-data -g www-data -m 750 /var/www/html/.tmp /var/www/html/db "$BACKUP_PATH"
install -d -o www-data -g www-data -m 755 /var/www/html/images/uploads/logos /var/www/html/images/uploads/logos/avatars
chown -R www-data:www-data /var/www/html/.tmp /var/www/html/db /var/www/html/images/uploads/logos "$BACKUP_PATH"
find /var/www/html/.tmp /var/www/html/db "$BACKUP_PATH" -type d -exec chmod 750 {} \;
find /var/www/html/.tmp /var/www/html/db "$BACKUP_PATH" -type f -exec chmod 640 {} \;
find /var/www/html/images/uploads/logos -type d -exec chmod 755 {} \;
find /var/www/html/images/uploads/logos -type f -exec chmod 644 {} \;

# PIDs we’ll track
PHP_FPM_PID=
NGINX_PID=
CROND_PID=
shutdown_in_progress=0

shutdown_once() {
  exit_signal=$?
  kill_signal=$(kill -l "$exit_signal" 2>/dev/null || echo "$exit_signal")

  [ "$shutdown_in_progress" -eq 1 ] && return 0
  shutdown_in_progress=1

  echo "Got signal: $kill_signal - Shutting down gracefully... "
  # nginx wants QUIT for graceful
  nginx -s quit || true
  # php-fpm graceful quit as well
  [ -n "${PHP_FPM_PID}" ] && kill -QUIT "${PHP_FPM_PID}" 2>/dev/null || true
  # cron can just get TERM
  [ -n "${CROND_PID}" ] && kill -TERM "${CROND_PID}" 2>/dev/null || true
  echo "Graceful shutdown complete."
}

# Handle all common stop signals
trap 'shutdown_once' SIGTERM SIGINT SIGQUIT

# Start both PHP-FPM and Nginx
echo "Launching php-fpm"
php-fpm -F &
PHP_FPM_PID=$!

echo "Launching crond"
crond -f &
CROND_PID=$!

echo "Launching nginx"
nginx -g 'daemon off;' &
NGINX_PID=$!

# Wait one second before running scripts
sleep 1

# Create database if it does not exist
/usr/local/bin/php /var/www/html/endpoints/cronjobs/createdatabase.php

# Perform any database migrations
/usr/local/bin/php /var/www/html/endpoints/db/migrate.php

# Keep runtime data writable only by www-data where possible.
find /var/www/html/db -type d -exec chmod 750 {} \;
find /var/www/html/db -type f -exec chmod 640 {} \;
find /var/www/html/images/uploads/logos -type d -exec chmod 755 {} \;
find /var/www/html/images/uploads/logos -type f -exec chmod 644 {} \;

# Remove crontab for the user
crontab -d -u root

# Install the current cron schedule from the mounted app directory.
chmod 0644 /var/www/html/cronjobs
crontab /var/www/html/cronjobs

# Run updatenextpayment.php and wait for it to finish
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

# Run updateexchange.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

# Run checkforupdates.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/checkforupdates.php

# Essentially wait until all child processes exit
wait
