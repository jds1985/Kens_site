FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY members-demo/php/apache.conf /etc/apache2/sites-available/000-default.conf
COPY members-demo/php/demo.ini /usr/local/etc/php/conf.d/demo.ini
COPY members-demo/php/demo-guard.php members-demo/php/prepend-demo.php members-demo/php/seed.php members-demo/php/wait-db.php /opt/demo/
COPY members-demo/php/entrypoint.sh /opt/demo/entrypoint.sh
RUN chmod 0755 /opt/demo/entrypoint.sh
COPY members-fresh-20261107/members /var/www/html
COPY members-fresh-20261107/routing/members.htaccess /var/www/html/.htaccess
RUN mkdir -p /var/www/html/storage/pdfs \
    && chown -R www-data:www-data /var/www/html/storage

ENV MEMBERS_DEMO=1
ENV KEN_MEMBERS_CONFIG=/opt/demo/demo-config.php

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/opt/demo/entrypoint.sh"]
