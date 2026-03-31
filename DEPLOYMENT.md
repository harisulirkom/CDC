# CDC Backend Deployment (Production)

## 1. Setup
```bash
cp .env.production.example .env
php artisan key:generate
```

Lengkapi nilai:
- `DB_*`
- `FRONTEND_URL`
- `CORS_ALLOWED_ORIGINS`
- `MAIL_*` dan/atau `BREVO_*`

## 2. Install + Optimize
```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Queue + Scheduler
- Queue worker: jalankan `php artisan queue:work` via systemd/supervisor.
- Cron scheduler:
```bash
* * * * * cd /var/www/cdc/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Health Check
```bash
curl https://api.cdc.example.com/api/ping
```

Response harus:
```json
{"message":"API OK"}
```
