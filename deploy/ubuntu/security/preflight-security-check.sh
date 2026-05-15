#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.env}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "[FAIL] Env file tidak ditemukan: ${ENV_FILE}"
  exit 1
fi

get_env_value() {
  local key="$1"
  local raw
  raw="$(grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 | cut -d'=' -f2- || true)"
  raw="${raw%\"}"
  raw="${raw#\"}"
  echo "${raw}"
}

fail_count=0

check_eq() {
  local key="$1"
  local expected="$2"
  local actual
  actual="$(get_env_value "${key}")"
  if [[ "${actual}" == "${expected}" ]]; then
    echo "[PASS] ${key}=${expected}"
  else
    echo "[FAIL] ${key} expected '${expected}', actual '${actual}'"
    fail_count=$((fail_count + 1))
  fi
}

check_not_empty() {
  local key="$1"
  local actual
  actual="$(get_env_value "${key}")"
  if [[ -n "${actual}" ]]; then
    echo "[PASS] ${key} terisi"
  else
    echo "[FAIL] ${key} kosong"
    fail_count=$((fail_count + 1))
  fi
}

check_not_in_list() {
  local key="$1"
  local actual
  actual="$(tr '[:upper:]' '[:lower:]' <<<"$(get_env_value "${key}")")"
  shift
  local denied=("$@")
  for item in "${denied[@]}"; do
    if [[ "${actual}" == "${item}" ]]; then
      echo "[FAIL] ${key} tidak boleh '${item}'"
      fail_count=$((fail_count + 1))
      return
    fi
  done
  echo "[PASS] ${key} bukan akun superuser"
}

check_https_url() {
  local key="$1"
  local actual
  actual="$(get_env_value "${key}")"
  if [[ "${actual}" =~ ^https:// ]]; then
    echo "[PASS] ${key} menggunakan https"
  else
    echo "[FAIL] ${key} harus https (actual: ${actual})"
    fail_count=$((fail_count + 1))
  fi
}

echo "== CDC Security Preflight =="
echo "Env file: ${ENV_FILE}"

check_eq "APP_ENV" "production"
check_eq "APP_DEBUG" "false"
check_not_empty "APP_KEY"
check_not_in_list "DB_USERNAME" "root" "postgres" "sa" "administrator"
check_eq "SESSION_SECURE_COOKIE" "true"
check_https_url "APP_URL"
check_https_url "FRONTEND_URL"

cors_origins="$(get_env_value "CORS_ALLOWED_ORIGINS")"
if [[ "${cors_origins}" == "*" ]]; then
  echo "[FAIL] CORS_ALLOWED_ORIGINS tidak boleh '*'"
  fail_count=$((fail_count + 1))
else
  echo "[PASS] CORS_ALLOWED_ORIGINS tidak wildcard"
fi

if [[ ${fail_count} -gt 0 ]]; then
  echo ""
  echo "Security preflight gagal (${fail_count} temuan)."
  exit 2
fi

echo ""
echo "Security preflight lulus."
