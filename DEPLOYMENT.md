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

Restore test:

A real restore test was performed into a separate database:

laravel_log2_loki_restore_test

The backup was imported successfully.

Verified table counts:

migrations matched.
users matched.
jobs matched.

The restore test database was dropped after verification.

Important rule:

Never restore a backup directly into the production database for testing. Always restore into a separate temporary database first.

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

Current request log level policy:

Successful and fast HTTP requests are logged at info level.

Requests with status_code >= 400 or duration_ms >= 1000 are logged at warning level.

Production LOG_LEVEL must be info if normal request logs should be written.

Log rotation:

Laravel uses the daily log channel in production.

Production logging env:

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14

Daily log path format:

/var/www/laravel-log2-loki/storage/logs/laravel-YYYY-MM-DD.log

Retention:

Laravel keeps daily logs for 14 days.

Log file permission hardening:

php8.4-fpm has a systemd override:

/etc/systemd/system/php8.4-fpm.service.d/override.conf

Override content:

[Service]
UMask=0027

Expected log file permission:

660

Reason:

Both www-data and deploy need write access. PHP-FPM writes web request logs as www-data, while deploy runs artisan, deploy scripts, and health checks. World access must remain disabled.

Important rule:

Laravel logs must not be world-readable. Logs may contain operational details, request paths, exception metadata, user identifiers, or accidentally logged sensitive data.

## JSON API error responses

Current status:

