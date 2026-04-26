#!/bin/sh
set -e
cd /var/www/html

echo "==> Cache clear"
rm -rf var/cache/dev
php bin/console cache:warmup --env=dev 2>&1 | tail -n 5

echo "==> Postgres database create (if missing)"
php bin/console doctrine:database:create --if-not-exists --no-interaction 2>&1 | tail -n 3

echo "==> Generic backend Doctrine schema update"
php bin/console doctrine:schema:update --force --no-interaction 2>&1 | tail -n 10

echo "==> Dynamic PostgreSQL model schema sync"
php bin/console app:models:schema:sync --no-interaction 2>&1 | tail -n 10

echo "==> Mongo schema update"
php bin/console app:mongo:schema:update --no-interaction 2>&1 | tail -n 5

echo "==> ORM fixtures load (purge + load)"
php bin/console doctrine:fixtures:load --no-interaction 2>&1 | tail -n 5

echo "==> Mongo seed"
php bin/console app:mongo:seed --no-interaction 2>&1 | tail -n 10

echo "==> Verify Postgres rows"
PGPASSWORD=baas_app_pwd psql -h postgres -U baas -d baas -c "SELECT 'restaurants' AS t, COUNT(*) FROM restaurants UNION ALL SELECT 'service_hours', COUNT(*) FROM service_hours UNION ALL SELECT 'categories', COUNT(*) FROM categories UNION ALL SELECT 'dishes', COUNT(*) FROM dishes UNION ALL SELECT 'menus', COUNT(*) FROM menus UNION ALL SELECT 'reservations', COUNT(*) FROM reservations UNION ALL SELECT 'users', COUNT(*) FROM \"user\";"

echo "==> Verify Mongo docs"
php -r '
require "/var/www/html/vendor/autoload.php";
$c = new MongoDB\Client("mongodb://baas:baas_app_pwd@mongo:27017/baas?authSource=baas");
$db = $c->selectDatabase("baas");
foreach (["gallery_images","audit_logs"] as $coll) {
  echo $coll . ": " . $db->selectCollection($coll)->countDocuments() . "\n";
}
'

echo "DONE"
