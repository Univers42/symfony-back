#!/bin/sh
set -e
cd /var/www/html

CURL="curl -fsS --max-time 90"

echo "==> warm-up cache"
$CURL -o /dev/null -w "  HTTP %{http_code}\n" http://apache/api/docs

echo "==> /api/docs anonymous"
$CURL -o /dev/null -w "  HTTP %{http_code}\n" -H "Accept: text/html" http://apache/api/docs

echo "==> POST /api/login (admin)"
TOKEN=$($CURL -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@baas.test","password":"admin1234"}' \
  http://apache/api/login | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["token"] ?? "";')
if [ -z "$TOKEN" ]; then echo "  FAIL: no token"; exit 1; fi
echo "  token len: ${#TOKEN}"

echo "==> GET /api/me"
$CURL -H "Authorization: Bearer $TOKEN" http://apache/api/me
echo

echo "==> GET /api/baas/restaurants (anonymous OK)"
$CURL -H "Accept: application/json" http://apache/api/baas/restaurants | head -c 400
echo

echo "==> GET /api/baas/dishes"
$CURL -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" http://apache/api/baas/dishes | head -c 400
echo

echo "==> GET /api/mongo/gallery_images"
$CURL -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" http://apache/api/mongo/gallery_images | head -c 400
echo

echo "==> POST /api/register (new client)"
$CURL -X POST -H "Content-Type: application/json" \
  -d "{\"email\":\"new$(date +%s)@example.com\",\"password\":\"secret123\",\"display_name\":\"Newbie\"}" \
  http://apache/api/register
echo
echo "DONE"
