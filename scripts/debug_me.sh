#!/bin/sh
TOKEN=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@quai-antique.test","password":"admin1234"}' \
  http://apache/api/login | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["token"] ?? "";')
echo "TOKEN=[$TOKEN]"
echo "len=${#TOKEN}"
echo "--- /api/me ---"
curl -s -i -H "Authorization: Bearer $TOKEN" http://apache/api/me | head -n 30
