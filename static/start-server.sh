#!/bin/bash

echo "starting php"
service php8.0-fpm start
sleep 10

#service nginx start
echo "starting nginx"
service nginx start

tail -f /dev/null
