#!/bin/sh
for i in 1 2 3 4 5 6 7; do
  printf "req%d: " "$i"
  curl -sS --max-time 30 -o /dev/null -w '%{http_code} %{time_total}s\n' http://apache/api/mongo/gallery_images
done
