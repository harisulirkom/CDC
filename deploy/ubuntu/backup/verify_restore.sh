#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-}"
TARGET_DB="${2:-}"

if [[ -z "${ENV_FILE}" || -z "${TARGET_DB}" ]]; then
  echo "Usage: $0 <env_file> <target_db>"
  exit 1
fi

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "[FAIL] Env file tidak ditemukan: ${ENV_FILE}"
  exit 1
fi

set -a
source "${ENV_FILE}"
set +a

: "${DB_HOST:?missing DB_HOST}"
: "${DB_PORT:?missing DB_PORT}"
: "${DB_USERNAME:?missing DB_USERNAME}"
: "${DB_PASSWORD:?missing DB_PASSWORD}"

check_table() {
  local table_name="$1"
  local exists
  exists="$(MYSQL_PWD="${DB_PASSWORD}" mysql -N -s \
    -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USERNAME}" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TARGET_DB}' AND table_name='${table_name}';")"

  if [[ "${exists}" == "1" ]]; then
    echo "[PASS] tabel ${table_name} ada"
  else
    echo "[FAIL] tabel ${table_name} tidak ditemukan"
    return 1
  fi
}

check_table "users"
check_table "alumnis"
check_table "responses"

echo "[DONE] Verifikasi restore selesai."
