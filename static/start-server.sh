#!/bin/bash

echo "starting php"
service php8.0-fpm start
sleep 10

cp /ebs/nginx/conf/fastcgi.conf.tpl /etc/nginx/fastcgi.conf
#service nginx start
echo "starting nginx"
service nginx start

tail -f /dev/null
