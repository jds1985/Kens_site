#!/bin/sh
set -eu
mkdir -p /var/www/html/storage/pdfs
chown -R www-data:www-data /var/www/html/storage
# Wait and seed without the Apache prepend so a down database cannot
# turn the wait loop into an immediate guard failure through session setup.
php -d auto_prepend_file= /opt/demo/wait-db.php
php -d auto_prepend_file= /opt/demo/seed.php
chown -R www-data:www-data /var/www/html/storage
exec apache2-foreground
