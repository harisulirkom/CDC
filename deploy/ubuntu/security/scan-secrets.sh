#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
cd "${REPO_ROOT}"

echo "== CDC lightweight secret scan =="

patterns=(
  "AKIA[0-9A-Z]{16}"
  "-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----"
  "(?i)(api[_-]?key|secret|token|password)\\s*[:=]\\s*['\"][A-Za-z0-9_\\-\\/+]{16,}['\"]"
)

exclude_glob=(
  "--glob=!vendor/**"
  "--glob=!node_modules/**"
  "--glob=!.git/**"
  "--glob=!.env"
  "--glob=!.env.*"
  "--glob=!*.lock"
)

hit=0
if command -v rg >/dev/null 2>&1; then
  for p in "${patterns[@]}"; do
    if rg -n -P "${p}" . "${exclude_glob[@]}"; then
      hit=1
    fi
  done
else
  echo "[WARN] rg tidak tersedia, fallback ke grep."
  if grep -RInE "AKIA[0-9A-Z]{16}|BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY|api[_-]?key|secret|token|password" \
    --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude=.env --exclude=.env.* --exclude='*.lock' .; then
    hit=1
  fi
fi

if [[ ${hit} -eq 1 ]]; then
  echo ""
  echo "[FAIL] Ada indikasi secret hardcoded. Review output di atas."
  exit 2
fi

echo "[PASS] Tidak ada pola secret hardcoded pada scan ringan."
