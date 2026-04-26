#!/bin/sh
set -e
B=http://apache
JAR=/tmp/cookies2.txt
rm -f $JAR

echo "==> /admin (anon, expect 302 to /login)"
curl -s -o /dev/null -w "  %{http_code}\n" --max-time 60 "$B/admin"

echo "==> /register page"
curl -fsS -o /dev/null -w "  %{http_code}\n" --max-time 60 "$B/register"

echo "==> /login page (priming session)"
curl -fsS -c $JAR -o /dev/null -w "  %{http_code}\n" --max-time 60 "$B/login"

# Stateless CSRF: client supplies matching cookie + form field.
TOKEN=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
echo "  injected csrf token: $(echo -n $TOKEN | head -c 12)..."

echo "==> Form login as admin"
curl -s -b $JAR -c $JAR --max-time 60 \
    --cookie "csrf-token-authenticate=$TOKEN" \
    -d "_username=admin@baas.test" \
    -d "_password=admin1234" \
    -d "_csrf_token=$TOKEN" \
    -o /tmp/login_resp.html -w "  POST /login: %{http_code} -> %{redirect_url}\n" \
    "$B/login"

echo "==> /admin (with session cookie)"
curl -sL -b $JAR -c $JAR -o /tmp/admin.html -w "  %{http_code} %{size_download}b\n" --max-time 90 "$B/admin"
echo "  contains 'Symfony BaaS' header: $(grep -c 'Symfony BaaS' /tmp/admin.html)"
echo "  contains 'API resources' menu: $(grep -c 'API resources' /tmp/admin.html)"

echo "==> /api/baas/resources (dashboard data API)"
curl -sL -b $JAR -o /tmp/resources.json -w "  %{http_code} %{size_download}b\n" --max-time 90 "$B/api/baas/resources"
echo "  contains restaurants resource: $(grep -c 'restaurants' /tmp/resources.json)"

echo "==> /account (authed cookie)"
curl -sL -b $JAR -o /tmp/acc.html -w "  %{http_code} %{size_download}b\n" --max-time 60 "$B/account"
echo "  contains admin email: $(grep -c 'admin@baas.test' /tmp/acc.html)"

echo
echo "ADMIN CHECKS DONE"
