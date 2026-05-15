-- CDC production database user (least privilege)
-- Jalankan sebagai admin DB, lalu update .env:
-- DB_USERNAME=cdc_app
-- DB_PASSWORD=<strong-random-password>

CREATE USER IF NOT EXISTS 'cdc_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
CREATE USER IF NOT EXISTS 'cdc_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER
ON cdc.* TO 'cdc_app'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER
ON cdc.* TO 'cdc_app'@'localhost';

FLUSH PRIVILEGES;
