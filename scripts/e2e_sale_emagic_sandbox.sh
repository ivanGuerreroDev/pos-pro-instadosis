#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8088}"
EMAIL="${EMAIL:-shop-owner@shop-owner.com}"
PASSWORD="${PASSWORD:-123456}"
BUSINESS_ID="${BUSINESS_ID:-4}"
API_KEY="${API_KEY:-2423098a70f3496d8e8a9d5f8b582034}"

WORKDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

fail() {
  echo "[FAIL] $1" >&2
  exit 1
}

run_sql() {
  local sql="$1"
  (cd "$WORKDIR" && docker compose exec -T db mysql -N -B -ularavel -psecret -e "$sql")
}

echo "[INFO] Ensure business is active with API key"
run_sql "USE laravel; UPDATE businesses SET billing_status='active', emagic_api_key='${API_KEY}', billing_linked_at=NOW() WHERE id=${BUSINESS_ID}; UPDATE users SET status='active' WHERE business_id=${BUSINESS_ID};"

echo "[INFO] Ensure minimum e2e dataset exists"
run_sql "USE laravel; \
INSERT INTO business_invoice_data (business_id,dtipoRuc,druc,ddv,dnombEm,dcoordEm,ddirecEm,dcodUbi,dcorreg,ddistr,dprov,dtfnEm,dcorElectEmi,created_at,updated_at) \
SELECT ${BUSINESS_ID},'Juridico','155705519-2-2021','37','Trade G SA','8.9833,-79.5167','Ciudad de Panama','8-10-01','8-10','8','8','60000000','qa-tradeg@example.com',NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM business_invoice_data WHERE business_id=${BUSINESS_ID}); \
INSERT INTO categories (categoryName,business_id,variationCapacity,variationColor,variationSize,variationType,variationWeight,status,created_at,updated_at) \
SELECT 'QA Categoria',${BUSINESS_ID},0,0,0,0,0,1,NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE business_id=${BUSINESS_ID} AND categoryName='QA Categoria'); \
INSERT INTO units (unitName,business_id,status,created_at,updated_at) \
SELECT 'Unidad',${BUSINESS_ID},1,NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM units WHERE business_id=${BUSINESS_ID} AND unitName='Unidad'); \
INSERT INTO brands (business_id,brandName,status,created_at,updated_at) \
SELECT ${BUSINESS_ID},'QA Marca',1,NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM brands WHERE business_id=${BUSINESS_ID} AND brandName='QA Marca'); \
INSERT INTO parties (name,business_id,email,type,phone,due,address,image,status,created_at,updated_at) \
SELECT 'Cliente E2E QA',${BUSINESS_ID},'cliente-e2e@example.com','Retailer','60009999',0,'Ciudad de Panama',NULL,1,NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM parties WHERE business_id=${BUSINESS_ID} AND phone='60009999'); \
INSERT INTO party_invoice_data (party_id,dtipoRuc,druc,ddv,itipoRec,dnombRec,ddirecRec,dcodUbi,dcorreg,ddistr,dprov,dcorElectRec,didExt,dpaisExt,created_at,updated_at) \
SELECT p.id,'Jurídico','155705519-2-2021','37','01','Cliente E2E QA','Ciudad de Panama','8-10-01','8-10','8','8','cliente-e2e@example.com',NULL,NULL,NOW(),NOW() \
FROM parties p \
WHERE p.business_id=${BUSINESS_ID} AND p.phone='60009999' \
  AND NOT EXISTS (SELECT 1 FROM party_invoice_data pid WHERE pid.party_id=p.id); \
UPDATE party_invoice_data pid \
JOIN parties p ON p.id = pid.party_id \
SET pid.dtipoRuc='Jurídico', pid.druc='155705519-2-2021', pid.ddv='37', pid.itipoRec='01', pid.dnombRec='Cliente E2E QA', pid.ddirecRec='Ciudad de Panama', pid.dcodUbi='8-10-01', pid.dcorreg='8-10', pid.ddistr='8', pid.dprov='8', pid.dcorElectRec='cliente-e2e@example.com', pid.didExt=NULL, pid.dpaisExt=NULL, pid.updated_at=NOW() \
WHERE p.business_id=${BUSINESS_ID} AND p.phone='60009999'; \
INSERT INTO products (productName,business_id,unit_id,brand_id,category_id,productCode,productPicture,productDealerPrice,productPurchasePrice,productSalePrice,productWholeSalePrice,productStock,size,type,color,weight,capacity,productManufacturer,meta,track_by_batches,is_medicine,tax_rate,created_at,updated_at) \
SELECT 'Producto E2E EMAGIC',${BUSINESS_ID},(SELECT id FROM units WHERE business_id=${BUSINESS_ID} AND unitName='Unidad' LIMIT 1),(SELECT id FROM brands WHERE business_id=${BUSINESS_ID} AND brandName='QA Marca' LIMIT 1),(SELECT id FROM categories WHERE business_id=${BUSINESS_ID} AND categoryName='QA Categoria' LIMIT 1),CONCAT('E2E-',DATE_FORMAT(NOW(),'%Y%m%d%H%i%s')),NULL,12.00,10.00,15.00,15.00,50,NULL,NULL,NULL,NULL,NULL,'QA Labs',NULL,0,1,'7',NOW(),NOW() FROM DUAL \
WHERE NOT EXISTS (SELECT 1 FROM products WHERE business_id=${BUSINESS_ID} AND productName='Producto E2E EMAGIC');"

