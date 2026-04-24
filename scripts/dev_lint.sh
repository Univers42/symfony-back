#!/bin/sh
set -e
cd /var/www/html
rm -f src/Entity/Reservation.php src/Entity/ServiceHour.php
php bin/console app:models:generate 2>&1 | tail -n 3
echo "--- Lint errors (none = OK) ---"
for f in src/Entity/*.php src/Document/*.php; do
  out=$(php -l "$f" 2>&1 | grep -v "No syntax errors" || true)
  if [ -n "$out" ]; then
    echo "FAIL $f:"
    echo "$out"
  fi
done
echo "DONE"
