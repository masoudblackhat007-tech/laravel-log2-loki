#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/laravel-log2-loki"
URL="http://91.107.169.146"

cd "$APP_DIR"

echo "== Git status =="
if [ -n "$(git status --short)" ]; then
    git status --short
    echo "ERROR: working tree is not clean"
    exit 1
fi
echo "working tree clean"

echo
echo "== Laravel environment =="
php artisan about --only=environment

echo
echo "== Logging env =="
grep -E "^LOG_CHANNEL|^LOG_STACK|^LOG_LEVEL|^LOG_DAILY_DAYS" .env

echo
echo "== PHP-FPM UMask =="
systemctl cat php8.4-fpm | grep -n "UMask=0027" || {
    echo "ERROR: php8.4-fpm UMask=0027 is missing"
    exit 1
}

echo
echo "== HTTP headers =="
curl -I "$URL"

echo
echo "== Sensitive file exposure checks =="

env_code="$(curl -s -o /dev/null -w "%{http_code}" "$URL/.env")"
composer_code="$(curl -s -o /dev/null -w "%{http_code}" "$URL/composer.json")"
log_code="$(curl -s -o /dev/null -w "%{http_code}" "$URL/storage/logs/laravel.log")"

echo ".env: $env_code"
echo "composer.json: $composer_code"
echo "laravel.log: $log_code"

if [ "$env_code" = "200" ] || [ "$composer_code" = "200" ] || [ "$log_code" = "200" ]; then
    echo "ERROR: sensitive file exposure detected"
    exit 1
fi

echo
echo "== Latest daily log =="
TODAY_LOG="$APP_DIR/storage/logs/laravel-$(date +%F).log"

if [ -f "$TODAY_LOG" ]; then
    ls -lh "$TODAY_LOG"
    tail -n 1 "$TODAY_LOG"
else
    echo "ERROR: today's daily log file does not exist: $TODAY_LOG"
    exit 1
fi

echo
echo "== Backup files =="
ls -lh /home/deploy/backups/laravel-log2-loki
gzip -t /home/deploy/backups/laravel-log2-loki/*.sql.gz

echo
echo "Health check finished."
