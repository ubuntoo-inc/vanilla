#!/bin/bash

echo "starting php"
service php8.0-fpm starts

chown www-data:www-data -R /ebs
chmod 777 /ebs/vanilla/conf
chmod 777 /ebs/vanilla/uploads
chmod 777 /ebs/vanilla/cache
cp /ebs/nginx/conf/fastcgi.conf.tpl /etc/nginx/fastcgi.conf
cp /ebs/nginx/conf/vanilla-web.conf /etc/nginx/sites-available
ln -s /etc/nginx/sites-available/vanilla-web.conf /etc/nginx/sites-enabled
#service nginx start
echo "starting nginx"
service nginx start

tail -f /dev/null
