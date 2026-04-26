#!/bin/sh
curl -s -i -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@baas.test","password":"admin1234"}' \
  http://apache/api/login
echo
echo "---"
php /var/www/html/bin/console debug:firewall api 2>&1 | tail -n 40
