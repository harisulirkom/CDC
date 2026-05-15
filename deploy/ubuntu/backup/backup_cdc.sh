#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-}"
BACKUP_DIR="${2:-/var/backups/cdc}"
RETENTION_DAYS="${3:-14}"

if [[ -z "${ENV_FILE}" || ! -f "${ENV_FILE}" ]]; then
  echo "Usage: $0 <env_file> [backup_dir] [retention_days]"
  exit 1
fi

set -a
source "${ENV_FILE}"
set +a

: "${DB_HOST:?missing DB_HOST}"
: "${DB_PORT:?missing DB_PORT}"
: "${DB_DATABASE:?missing DB_DATABASE}"
: "${DB_USERNAME:?missing DB_USERNAME}"
: "${DB_PASSWORD:?missing DB_PASSWORD}"
: "${APP_ROOT:=/var/www/cdc}"

mkdir -p "${BACKUP_DIR}"
timestamp="$(date +%Y%m%d_%H%M%S)"

db_backup="${BACKUP_DIR}/cdc_db_${timestamp}.sql.gz"
storage_backup="${BACKUP_DIR}/cdc_storage_${timestamp}.tar.gz"
checksum_file="${BACKUP_DIR}/cdc_checksums_${timestamp}.sha256"

echo "[INFO] Dump database -> ${db_backup}"
MYSQL_PWD="${DB_PASSWORD}" mysqldump \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USERNAME}" \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  "${DB_DATABASE}" | gzip > "${db_backup}"

if [[ -d "${APP_ROOT}/storage" ]]; then
  echo "[INFO] Backup storage -> ${storage_backup}"
  tar -czf "${storage_backup}" -C "${APP_ROOT}" storage
else
  echo "[WARN] Folder storage tidak ditemukan di ${APP_ROOT}, skip backup file."
  : > "${storage_backup}"
fi

sha256sum "${db_backup}" "${storage_backup}" > "${checksum_file}"
echo "[INFO] Checksum -> ${checksum_file}"

echo "[INFO] Retention cleanup > ${RETENTION_DAYS} hari"
find "${BACKUP_DIR}" -type f -name "cdc_*" -mtime +"${RETENTION_DAYS}" -delete

echo "[DONE] Backup selesai: ${timestamp}"
