#!/bin/bash

# Create the storage directory.
# sudo mkdir -p /var/www/app/storage/{logs,app/public,framework/{views,sessions,testing,cache/{data,laravel-excel}}}
sudo mkdir -p /var/www/app/bootstrap/cache
sudo mkdir -p /var/www/app/storage/logs
sudo mkdir -p /var/www/app/storage/app/public
sudo mkdir -p /var/www/app/storage/framework/views
sudo mkdir -p /var/www/app/storage/framework/sessions
sudo mkdir -p /var/www/app/storage/framework/testing
sudo mkdir -p /var/www/app/storage/framework/cache/data

# Move the previously downloaded file to the right place.

mv /tmp/production.env /var/www/app/.env

sudo chown -R ubuntu:ubuntu /var/www/app/storage

# Run new migrations. While this is run on all instances, only the
# first execution will do anything. As long as we're using CodeDeploy's
# OneAtATime configuration we can't have a race condition.
sudo -Hu ubuntu php /var/www/app/artisan migrate --force

# Run production optimizations.
sudo -Hu ubuntu php /var/www/app/artisan optimize
sudo -Hu ubuntu php /var/www/app/artisan event:cache

# Fix permissions.
touch -Hu ubuntu /var/www/app/storage/logs/laravel.log
sudo chmod -R 775 /var/www/app/storage/{app,framework,logs}
sudo chmod -R 775 /var/www/app/bootstrap/cache
sudo chown -R ubuntu:ubuntu /var/www/app/

# Reload ec2-user to clear OPcache.
sudo systemctl restart nginx
sudo systemctl start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
# sudo supervisorctl reload

touch /tmp/deployment-done
