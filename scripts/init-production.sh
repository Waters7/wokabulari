#!/usr/bin/env bash
# Inisialisasi pertama kali (SEKALI saja) setelah container berjalan:
# tabel, role & permission, bahasa, aturan gamifikasi, lalu akun super admin.
# Jalankan dari folder repo:  ./scripts/init-production.sh
set -euo pipefail
cd "$(dirname "$0")/.."

DC="docker compose -f compose.prod.yaml"

$DC exec -T app php artisan migrate --force
$DC exec -T app php artisan db:seed --class=ProductionSeeder --force
$DC exec -T app php artisan optimize

echo
echo "Buat akun super admin (login memakai username atau email):"
$DC exec app php artisan app:create-admin
