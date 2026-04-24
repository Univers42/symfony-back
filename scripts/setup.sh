#!/usr/bin/env bash
# scripts/setup.sh — Linux/macOS bootstrap
set -euo pipefail

echo "==> Preparing project files..."

[ -f .env ] || cp .env.example .env
mkdir -p app secrets models

# Drop legacy MySQL secrets if still present (we use Postgres + Mongo now).
rm -f secrets/mysql_password.txt secrets/mysql_root_password.txt

[ -f secrets/postgres_password.txt ]   || printf 'baas_app_pwd'              > secrets/postgres_password.txt
[ -f secrets/mongo_root_password.txt ] || printf 'change_this_root_password' > secrets/mongo_root_password.txt
[ -f secrets/mongo_app_password.txt ]  || printf 'baas_app_pwd'              > secrets/mongo_app_password.txt
[ -f secrets/jwt_passphrase.txt ]      || printf 'change_me_jwt_passphrase'  > secrets/jwt_passphrase.txt

# Strip any trailing CR/LF from existing secret files (drivers would otherwise
# treat \r\n as part of the password, causing connection failures).
for f in secrets/*.txt; do
    [ -f "$f" ] || continue
    content=$(tr -d '\r\n' < "$f")
    printf '%s' "$content" > "$f"
done

# Best-effort: align UID/GID in .env so bind mounts are writable
if command -v id >/dev/null 2>&1; then
    UID_VAL=$(id -u); GID_VAL=$(id -g)
    sed -i.bak -E "s/^APP_UID=.*/APP_UID=${UID_VAL}/; s/^APP_GID=.*/APP_GID=${GID_VAL}/" .env && rm -f .env.bak
fi

echo "✓ Setup complete."
echo "Next: make build && make init && make up"
