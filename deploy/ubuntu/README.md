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
2. PHP-FPM pool: `deploy/ubuntu/php-fpm/www-cdc.conf.example`
3. PHP Opcache: `deploy/ubuntu/php/conf.d/99-cdc-opcache.ini`

## 3) Langkah optimasi aplikasi (Laravel)

1. Set `.env` production:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `CACHE_DRIVER=redis`
   - `QUEUE_CONNECTION=redis`
2. Build cache config:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
3. Jalankan migration index:
   - `php artisan migrate --force`

## 4) Warm-up cache ringkasan tracer

Setelah deploy, hit endpoint ringkasan akreditasi untuk kombinasi filter utama agar cache awal langsung terbentuk:

1. Fakultas = all, Prodi = all, TS-1/TS-2
2. Tiap fakultas besar dengan Prodi populer

## 5) Monitoring minimum

1. Nginx: request/s, upstream response time, 5xx
2. PHP-FPM: active process, slowlog
3. MySQL: slow query log, threads connected, buffer pool hit rate
4. Redis: used memory, evicted keys

