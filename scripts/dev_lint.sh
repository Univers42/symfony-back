#!/bin/sh
set -e
cd /var/www/html
php bin/console app:models:validate 2>&1 | tail -n 5
echo "--- Lint errors (none = OK) ---"
find src -name '*.php' | while IFS= read -r f; do
  out=$(php -l "$f" 2>&1 | grep -v "No syntax errors" || true)
  if [ -n "$out" ]; then
    echo "FAIL $f:"
    echo "$out"
  fi
done
echo "DONE"
