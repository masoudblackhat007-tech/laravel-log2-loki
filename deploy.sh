#!/usr/bin/env bash
set -euo pipefail

cd /var/www/laravel-log2-loki

git pull origin main

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan optimize

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
sudo find storage/logs -type f -name "*.log" -exec chmod 660 {} \;

echo "Deploy finished."
