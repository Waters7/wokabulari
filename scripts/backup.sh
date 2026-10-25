#!/usr/bin/env bash
# Cadangan: dump database + arsip berkas privat (selfie, tanda tangan, tugas, materi).
# Jadwalkan lewat cron host, mis. tiap hari 01.00:
#   0 1 * * * cd /opt/wokabulari && ./scripts/backup.sh >> /var/log/wokabulari-backup.log 2>&1
# Salin juga hasilnya ke luar server (cadangan di server yang sama bukan cadangan sesungguhnya).
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077

DEST="${BACKUP_DIR:-/opt/backup/wokabulari}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M)"
DC="docker compose -f compose.prod.yaml"

mkdir -p "$DEST"

# Database — kredensial dibaca dari variabel lingkungan container db (.env.db).
$DC exec -T db sh -c 'exec mysqldump --single-transaction --quick --routines -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$DEST/db-$STAMP.sql.gz"

# Berkas di volume storage-app (tanpa folder backups bawaan aplikasi).
$DC exec -T app tar -C /var/www/html/storage/app --exclude=./backups -czf - . > "$DEST/files-$STAMP.tar.gz"

find "$DEST" -type f -name '*.gz' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') cadangan tersimpan di $DEST"
