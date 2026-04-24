#!/bin/sh
set -e
BASE="http://apache"
echo "==> /api/docs"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 "$BASE/api/docs"
echo "==> /api/restaurants"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 -H 'Accept: application/json' "$BASE/api/restaurants"
echo "==> /api/dishes"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 -H 'Accept: application/json' "$BASE/api/dishes"
echo "==> /api/menus"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 -H 'Accept: application/json' "$BASE/api/menus"
echo "==> /api/mongo/gallery_images"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 -H 'Accept: application/json' "$BASE/api/mongo/gallery_images"
echo "==> /api/reservations/availability?date=2026-04-28&guests=2&service=lunch"
curl -fsS --max-time 60 -H 'Accept: application/json' "$BASE/api/reservations/availability?date=2026-04-28&guests=2&service=lunch"
echo
echo "==> /api/login (admin)"
TOKEN=$(curl -fsS --max-time 60 -H 'Content-Type: application/json' -d '{"email":"admin@quai-antique.test","password":"admin1234"}' "$BASE/api/login" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
echo "  token length: $(echo -n "$TOKEN" | wc -c)"
echo "==> /api/me with bearer"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 -H "Authorization: Bearer $TOKEN" "$BASE/api/me"
echo "==> /reservation page"
curl -fsS -o /dev/null -w "  %{http_code} %{size_download}b\n" --max-time 60 "$BASE/reservation"
echo
echo "ALL GOOD"
