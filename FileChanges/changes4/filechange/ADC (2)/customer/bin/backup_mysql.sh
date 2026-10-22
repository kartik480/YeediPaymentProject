#!/bin/sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ENV="$ROOT/.env"
[ -f "$ENV" ] || { echo "Missing .env"; exit 1; }
getenv(){ grep -E "^$1=" "$ENV" | tail -1 | cut -d= -f2-; }
DB_HOST=$(getenv DB_HOST); DB_PORT=$(getenv DB_PORT); DB_NAME=$(getenv DB_NAME); DB_USER=$(getenv DB_USER); DB_PASS=$(getenv DB_PASS)
OUT="$ROOT/storage/backup"; mkdir -p "$OUT"; FILE="$OUT/yeedipay_$(date +%Y%m%d_%H%M%S).sql.gz"
MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$FILE"
find "$OUT" -type f -name 'yeedipay_*.sql.gz' -mtime +7 -delete
echo "$FILE"
