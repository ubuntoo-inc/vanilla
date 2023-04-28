#!/bin/bash

echo "starting php"
service php7.3-fpm start
sleep 10

#service nginx start
echo "starting nginx"
nginx -g "daemon on;"

tail -f /dev/null
