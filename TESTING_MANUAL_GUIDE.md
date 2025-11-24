# Guía de Testing Manual - Sistema de Lotes

## 🎯 Objetivo
Esta guía te ayudará a probar manualmente todas las funcionalidades del sistema de gestión por lotes usando Postman, Insomnia o curl.

---

## 📋 Pre-requisitos

1. **Ejecutar migraciones**
```bash
cd "/Users/ivanguerrero/Documents/Ivan/Pos App/pos-pro-instadosis"
php artisan migrate
```

2. **Verificar servidor en ejecución**
```bash
php artisan serve
# El servidor debería estar en: http://localhost:8000
```

3. **Obtener token de autenticación**
```bash
POST http://localhost:8000/api/v1/sign-in
Content-Type: application/json

{
  "email": "tu_usuario@example.com",
  "password": "tu_contraseña"
}

# Guardar el token de la respuesta
```

4. **Configurar headers en todas las peticiones**
```
Authorization: Bearer {tu_token_aqui}
Content-Type: application/json
Accept: application/json
```

---

## 🧪 Casos de Prueba

### CASO 1: Crear Producto con Lotes

**Objetivo:** Verificar que se puede crear un producto con seguimiento por lotes

**Endpoint:** `POST http://localhost:8000/api/v1/products`

**Body:**
```json
{
  "productCode": "TEST-MED-001",
  "productName": "Paracetamol 500mg TEST",
  "category_id": 1,
  "unit_id": 1,
  "brand_id": 1,
  "track_by_batches": true,
  "is_medicine": true,
  "tax_rate": "7",
  "productStock": 0,
  "productSalePrice": 5.00,
  "productPurchasePrice": 3.00,
  "productDetails": "Medicamento para pruebas"
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ Respuesta incluye el producto creado con id
- ✅ `track_by_batches` = true
- ✅ `is_medicine` = true
- ✅ `tax_rate` = "7"

**Guardar:** `product_id` para siguientes pruebas

---

### CASO 2: Registrar Compra con Lote

**Objetivo:** Verificar que al comprar un producto con lotes se cree automáticamente el lote

**Endpoint:** `POST http://localhost:8000/api/v1/purchase`

**Body:**
```json
{
  "party_id": 1,
  "totalAmount": 300.00,
  "paidAmount": 300.00,
  "dueAmount": 0,
  "discountAmount": 0,
  "products": [
    {
      "product_id": {USAR_PRODUCT_ID_DEL_CASO_1},
      "quantities": 100,
      "purchasePrice": 3.00,
      "batch_number": "BATCH-TEST-001",
      "manufacture_date": "2024-01-15",
      "expiry_date": "2025-12-31"
    }
  ]
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ Compra creada exitosamente

**Verificar:**
```bash
GET http://localhost:8000/api/v1/product-batches/product/{product_id}

# Debería retornar el lote creado automáticamente
```

**Guardar:** `batch_id` del lote creado

---

### CASO 3: Consultar Lotes Disponibles

**Objetivo:** Verificar que se pueden consultar lotes ordenados por FEFO

**Endpoint:** `GET http://localhost:8000/api/v1/product-batches/product/{product_id}/available`

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ Lista de lotes ordenados por expiry_date (ASC)
- ✅ Solo lotes con available_quantity > 0
- ✅ Solo lotes activos (no expirados)

---

### CASO 4: Venta con FEFO Automático

**Objetivo:** Verificar que el sistema asigna lotes automáticamente usando FEFO

**Endpoint:** `POST http://localhost:8000/api/v1/sales`

