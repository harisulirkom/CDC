# Deployment Tuning (Ubuntu Single Server)

Baseline ini ditujukan untuk beban rata-rata 500 user aktif pada 1 server Ubuntu dengan RAM 64 GB.

## 1) Stack yang disarankan

1. `nginx` sebagai reverse proxy + static assets
2. `php8.3-fpm` untuk Laravel
3. `mysql` (atau MariaDB) di server yang sama
4. `redis` untuk cache + queue
5. `supervisor` untuk worker queue

## 2) File konfigurasi template

1. Nginx virtual host: `deploy/ubuntu/nginx/cdc.conf.example`
2. Nginx anti-abuse snippet: `deploy/ubuntu/nginx/security-hardening.snippet`
3. PHP-FPM pool: `deploy/ubuntu/php-fpm/www-cdc.conf.example`
4. PHP Opcache: `deploy/ubuntu/php/conf.d/99-cdc-opcache.ini`
5. PHP security hardening: `deploy/ubuntu/php/conf.d/98-cdc-security.ini`
6. SQL least-privilege user: `deploy/ubuntu/mysql/01-create-app-user.sql`
7. Security preflight: `deploy/ubuntu/security/preflight-security-check.sh`
8. Secret scan (PHP): `deploy/ubuntu/security/scan-secrets.php`
9. Backup/restore scripts: `deploy/ubuntu/backup/`
10. Monitoring alerts template: `deploy/ubuntu/monitoring/prometheus-alert-rules.yml`

## 3) Langkah optimasi aplikasi (Laravel)

1. Set `.env` production:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `DB_USERNAME` jangan gunakan `root/postgres/sa/administrator`
   - `CACHE_DRIVER=redis`
   - `QUEUE_CONNECTION=redis`
2. Build cache config:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
3. Jalankan migration index:
   - `php artisan migrate --force`
4. Aktifkan guard runtime (sudah ada di code):
   - Deploy akan gagal boot bila `APP_DEBUG=true` saat `APP_ENV=production`
   - Deploy akan gagal boot bila DB user production memakai akun superuser
5. Jalankan preflight security check:
   - `bash deploy/ubuntu/security/preflight-security-check.sh /var/www/cdc/.env.production`
6. Jalankan secret scan:
   - `php deploy/ubuntu/security/scan-secrets.php /var/www/cdc`

## 4) Warm-up cache ringkasan tracer

Setelah deploy, hit endpoint ringkasan akreditasi untuk kombinasi filter utama agar cache awal langsung terbentuk:

1. Fakultas = all, Prodi = all, TS-1/TS-2
2. Tiap fakultas besar dengan Prodi populer

## 5) Monitoring minimum

1. Nginx: request/s, upstream response time, 5xx
2. PHP-FPM: active process, slowlog
3. MySQL: slow query log, threads connected, buffer pool hit rate
4. Redis: used memory, evicted keys

## 6) Backup & Restore (SEC-19)

1. Siapkan credential backup di `/etc/cdc/backup.env` (read-only root).
2. Buat backup harian:
   - `bash deploy/ubuntu/backup/backup_cdc.sh /etc/cdc/backup.env /var/backups/cdc`
3. Uji restore ke database sandbox:
   - `bash deploy/ubuntu/backup/restore_cdc.sh /etc/cdc/backup.env /var/backups/cdc/<file>.sql.gz cdc_restore_test`

## 7) Monitoring Alerts (SEC-24)

1. Import rule ke Prometheus:
   - `deploy/ubuntu/monitoring/prometheus-alert-rules.yml`
2. Hubungkan Alertmanager (Telegram/Email/Slack) dan uji notifikasi.
3. Lakukan burn-in minimal 24 jam sebelum go-live.

## 8) Incident Response (SEC-25)

1. Gunakan runbook: `Tracerv2/docs/security/04-incident-response-runbook-cdc.md`
2. Simulasi tabletop incident minimal 1x per kuartal.
