#!/bin/bash

# Create the storage directory.
# sudo mkdir -p /var/www/app/storage/{logs,app/public,framework/{views,sessions,testing,cache/{data,laravel-excel}}}
sudo -Hu ubuntu mkdir -p /var/www/app/bootstrap/cache
sudo -Hu ubuntu mkdir -p /var/www/app/storage/logs
sudo -Hu ubuntu mkdir -p /var/www/app/storage/app/public
sudo -Hu ubuntu mkdir -p /var/www/app/storage/framework/views
sudo -Hu ubuntu mkdir -p /var/www/app/storage/framework/sessions
sudo -Hu ubuntu mkdir -p /var/www/app/storage/framework/testing
sudo -Hu ubuntu mkdir -p /var/www/app/storage/framework/cache/data

sudo chmod -R 775 /var/www/app/bootstrap/cache
# Move the previously downloaded file to the right place.

mv /tmp/production.env /var/www/app/.env

sudo chown -R ubuntu:ubuntu /var/www/app/storage

# Run new migrations. While this is run on all instances, only the
# first execution will do anything. As long as we're using CodeDeploy's
# OneAtATime configuration we can't have a race condition.
# leave proof that migrations have been run
sudo -Hu ubuntu php /var/www/app/artisan migrate -v --force >/tmp/migration.log 2>&1 && touch /tmp/migrations-done
# Run production optimizations.

# sudo -Hu ubuntu php /var/www/app/artisan optimize >/tmp/optimization.log 2>&1 && touch /tmp/optimization-done

sudo -Hu ubuntu php /var/www/app/artisan optimize:clear >/tmp/optimization-clear.log 2>&1 && touch /tmp/optimization-clear-done
# sudo -Hu ubuntu php /var/www/app/artisan event:cache >/tmp/event-cache.log 2>&1 && touch /tmp/event-cache-done

# Fix permissions.
touch /var/www/app/storage/logs/laravel.log >/tmp/laravel-log.log 2>&1 && touch /tmp/laravel-log-done
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
