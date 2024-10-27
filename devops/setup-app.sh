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
# leave proof that migrations have been run
sudo -Hu ubuntu php /var/www/app/artisan migrate --force 2>/tmp/migration-error.log && touch /tmp/migrations-done
# Run production optimizations.

sudo -Hu ubuntu php /var/www/app/artisan optimize 2>/tmp/optimization-error.log && touch /tmp/optimizations-done
sudo -Hu ubuntu php /var/www/app/artisan event:cache 2>/tmp/event-cache-error.log && touch /tmp/event-cache-done

# Fix permissions.
touch -Hu ubuntu /var/www/app/storage/logs/laravel.log 2>/tmp/laravel-log-error.log && touch /tmp/laravel-log-done
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
