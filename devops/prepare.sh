# Prepares for the deployment. Called from CodeDeploy's appspec.yml.

touch /tmp/deployment-started

aws configure set default.region ap-southeast-1
aws ssm get-parameter --with-decryption --name /luckymillion/api/p/.env --output text --query 'Parameter.Value' >/tmp/production.env

# Download the service configuration files from Parameter Store
aws ssm get-parameter --with-decryption --name /luckymillion/api/p/supervisor.conf --output text --query 'Parameter.Value' >/tmp/supervisor.conf
aws ssm get-parameter --with-decryption --name /luckymillion/api/p/nginx.conf --output text --query 'Parameter.Value' >/tmp/nginx.conf
sudo mv /tmp/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
sudo mv /tmp/nginx.conf /etc/nginx/conf.d/web.conf

# unlink default nginx configuration
sudo unlink /etc/nginx/sites-enabled/default

# Enable the services to start on boot.
sudo systemctl enable --now nginx
sudo systemctl enable supervisor

# Completely empty the app directory before dumping the revision's files
# there to avoid any deployment failures.
rm -rf /var/www/app/
mkdir /var/www/app/
sudo chown ubuntu:ubuntu -R /var/www/app/

touch /tmp/deployment-cleared
