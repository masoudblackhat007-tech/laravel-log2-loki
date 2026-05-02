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

## Database backup

Manual backup command on server:

/home/deploy/scripts/backup-laravel-log2-loki.sh

Backup directory:

/home/deploy/backups/laravel-log2-loki

Backup credential file:

/home/deploy/.my-laravel-log2-backup.cnf

Credential file permission:

600

Backup directory permission:

700

Backup file permission:

600

Cron schedule:

15 3 * * * /home/deploy/scripts/backup-laravel-log2-loki.sh >> /home/deploy/backups/laravel-log2-loki/backup.log 2>&1

Current retention policy:

Only the latest successful backup is kept.

Warning:

Keeping only one backup is risky. If corrupted data is backed up, the last known-good backup may be lost.

Local backup pull command from WSL/bash:

scp 'laravel-server:/home/deploy/backups/laravel-log2-loki/*.sql.gz' ~/backups/laravel-log2-loki/

Local backup directory:

/home/masoud/backups/laravel-log2-loki

Local backup verification:

gzip -t ~/backups/laravel-log2-loki/*.sql.gz

## HTTPS status

Current status:

HTTP only.

Reason:

No domain is configured yet.

Required next steps for real HTTPS:

1. Buy or configure a domain.
2. Point DNS A record to 91.107.169.146.
3. Change Nginx server_name from IP to domain.
4. Install Certbot.
5. Issue Let's Encrypt certificate.
6. Redirect HTTP to HTTPS.
7. Set SESSION_SECURE_COOKIE=true after HTTPS is active.

Warning:

Do not treat HTTP as secure. Login, sessions, admin panels, tokens, forms, and user data must not rely on plain HTTP.

## JSON logging

Current status:

Laravel file logs are formatted as JSON lines.

Formatter class:

app/Logging/JsonFormatterTap.php

Configured channels:

single
daily

Production log file:

/var/www/laravel-log2-loki/storage/logs/laravel.log

Production log level:

warning

Important behavior:

info logs are ignored in production because LOG_LEVEL=warning.

Server test command:

php artisan tinker --execute="logger()->warning('server_json_logging_warning_test', ['check' => true, 'source' => 'server']);"

Expected output shape:

{"message":"server_json_logging_warning_test","context":{"check":true,"source":"server"},"level":300,"level_name":"WARNING","channel":"production","datetime":"...","extra":[]}

Warning:

JSON formatting alone does not make logs safe. Do not log raw tokens, passwords, session IDs, cookies, authorization headers, private files, or full user payloads.

HTTP request logging:

Enabled through middleware:

app/Http/Middleware/RequestContextLogging.php

Registered in:

bootstrap/app.php

Request context builder:

app/Logging/RequestLogContextBuilder.php

Each HTTP request logs:

request_id
service
environment
method
path
route
status_code
duration_ms
masked client_ip
user_agent
user_id when authenticated
session_hash instead of raw session_id

Response header:

X-Request-Id

Current limitation:

Successful HTTP requests are currently logged at warning level so they are visible with LOG_LEVEL=warning. This is useful for the exercise, but semantically request logs should usually be info level, while warnings should be reserved for slow, failed, or suspicious requests.

## Important warnings

Do not commit .env.
Do not use DB root for Laravel.
Do not chmod 777.
Do not put private files in storage/app/public.
Do not blindly run destructive migrations with migrate --force on real data.
HTTP is still not HTTPS.
