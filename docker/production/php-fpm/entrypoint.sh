#!/bin/sh
set -e

# Initialize storage directory if empty
# -------------------------------------------------------------------------
# If the storage directory is empty, copy the initial contents
# and set the correct permissions.
# -------------------------------------------------------------------------
if [ ! "$(ls -A /var/www/storage)" ]; then
  echo "Initializing storage directory..."
  cp -R /var/www/storage-init/. /var/www/storage
  chown -R www-data:www-data /var/www/storage
fi

# Remove storage-init directory
rm -rf /var/www/storage-init

# Run Laravel migrations (against the default relational connection)
# -------------------------------------------------------------------------
php artisan migrate --force

# Clear and cache configurations for performance
# -------------------------------------------------------------------------
php artisan config:cache
php artisan route:cache

# Run the default command
exec "$@"