**Body:**
```json
{
  "party_id": 1,
  "customer_name": "Cliente Prueba",
  "customer_phone": "555-0001",
  "totalAmount": 100.00,
  "paidAmount": 100.00,
  "dueAmount": 0,
  "discountAmount": 0,
  "products": [
    {
      "product_id": {USAR_PRODUCT_ID_DEL_CASO_1},
      "quantities": 20,
      "price": 5.00,
      "lossProfit": 2.00
    }
  ]
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ Venta creada exitosamente
- ✅ `sale_details` incluye campos de impuestos:
  - `subtotal`: 100.00 (20 × 5.00)
  - `tax_rate`: "7"
  - `tax_amount`: 7.00
  - `total`: 107.00
- ✅ `batchSaleDetails` muestra los lotes asignados

**Verificar Stock:**
```bash
GET http://localhost:8000/api/v1/product-batches/{batch_id}

# available_quantity debería haber disminuido en 20
# sold_quantity debería haber aumentado en 20
```

---

### CASO 5: Venta con Selección Manual de Lotes

**Objetivo:** Verificar que se pueden seleccionar manualmente los lotes

**Pre-requisito:** Crear un segundo lote del mismo producto (repetir CASO 2 con diferentes fechas)

**Endpoint:** `POST http://localhost:8000/api/v1/sales`

**Body:**
```json
{
  "party_id": 1,
  "customer_name": "Cliente Prueba 2",
  "totalAmount": 75.00,
  "paidAmount": 75.00,
  "dueAmount": 0,
  "discountAmount": 0,
  "products": [
    {
      "product_id": {USAR_PRODUCT_ID},
      "quantities": 15,
      "price": 5.00,
      "lossProfit": 2.00,
      "batch_allocations": [
        {
          "batch_id": {BATCH_ID_ESPECÍFICO},
          "quantity": 15
        }
      ]
    }
  ]
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ Se usa el lote especificado en batch_allocations
- ✅ No se aplica FEFO automático

---

### CASO 6: Crear Lote Manualmente

**Objetivo:** Verificar que se puede crear un lote sin necesidad de compra

**Endpoint:** `POST http://localhost:8000/api/v1/product-batches`

**Body:**
```json
{
  "product_id": {PRODUCT_ID},
  "batch_number": "MANUAL-BATCH-001",
  "quantity": 50,
  "purchase_price": 2.50,
  "manufacture_date": "2024-02-01",
  "expiry_date": "2025-06-30",
  "notes": "Lote creado manualmente para ajuste de inventario"
}
```

**Resultado Esperado:**
- ✅ Status: 201 Created
- ✅ Lote creado exitosamente
- ✅ `available_quantity` = 50
- ✅ `status` = "active"

---

### CASO 7: Descartar Lote (Vencido/Dañado)

**Objetivo:** Verificar que se puede descartar parcial o totalmente un lote

**Endpoint:** `POST http://localhost:8000/api/v1/product-batches/{batch_id}/discard`

**Body:**
```json
{
  "quantity": 10,
  "reason": "Productos dañados por humedad - prueba de descarte"
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ `available_quantity` disminuye en 10
- ✅ Se crea `BatchTransaction` con type="discard"

---

### CASO 8: Ajustar Stock de Lote

**Objetivo:** Verificar que se puede ajustar el stock de un lote

**Endpoint:** `POST http://localhost:8000/api/v1/product-batches/{batch_id}/adjust`

**Body (Agregar):**
```json
{
  "quantity": 5,
  "type": "add",
  "reason": "Corrección de inventario físico - encontrados 5 adicionales"
}
```

**Body (Restar):**
```json
{
  "quantity": 3,
  "type": "subtract",
  "reason": "Corrección de inventario físico - faltaban 3 unidades"
}
```

**Resultado Esperado:**
- ✅ Status: 200 OK
- ✅ `available_quantity` se ajusta correctamente
- ✅ Se crea `BatchTransaction` con type="adjustment"

---

### CASO 9: Verificar Comando de Vencimientos

**Objetivo:** Verificar que el comando detecta lotes próximos a vencer

**Pre-requisito:** Crear un lote con fecha de vencimiento cercana

