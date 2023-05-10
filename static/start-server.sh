#!/bin/bash

echo "starting php"
service php8.0-fpm start
sleep 10

cp /ebs/nginx/conf/fastcgi.conf.tpl /etc/nginx/fastcgi.conf
cp /ebs/nginx/conf/vanilla-web.conf /etc/nginx/sites-available
ln -s /etc/nginx/sites-available/vanilla-web.conf /etc/nginx/sites-enabled
#service nginx start
echo "starting nginx"
service nginx start

tail -f /dev/null
