# Laravel Log2 Loki Deployment Notes

## Project

Local path:

D:\project-learn\laravel-log2-loki

Server path:

/var/www/laravel-log2-loki

Repository:

git@github.com:masoudblackhat007-tech/laravel-log2-loki.git

Server:

91.107.169.146

## Current stack

Ubuntu 22.04.5
Nginx
PHP 8.4.20
Laravel 12.58.0
Composer 2.9.7
Node 20.20.2 on server
MariaDB

## Security status

APP_ENV=production
APP_DEBUG=false
Nginx root=/var/www/laravel-log2-loki/public
.env is not web-readable
composer.json is not web-readable
Laravel logs are not web-readable
SSH root login disabled
SSH password login disabled
SSH public key login enabled
fail2ban sshd jail active
HTTP port 80 restricted by UFW to one IP
Deploy key is read-only

## Deploy command

ssh laravel-server
tmux attach -t deploy || tmux new -s deploy
cd /var/www/laravel-log2-loki
./deploy.sh

## Important warnings

Do not commit .env.
Do not use DB root for Laravel.
Do not chmod 777.
Do not put private files in storage/app/public.
Do not blindly run destructive migrations with migrate --force on real data.
HTTP is still not HTTPS.