```json
{
  "product_id": {PRODUCT_ID},
  "batch_number": "NEAR-EXPIRY-001",
  "quantity": 30,
  "purchase_price": 2.50,
  "manufacture_date": "2024-01-01",
  "expiry_date": "2024-02-15"  // Fecha próxima
}
```

**Comando:**
```bash
php artisan batches:check-expiring
```

**Resultado Esperado:**
- ✅ Console muestra: "Lotes vencidos encontrados: X"
- ✅ Console muestra: "Lotes próximos a vencer encontrados: X"
- ✅ Se crean notificaciones

**Verificar:**
```bash
GET http://localhost:8000/api/v1/batch-notifications/unread

# Debería retornar las notificaciones creadas
```

---

### CASO 10: Gestión de Notificaciones

**Objetivo:** Verificar el ciclo completo de notificaciones

**10.1. Listar Notificaciones**
```bash
GET http://localhost:8000/api/v1/batch-notifications
```

**10.2. Ver Solo No Leídas**
```bash
GET http://localhost:8000/api/v1/batch-notifications/unread
```

**10.3. Marcar como Leída**
```bash
POST http://localhost:8000/api/v1/batch-notifications/{notification_id}/read
```

**10.4. Descartar Notificación**
```bash
DELETE http://localhost:8000/api/v1/batch-notifications/{notification_id}
```

**10.5. Ver Estadísticas**
```bash
GET http://localhost:8000/api/v1/batch-notifications/stats
```

**Resultado Esperado:**
- ✅ Todos los endpoints responden correctamente
- ✅ Estados cambian apropiadamente (pending → read → dismissed)

---

### CASO 11: Validación de Stock Insuficiente

**Objetivo:** Verificar que el sistema rechaza ventas sin stock suficiente

**Endpoint:** `POST http://localhost:8000/api/v1/sales`

**Body (cantidad mayor al stock disponible):**
```json
{
  "party_id": 1,
  "customer_name": "Cliente Prueba Stock",
  "totalAmount": 0,
  "paidAmount": 0,
  "dueAmount": 0,
  "products": [
    {
      "product_id": {PRODUCT_ID},
      "quantities": 999999,
      "price": 5.00,
      "lossProfit": 0
    }
  ]
}
```

**Resultado Esperado:**
- ✅ Status: 400 Bad Request
- ✅ Mensaje de error: "stock not available for this product. Available quantity is: X"

---

### CASO 12: Cálculo de Impuestos Variables

**Objetivo:** Verificar que los impuestos se calculan correctamente según tax_rate

**12.1. Producto al 0%**
```json
// Crear producto con tax_rate = "0"
// Vender 10 unidades a $10 c/u
// Verificar: subtotal=100, tax_amount=0, total=100
```

**12.2. Producto al 7%**
```json
// Crear producto con tax_rate = "7"
// Vender 10 unidades a $10 c/u
// Verificar: subtotal=100, tax_amount=7, total=107
```

**12.3. Producto al 10%**
```json
// Crear producto con tax_rate = "10"
// Vender 10 unidades a $10 c/u
// Verificar: subtotal=100, tax_amount=10, total=110
```

**12.4. Producto al 15%**
```json
// Crear producto con tax_rate = "15"
// Vender 10 unidades a $10 c/u
// Verificar: subtotal=100, tax_amount=15, total=115
```

---

### CASO 13: Filtros Avanzados en Lotes

**Objetivo:** Verificar que los filtros funcionan correctamente

**13.1. Solo Activos**
```bash
GET http://localhost:8000/api/v1/product-batches?status=active
```

**13.2. Solo Vencidos**
```bash
GET http://localhost:8000/api/v1/product-batches?status=expired
```

**13.3. Próximos a Vencer**
```bash
GET http://localhost:8000/api/v1/product-batches?near_expiry=true
```

**13.4. Con Stock Disponible**
```bash
GET http://localhost:8000/api/v1/product-batches?with_stock=true
```

**13.5. Por Producto**
```bash
GET http://localhost:8000/api/v1/product-batches?product_id={PRODUCT_ID}
```

