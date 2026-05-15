#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-}"
BACKUP_FILE="${2:-}"
TARGET_DB="${3:-}"

if [[ -z "${ENV_FILE}" || -z "${BACKUP_FILE}" || -z "${TARGET_DB}" ]]; then
  echo "Usage: $0 <env_file> <backup_sql_gz> <target_db>"
  exit 1
fi

if [[ ! -f "${ENV_FILE}" || ! -f "${BACKUP_FILE}" ]]; then
  echo "[FAIL] env file atau backup file tidak ditemukan."
  exit 1
fi

set -a
source "${ENV_FILE}"
set +a

: "${DB_HOST:?missing DB_HOST}"
: "${DB_PORT:?missing DB_PORT}"
: "${DB_USERNAME:?missing DB_USERNAME}"
: "${DB_PASSWORD:?missing DB_PASSWORD}"

echo "[INFO] Create database ${TARGET_DB} jika belum ada"
MYSQL_PWD="${DB_PASSWORD}" mysql \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USERNAME}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${TARGET_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[INFO] Restore ${BACKUP_FILE} -> ${TARGET_DB}"
gunzip -c "${BACKUP_FILE}" | MYSQL_PWD="${DB_PASSWORD}" mysql \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USERNAME}" \
  "${TARGET_DB}"

echo "[DONE] Restore selesai."
