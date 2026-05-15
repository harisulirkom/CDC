# CDC Backup & Restore (SEC-19)

## 1) Siapkan file env backup

Buat file `/etc/cdc/backup.env` (permission `600`, owner `root`):

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cdc
DB_USERNAME=cdc_app
DB_PASSWORD=strong_password
APP_ROOT=/var/www/cdc
```

## 2) Backup harian

```bash
bash deploy/ubuntu/backup/backup_cdc.sh /etc/cdc/backup.env /var/backups/cdc
```

Contoh cron harian (jam 01:10):

```bash
10 1 * * * root bash /var/www/cdc/deploy/ubuntu/backup/backup_cdc.sh /etc/cdc/backup.env /var/backups/cdc >> /var/log/cdc-backup.log 2>&1
```

## 3) Restore ke database uji

```bash
bash deploy/ubuntu/backup/restore_cdc.sh /etc/cdc/backup.env /var/backups/cdc/cdc_db_YYYYmmdd_HHMMSS.sql.gz cdc_restore_test
```

## 4) Verifikasi restore

```bash
bash deploy/ubuntu/backup/verify_restore.sh /etc/cdc/backup.env cdc_restore_test
```

Kriteria minimal lulus:
- database dapat diimport tanpa error.
- tabel inti (`users`, `alumnis`, `responses`) ada.