PARTY_ID="$(run_sql "USE laravel; SELECT id FROM parties WHERE business_id=${BUSINESS_ID} AND phone='60009999' ORDER BY id DESC LIMIT 1;")"
PRODUCT_ID="$(run_sql "USE laravel; SELECT id FROM products WHERE business_id=${BUSINESS_ID} AND productName='Producto E2E EMAGIC' ORDER BY id DESC LIMIT 1;")"
[[ -n "$PARTY_ID" ]] || fail "No se encontro party para la prueba"
[[ -n "$PRODUCT_ID" ]] || fail "No se encontro producto para la prueba"

echo "[INFO] Login as ${EMAIL}"
LOGIN_JSON="$(curl -sS -X POST "${BASE_URL}/api/v1/sign-in" -H 'Accept: application/json' -d "email=${EMAIL}" -d "password=${PASSWORD}")"
TOKEN="$(printf '%s' "$LOGIN_JSON" | php -r '$j=json_decode(stream_get_contents(STDIN), true); echo $j["data"]["token"] ?? ($j["token"] ?? "");')"
[[ -n "$TOKEN" ]] || fail "No se pudo obtener token. Respuesta: ${LOGIN_JSON}"

echo "[INFO] Create sale with real billing call"
SALE_PAYLOAD_FILE="/tmp/e2e_sale_payload.json"
cat > "$SALE_PAYLOAD_FILE" <<JSON
{
  "party_id": ${PARTY_ID},
  "customer_name": "Cliente E2E QA",
  "customer_phone": "60009999",
  "saleDate": "2026-04-16",
  "totalAmount": 30,
  "discountAmount": 0,
  "dueAmount": 0,
  "paidAmount": 30,
  "vat_amount": 2.1,
  "vat_percent": 7,
  "isPaid": true,
  "paymentType": "Cash",
  "products": [
    {
      "product_id": ${PRODUCT_ID},
      "quantities": 2,
      "price": 15,
      "lossProfit": 10
    }
  ]
}
JSON

SALE_RESPONSE_FILE="/tmp/e2e_sale_response.json"
SALE_CODE="$(curl -sS -o "$SALE_RESPONSE_FILE" -w '%{http_code}' -X POST "${BASE_URL}/api/v1/sales" -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer ${TOKEN}" --data @"$SALE_PAYLOAD_FILE")"
[[ "$SALE_CODE" == "200" ]] || fail "Venta fallo con HTTP ${SALE_CODE}. Respuesta: $(cat "$SALE_RESPONSE_FILE")"

SALE_ID="$(php -r '$j=json_decode(file_get_contents("/tmp/e2e_sale_response.json"), true); echo $j["data"]["id"] ?? "";')"
BILLING_SUCCESS="$(php -r '$j=json_decode(file_get_contents("/tmp/e2e_sale_response.json"), true); echo (isset($j["billing"]["success"]) && $j["billing"]["success"] ? "true" : "false");')"
BILLING_ERROR="$(php -r '$j=json_decode(file_get_contents("/tmp/e2e_sale_response.json"), true); echo $j["billing"]["error"] ?? "";')"
[[ -n "$SALE_ID" ]] || fail "No se pudo extraer sale_id. Respuesta: $(cat "$SALE_RESPONSE_FILE")"

echo "[INFO] Validate DB persistence"
DB_ROW="$(run_sql "USE laravel; SELECT id, invoiceNumber, JSON_UNQUOTE(JSON_EXTRACT(meta,'$.billing_status')), IFNULL((SELECT COUNT(*) FROM dgi_invoice di WHERE di.sale_id=sales.id),0) FROM sales WHERE id=${SALE_ID} LIMIT 1;")"
DGI_COUNT="$(run_sql "USE laravel; SELECT COUNT(*) FROM dgi_invoice WHERE sale_id=${SALE_ID};")"

PDF_PAYLOAD_FILE="/tmp/e2e_pdf_payload.json"
printf '{"sale_id":%s}\n' "$SALE_ID" > "$PDF_PAYLOAD_FILE"
PDF_RESPONSE_FILE="/tmp/e2e_pdf_response.json"
PDF_CODE="$(curl -sS -o "$PDF_RESPONSE_FILE" -w '%{http_code}' -X POST "${BASE_URL}/api/v1/dgi-pdf" -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer ${TOKEN}" --data @"$PDF_PAYLOAD_FILE")"
PDF_HAS_DATA="$(php -r '$j=json_decode(file_get_contents("/tmp/e2e_pdf_response.json"), true); $d=$j["data"] ?? ""; echo (is_string($d) && strlen($d) > 100 ? "true" : "false");')"

echo "[INFO] sale_id=${SALE_ID} billing_success=${BILLING_SUCCESS} dgi_invoice_count=${DGI_COUNT} pdf_code=${PDF_CODE}"
echo "[INFO] sales_row=${DB_ROW}"

if [[ "$BILLING_SUCCESS" != "true" ]]; then
  fail "Facturacion eMagic no fue exitosa. error=${BILLING_ERROR}; response=$(cat "$SALE_RESPONSE_FILE")"
fi

if [[ "$DGI_COUNT" -lt 1 ]]; then
  fail "No se encontro registro en dgi_invoice para sale_id=${SALE_ID}"
fi

if [[ "$PDF_CODE" != "200" || "$PDF_HAS_DATA" != "true" ]]; then
  fail "No se pudo recuperar PDF de facturacion. code=${PDF_CODE}; response=$(cat "$PDF_RESPONSE_FILE")"
fi

echo "[DONE] E2E venta + facturacion eMagic completado"
echo "[DONE] Evidencia: sale_id=${SALE_ID}, dgi_invoice_count=${DGI_COUNT}, pdf_code=${PDF_CODE}"
