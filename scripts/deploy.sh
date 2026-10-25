#!/usr/bin/env bash
# Update rutin Wokabulari di server (ambil kode terbaru, build, migrasi).
# Jalankan dari folder repo:  ./scripts/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

DC="docker compose -f compose.prod.yaml"

git pull --ff-only
$DC build app
$DC up -d --remove-orphans
$DC exec -T app php artisan migrate --force
$DC exec -T app php artisan optimize
$DC exec -T app php artisan queue:restart
docker image prune -f

echo "Selesai. Cek: curl -I https://wokabulari.poltekkes-manado.ac.id/up"
