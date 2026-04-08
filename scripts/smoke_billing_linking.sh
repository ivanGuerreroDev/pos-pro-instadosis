#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8088}"
EMAIL="${EMAIL:-shop-owner@shop-owner.com}"
PASSWORD="${PASSWORD:-123456}"
BUSINESS_ID="${BUSINESS_ID:-4}"
API_KEY="${API_KEY:-2423098a70f3496d8e8a9d5f8b582034}"

fail() {
  echo "[FAIL] $1" >&2
  exit 1
}

assert_code() {
  local actual="$1"
  local expected="$2"
  local label="$3"

  if [[ "$actual" != "$expected" ]]; then
    echo "[FAIL] ${label}: expected ${expected}, got ${actual}" >&2
    return 1
  fi

  echo "[OK] ${label}: ${actual}"
  return 0
}

echo "[INFO] Login as ${EMAIL}"
LOGIN_RESP=$(curl -sS -X POST "${BASE_URL}/api/v1/sign-in" \
  -H 'Accept: application/json' \
  -d "email=${EMAIL}" \
  -d "password=${PASSWORD}")

TOKEN=$(printf '%s' "$LOGIN_RESP" | grep -o '"token":"[^"]*"' | head -n1 | cut -d '"' -f4 || true)
[[ -n "$TOKEN" ]] || fail "No se pudo obtener token. Respuesta: ${LOGIN_RESP}"

echo "[OK] Token obtenido"

echo "[INFO] Set pending state in DB"
docker compose exec -T db mysql -ularavel -psecret -e "USE laravel; UPDATE businesses SET billing_status='pending_billing_linking', emagic_api_key=NULL, billing_linked_at=NULL WHERE id=${BUSINESS_ID}; UPDATE users SET status='pending_billing_linking' WHERE business_id=${BUSINESS_ID};"

echo "[INFO] Validate pending blocks sales"
PENDING_SALES_CODE=$(curl -sS -o /tmp/pending_sales.json -w '%{http_code}' -X POST "${BASE_URL}/api/v1/sales" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{}')
assert_code "$PENDING_SALES_CODE" "403" "pending sales blocked" || fail "pending sales"

echo "[INFO] Validate pending blocks dgi-pdf"
PENDING_PDF_CODE=$(curl -sS -o /tmp/pending_pdf.json -w '%{http_code}' -X POST "${BASE_URL}/api/v1/dgi-pdf" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{}')
assert_code "$PENDING_PDF_CODE" "403" "pending dgi-pdf blocked" || fail "pending dgi-pdf"

echo "[INFO] Set active state with API key"
docker compose exec -T db mysql -ularavel -psecret -e "USE laravel; UPDATE businesses SET billing_status='active', emagic_api_key='${API_KEY}', billing_linked_at=NOW() WHERE id=${BUSINESS_ID}; UPDATE users SET status='active' WHERE business_id=${BUSINESS_ID};"

echo "[INFO] Validate active no longer blocked (expected validation 422)"
ACTIVE_SALES_CODE=$(curl -sS -o /tmp/active_sales.json -w '%{http_code}' -X POST "${BASE_URL}/api/v1/sales" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{}')
assert_code "$ACTIVE_SALES_CODE" "422" "active sales reaches validation" || fail "active sales"

ACTIVE_PDF_CODE=$(curl -sS -o /tmp/active_pdf.json -w '%{http_code}' -X POST "${BASE_URL}/api/v1/dgi-pdf" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{}')
assert_code "$ACTIVE_PDF_CODE" "422" "active dgi-pdf reaches validation" || fail "active dgi-pdf"

echo "[INFO] Confirm login payload shows active billing_status"
LOGIN_ACTIVE=$(curl -sS -X POST "${BASE_URL}/api/v1/sign-in" -H 'Accept: application/json' -d "email=${EMAIL}" -d "password=${PASSWORD}")
if ! printf '%s' "$LOGIN_ACTIVE" | grep -q '"billing_status":"active"'; then
  fail "login payload no refleja billing_status active"
fi

echo "[OK] billing_status active en login"

echo "[DONE] Smoke test completado correctamente"