**13.6. Combinación**
```bash
GET http://localhost:8000/api/v1/product-batches?status=active&with_stock=true&product_id={PRODUCT_ID}
```

---

### CASO 14: Producto Sin Lotes (Backward Compatibility)

**Objetivo:** Verificar que productos sin lotes siguen funcionando normalmente

**14.1. Crear Producto Sin Lotes**
```json
{
  "productCode": "TEST-REGULAR-001",
  "productName": "Producto Regular TEST",
  "category_id": 1,
  "unit_id": 1,
  "track_by_batches": false,
  "is_medicine": false,
  "tax_rate": "10",
  "productStock": 100,
  "productSalePrice": 10.00,
  "productPurchasePrice": 5.00
}
```

**14.2. Vender Producto**
```json
{
  "party_id": 1,
  "customer_name": "Cliente Regular",
  "totalAmount": 50.00,
  "paidAmount": 50.00,
  "dueAmount": 0,
  "products": [
    {
      "product_id": {PRODUCT_ID_REGULAR},
      "quantities": 5,
      "price": 10.00,
      "lossProfit": 5.00
    }
  ]
}
```

**Resultado Esperado:**
- ✅ Venta exitosa
- ✅ `productStock` disminuye directamente
- ✅ NO se crean lotes
- ✅ NO se consulta BatchAllocationService
- ✅ Impuestos se calculan igual

---

## 📊 Checklist de Validación

Marca cada caso después de probarlo:

- [ ] CASO 1: Crear Producto con Lotes
- [ ] CASO 2: Registrar Compra con Lote
- [ ] CASO 3: Consultar Lotes Disponibles
- [ ] CASO 4: Venta con FEFO Automático
- [ ] CASO 5: Venta con Selección Manual
- [ ] CASO 6: Crear Lote Manualmente
- [ ] CASO 7: Descartar Lote
- [ ] CASO 8: Ajustar Stock de Lote
- [ ] CASO 9: Comando de Vencimientos
- [ ] CASO 10: Gestión de Notificaciones
- [ ] CASO 11: Validación de Stock Insuficiente
- [ ] CASO 12: Cálculo de Impuestos Variables
- [ ] CASO 13: Filtros Avanzados
- [ ] CASO 14: Backward Compatibility

---

## 🐛 Reporte de Bugs

Si encuentras algún problema, documenta:

1. **Caso de Prueba:** (número)
2. **Endpoint:** (URL completa)
3. **Body Enviado:** (JSON)
4. **Respuesta Recibida:** (JSON + status code)
5. **Comportamiento Esperado:** (descripción)
6. **Comportamiento Actual:** (descripción)
7. **Logs del Server:** (si aplica)

---

## 💡 Tips para Testing

### Ver Logs en Tiempo Real
```bash
tail -f storage/logs/laravel.log
```

### Verificar Rutas Registradas
```bash
php artisan route:list | grep batch
```

### Limpiar Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Revertir Migraciones (si necesitas empezar de nuevo)
```bash
php artisan migrate:rollback --step=7
php artisan migrate
```

### Ver Estado de Migraciones
```bash
php artisan migrate:status
```

---

## 📈 Métricas de Éxito

Al finalizar todas las pruebas, deberías tener:

✅ **14 casos de prueba exitosos**
✅ **0 errores críticos**
✅ **Todas las validaciones funcionando**
✅ **FEFO operando correctamente**
✅ **Impuestos calculados correctamente**
✅ **Notificaciones generándose automáticamente**
✅ **Backward compatibility confirmada**

---

## 📞 Siguiente Paso

Una vez completado el testing manual exitosamente:
1. Documentar resultados
2. Reportar bugs encontrados (si los hay)
3. Proceder con **FASE 3: Testing Automatizado**
4. Luego continuar con **FASE 4: Flutter Models**

---

**Última Actualización:** Fase 2 - Backend API completado
**Próxima Revisión:** Después de testing manual