JSON exception responses are enabled for requests that expect JSON or match api/*.

Configured in:

bootstrap/app.php

Error mapper:

app/Support/Errors/ApiErrorMapper.php

Error DTO:

app/Support/Errors/ApiError.php

Response shape:

{"error":{"code":"HTTP_404","message":"The route not-found-test could not be found.","details":[]}}

Rules:

ValidationException returns VALIDATION_ERROR with details.
AuthenticationException returns AUTH_ERROR.
HttpExceptionInterface returns HTTP_<status>.
QueryException returns DB_ERROR with a safe message.
Unknown exceptions return INTERNAL_ERROR with a safe message.

Security rule:

Unknown 500 errors must not expose raw exception messages, stack traces, SQL queries, passwords, tokens, file paths, or internal service details.

Production test command:

curl -i -H "Accept: application/json" http://91.107.169.146/not-found-test

## Deployment health check

Health check script:

health-check.sh

Run on server:

cd /var/www/laravel-log2-loki
./health-check.sh

Checks performed:

working tree is clean
Laravel environment is production
APP_DEBUG is off
logging env is correct
php8.4-fpm UMask=0027 is active
HTTP endpoint returns headers
X-Request-Id is present
.env is not web-readable
composer.json is not web-readable
laravel.log is not web-readable
today's daily log exists
latest daily log line is JSON
backup files exist
backup gzip integrity test passes

Important behavior:

The health check requests /storage/logs/laravel.log to verify that logs are not exposed. This intentionally creates a 404 warning log entry. That warning is expected during health checks.

## Tests

Current test coverage:

ApiErrorResponseTest verifies structured JSON error responses and X-Request-Id.
SensitiveDataRedactorTest verifies recursive redaction, IP masking, identifier hashing, and long string truncation.
RequestContextLoggingTest verifies X-Request-Id behavior and info-level logging for successful requests.
ApiErrorMapperTest verifies safe error mapping for unknown exceptions, database exceptions, authentication errors, and validation errors.

Run tests locally:

php artisan test

Run a focused test locally:

php artisan test --filter=ApiErrorMapperTest

Production rule:

Do not run the test suite on the production server as part of normal deployment. Production uses composer install --no-dev, so development test tooling is intentionally not installed there.

Correct workflow:

Run tests locally or in CI before pushing/deploying.
Deploy only after tests pass.
Use health-check.sh on the production server after deployment.

## Server swap

Current status:

A 2GB swap file is enabled.

Swap file:

/swapfile

fstab entry:

/swapfile none swap sw 0 0

Reason:

The server has 3.7GiB RAM and no default swap. Loki and Grafana can increase memory pressure, so a small swap file reduces the risk of sudden OOM kills during spikes.

Verification commands:

free -h
swapon --show
grep -n "/swapfile" /etc/fstab

## Loki, Grafana, and Alloy

Current status:

Loki, Grafana, and Alloy are installed from the official Grafana APT repository.

Installed versions:

```text
Loki 3.7.1
Grafana 13.0.1
Alloy 1.16.0
```

Service status:

```text
loki is active and enabled.
grafana-server is active and enabled.
alloy is active and enabled.
loki-ui is disabled and inactive.
```

Network binding policy:

```text
Loki HTTP listens only on 127.0.0.1:3100.
Loki gRPC listens only on 127.0.0.1:9095.
Grafana listens only on 127.0.0.1:3000.
Alloy listens only on 127.0.0.1:12345.
```

No Grafana, Loki, or Alloy ports are opened in UFW.

Important security rule:

Do not expose Loki directly to the internet. Loki is currently bound to localhost only.

Do not expose Grafana directly without HTTPS, authentication hardening, and access control.

Old Docker containers:

Old Docker containers named `grafana` and `alloy` were stopped because they conflicted with the APT/systemd services.

Laravel log ingestion:

Alloy reads Laravel daily JSON logs from:

```text
/var/www/laravel-log2-loki/storage/logs/laravel-*.log
```

Alloy pushes logs to local Loki:

```text
http://127.0.0.1:3100/loki/api/v1/push
```

Current Loki labels:

```text
environment
filename
host
job
service_name
```

Do not add high-cardinality request fields as permanent Loki labels.

Grafana Loki datasource:

A Loki datasource is provisioned in Grafana.

Provisioning file:

```text
/etc/grafana/provisioning/datasources/loki.yaml
```

Datasource settings:

```text
name: Loki
type: loki
access: proxy
url: http://127.0.0.1:3100
isDefault: true
editable: false
```

Current datasource UID:

```text
P8E80F9AEF21F6940
```

The datasource UID is not a secret, but it is environment-specific. If the datasource is recreated, the UID may change.

Grafana admin password:

The Grafana admin password is stored server-side only:

```text
/root/grafana-admin-password.txt
```

The file is owned by root and has permission `600`.

Important password rule:

Do not commit, print, paste, log, screenshot, or document the Grafana admin password.

Do not include the password file content in Git, chat, shell history snippets, deployment notes, public backups, or shared logs.

Verification commands:

```bash
systemctl is-active loki grafana-server alloy
systemctl is-enabled loki grafana-server alloy

curl -sS http://127.0.0.1:3100/ready
curl -sS http://127.0.0.1:3000/api/health
curl -sS http://127.0.0.1:12345/-/ready

sudo ss -lntp | grep -Ei 'loki|grafana|alloy|:3000|:3100|:9095|:12345|:18090|:9096' || true
```

Expected status:

```text
loki: active, enabled
grafana-server: active, enabled
alloy: active, enabled
loki-ui: inactive, disabled
```

Expected network exposure:

```text
127.0.0.1:3100   Loki HTTP
127.0.0.1:9095   Loki gRPC
127.0.0.1:3000   Grafana
127.0.0.1:12345  Alloy
```

There must be no public listener for Loki, Grafana, or Alloy.

Query Laravel logs directly from Loki:

```bash
curl -G -sS "http://127.0.0.1:3100/loki/api/v1/query_range" \
  --data-urlencode 'query={job="laravel"}' \
  --data-urlencode 'limit=5' \
  | python3 -m json.tool
```

Verify datasource through Grafana proxy:

```bash
GRAFANA_ADMIN_PASSWORD="$(sudo cat /root/grafana-admin-password.txt)"

curl -G -sS \
  -u "admin:${GRAFANA_ADMIN_PASSWORD}" \
  "http://127.0.0.1:3000/api/datasources/proxy/uid/P8E80F9AEF21F6940/loki/api/v1/query_range" \
  --data-urlencode 'query={job="laravel"}' \
  --data-urlencode 'limit=5' \
  | python3 -m json.tool

unset GRAFANA_ADMIN_PASSWORD
```

Useful LogQL queries:

Show latest Laravel logs:

```logql
{job="laravel"}
```

Parse JSON fields:

```logql
{job="laravel"} | json
```

Show warnings:

```logql
{job="laravel"} | json | level_name="WARNING"
```

Show 404 responses:

```logql
{job="laravel"} | json | context_http_status_code=404
```

Show HEAD requests:

```logql
{job="laravel"} | json | context_http_method="HEAD"
```

Readable request summary:

```logql
{job="laravel"} | json | line_format "{{.level_name}} {{.context_http_status_code}} {{.context_http_method}} {{.context_http_path}} request_id={{.context_request_id}} duration_ms={{.context_http_duration_ms}}"
```

Flattened JSON field names:

```text
context.http.status_code  -> context_http_status_code
context.http.method       -> context_http_method
context.http.path         -> context_http_path
context.request_id        -> context_request_id
```

Security and cardinality rule:

Do not promote these fields to permanent Loki labels:

```text
request_id
session_hash
client_ip
user_agent
path
route
any user-controlled value
```

Use those values only as parsed fields at query time.

Even hashed values such as `session_hash` must not become permanent labels. A hash is still a correlation identifier and can create privacy and cardinality problems.

Operational notes:

The health check intentionally requests blocked paths such as Laravel log files to confirm they are not web-readable. Those checks can create expected `404` warning entries in Loki.

The path `storage/logs/laravel.log` appearing in warning logs is expected when testing that logs are not exposed over HTTP.

Known Grafana note:

Grafana may log a plugin installation warning like this:

```text
Failed to install plugin ... read-only file system
```

Grafana is still considered healthy if both checks pass:

```bash
systemctl is-active grafana-server
curl -sS http://127.0.0.1:3000/api/health
```

Expected health output includes:

```text
active
database: ok
```

Do not ignore repeated or new Grafana errors without investigation, but the existing plugin warning is not currently blocking Loki datasource usage.

Final working pipeline:

```text
Laravel JSON daily logs
  -> /var/www/laravel-log2-loki/storage/logs/laravel-YYYY-MM-DD.log
  -> Alloy
  -> Loki
  -> Grafana Loki datasource
  -> Grafana proxy query
```

## Important warnings

Do not commit .env.
Do not use DB root for Laravel.
Do not chmod 777.
Do not put private files in storage/app/public.
Do not blindly run destructive migrations with migrate --force on real data.
HTTP is still not HTTPS.
