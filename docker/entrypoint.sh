#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# Optimize Laravel for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration and routes..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Ensure SQLite database exists if needed
if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_PATH=${DB_DATABASE:-/var/www/html/database/database.sqlite}
    if [ ! -f "$DB_PATH" ]; then
        echo "Creating SQLite database file at $DB_PATH..."
        mkdir -p "$(dirname "$DB_PATH")"
        touch "$DB_PATH"
        chown www-data:www-data "$DB_PATH"
        chmod 664 "$DB_PATH"
        # Also ensure directory is writable for SQLite journal files
        chown www-data:www-data "$(dirname "$DB_PATH")"
        chmod 775 "$(dirname "$DB_PATH")"
    fi
fi

# Determine the role of this container
ROLE=${CONTAINER_ROLE:-app}

if [ "$ROLE" = "app" ]; then
    echo "Starting Nginx and PHP-FPM..."
    # Start Nginx in background
    nginx
    # Start PHP-FPM in foreground
    php-fpm
elif [ "$ROLE" = "worker" ]; then
    echo "Starting Laravel worker..."
    php artisan queue:work --verbose --tries=3 --timeout=90
elif [ "$ROLE" = "scheduler" ]; then
    echo "Starting Laravel scheduler..."
    while [ true ]
    do
      php artisan schedule:run --no-interaction &
      sleep 60
    done
else
    echo "Could not find a role for $ROLE"
    exit 1
fi
