# Guía de API - Sistema de Gestión por Lotes

## Tabla de Contenidos
1. [Configuración de Productos](#configuración-de-productos)
2. [Gestión de Lotes](#gestión-de-lotes)
3. [Ventas con Lotes](#ventas-con-lotes)
4. [Notificaciones de Vencimiento](#notificaciones-de-vencimiento)
5. [Impuestos Variables](#impuestos-variables)

---

## Configuración de Productos

### Crear Producto con Seguimiento por Lotes

**Endpoint:** `POST /api/v1/products`

```json
{
  "productCode": "MED-001",
  "productName": "Paracetamol 500mg",
  "category_id": 1,
  "unit_id": 1,
  "track_by_batches": true,
  "is_medicine": true,
  "tax_rate": "7",
  "productStock": 0,
  "productSalePrice": 5.00,
  "productPurchasePrice": 3.00
}
```

**Campos Clave:**
- `track_by_batches`: `true` para activar gestión por lotes
- `is_medicine`: `true` si es medicamento
- `tax_rate`: `"0"`, `"7"`, `"10"`, o `"15"`

### Actualizar Producto

**Endpoint:** `PUT /api/v1/products/{id}`

```json
{
  "productCode": "MED-001",
  "productName": "Paracetamol 500mg",
  "is_medicine": true,
  "tax_rate": "10"
}
```

---

## Gestión de Lotes

### Listar Todos los Lotes

**Endpoint:** `GET /api/v1/product-batches`

**Parámetros Query:**
- `?status=active` - Solo lotes activos
- `?status=expired` - Solo lotes vencidos
- `?near_expiry=true` - Lotes próximos a vencer (< 30 días)
- `?product_id=1` - Filtrar por producto
- `?with_stock=true` - Solo lotes con stock disponible

**Respuesta:**
```json
{
  "message": "Lotes obtenidos exitosamente",
  "data": [
    {
      "id": 1,
      "product_id": 5,
      "purchase_id": 10,
      "batch_number": "BATCH-20240115-001",
      "quantity": 100,
      "available_quantity": 85,
      "sold_quantity": 15,
      "purchase_price": 3.00,
      "manufacture_date": "2024-01-15",
      "expiry_date": "2025-01-15",
      "status": "active",
      "product": {
        "id": 5,
        "productName": "Paracetamol 500mg"
      }
    }
  ]
}
```

### Crear Lote (Manual)

**Endpoint:** `POST /api/v1/product-batches`

```json
{
  "product_id": 5,
  "batch_number": "BATCH-20240115-002",
  "quantity": 200,
  "purchase_price": 3.50,
  "manufacture_date": "2024-01-15",
  "expiry_date": "2025-06-15",
  "notes": "Lote de emergencia"
}
```

> **Nota:** Los lotes normalmente se crean automáticamente al hacer una compra.

### Ver Detalles de un Lote

**Endpoint:** `GET /api/v1/product-batches/{id}`

**Respuesta:**
```json
{
  "message": "Lote obtenido exitosamente",
  "data": {
    "id": 1,
    "product_id": 5,
    "batch_number": "BATCH-20240115-001",
    "quantity": 100,
    "available_quantity": 85,
    "sold_quantity": 15,
    "purchase_price": 3.00,
    "manufacture_date": "2024-01-15",
    "expiry_date": "2025-01-15",
    "days_until_expiry": 365,
    "is_expired": false,
    "is_near_expiry": false,
    "status": "active",
    "product": {
      "id": 5,
      "productName": "Paracetamol 500mg"
    },
    "transactions": [
      {
        "id": 1,
        "type": "purchase",
        "quantity": 100,
        "created_at": "2024-01-15T10:00:00"
      }
    ]
  }
}
```

### Actualizar Lote

**Endpoint:** `PUT /api/v1/product-batches/{id}`

```json
{
  "expiry_date": "2025-12-31",
  "notes": "Fecha actualizada por proveedor"
}
```

### Descartar Lote (Vencido/Dañado)

**Endpoint:** `POST /api/v1/product-batches/{id}/discard`

```json
{
  "quantity": 10,
  "reason": "Productos dañados por humedad"
}
```

### Ajustar Stock de Lote

**Endpoint:** `POST /api/v1/product-batches/{id}/adjust`

```json
{
  "quantity": 5,
  "type": "add",
  "reason": "Corrección de inventario físico"
}
```

**Tipos de Ajuste:**
- `"add"`: Agregar unidades
- `"subtract"`: Restar unidades

### Lotes de un Producto Específico

**Endpoint:** `GET /api/v1/product-batches/product/{productId}`

**Parámetros Query:**
- `?status=active`
- `?with_stock=true`

### Lotes Disponibles para Venta (FEFO)

**Endpoint:** `GET /api/v1/product-batches/product/{productId}/available`

**Respuesta:**
```json
{
  "message": "Lotes disponibles obtenidos exitosamente",
  "data": [
    {
      "id": 1,
      "batch_number": "BATCH-20240115-001",
      "available_quantity": 85,
      "expiry_date": "2025-01-15",
      "days_until_expiry": 365
    },
    {
      "id": 2,
      "batch_number": "BATCH-20240120-001",
      "available_quantity": 200,
      "expiry_date": "2025-06-15",
      "days_until_expiry": 515
    }
  ]
}
```

> Los lotes están ordenados por fecha de vencimiento (FEFO - First Expired, First Out)

---

## Ventas con Lotes

### Realizar Venta con Asignación Automática (FEFO)

**Endpoint:** `POST /api/v1/sales`

```json
{
  "party_id": 5,
  "totalAmount": 150.00,
  "paidAmount": 150.00,
  "dueAmount": 0,
  "discountAmount": 0,
  "products": [
    {
      "product_id": 5,
      "quantities": 20,
      "price": 5.00,
      "lossProfit": 2.00
    }
  ]
}
```

> El sistema asignará automáticamente los lotes usando FEFO (primero los que vencen antes)

**Respuesta:**
```json
{
  "message": "Data saved successfully.",
  "data": {
    "id": 100,
    "totalAmount": 150.00,
    "details": [
      {
        "id": 150,
        "product_id": 5,
        "quantities": 20,
        "subtotal": 100.00,
        "tax_rate": "7",
        "tax_amount": 7.00,
        "total": 107.00,
        "batchSaleDetails": [
          {
            "id": 1,
            "product_batch_id": 1,
            "quantity": 20,
            "productBatch": {
              "batch_number": "BATCH-20240115-001",
              "expiry_date": "2025-01-15"
            }
          }
        ]
      }
    ]
  }
}
```

### Realizar Venta con Selección Manual de Lotes

**Endpoint:** `POST /api/v1/sales`

```json
{
  "party_id": 5,
  "totalAmount": 150.00,
  "paidAmount": 150.00,
  "dueAmount": 0,
  "discountAmount": 0,
  "products": [
    {
      "product_id": 5,
      "quantities": 30,
      "price": 5.00,
      "lossProfit": 2.00,
      "batch_allocations": [
        {
          "batch_id": 2,
          "quantity": 20
        },
        {
          "batch_id": 3,
          "quantity": 10
        }
      ]
    }
  ]
}
```

> Usa `batch_allocations` para especificar manualmente qué lotes usar

---

## Compras con Lotes

### Crear Compra con Lotes Automáticos

**Endpoint:** `POST /api/v1/purchase`

```json
{
  "party_id": 10,
  "totalAmount": 300.00,
  "paidAmount": 300.00,
  "dueAmount": 0,
  "products": [
    {
      "product_id": 5,
      "quantities": 100,
      "purchasePrice": 3.00,
      "batch_number": "BATCH-SUPPLIER-XYZ123",
      "manufacture_date": "2024-01-15",
      "expiry_date": "2025-12-31"
    }
  ]
}
```

**Campos de Lote en Compra:**
- `batch_number`: (Opcional) Número de lote del proveedor
- `manufacture_date`: Fecha de fabricación
- `expiry_date`: Fecha de vencimiento

> Si el producto tiene `track_by_batches = true`, se creará automáticamente un lote

---

## Notificaciones de Vencimiento

### Listar Todas las Notificaciones

**Endpoint:** `GET /api/v1/batch-notifications`

**Parámetros Query:**
- `?status=pending` - Solo pendientes
- `?status=read` - Solo leídas
- `?status=dismissed` - Solo descartadas
- `?product_id=5` - Por producto
- `?notification_type=near_expiry` - Tipo específico

**Respuesta:**
```json
{
  "message": "Notificaciones obtenidas exitosamente",
  "data": [
    {
      "id": 1,
      "product_batch_id": 1,
      "notification_type": "near_expiry",
      "message": "El lote BATCH-20240115-001 vencerá en 15 días",
      "status": "pending",
      "notified_at": "2024-12-31T00:00:00",
      "productBatch": {
        "id": 1,
        "batch_number": "BATCH-20240115-001",
        "expiry_date": "2025-01-15",
        "product": {
          "id": 5,
          "productName": "Paracetamol 500mg"
        }
      }
    }
  ]
}
```

### Notificaciones No Leídas

**Endpoint:** `GET /api/v1/batch-notifications/unread`

### Marcar como Leída

**Endpoint:** `POST /api/v1/batch-notifications/{id}/read`

**Respuesta:**
```json
{
  "message": "Notificación marcada como leída",
  "data": {
    "id": 1,
    "status": "read",
    "read_at": "2024-01-10T15:30:00"
  }
}
```

### Descartar Notificación

**Endpoint:** `DELETE /api/v1/batch-notifications/{id}`

### Estadísticas de Notificaciones

**Endpoint:** `GET /api/v1/batch-notifications/stats`

**Respuesta:**
```json
{
  "message": "Estadísticas obtenidas exitosamente",
  "data": {
    "total": 10,
    "pending": 5,
    "read": 3,
    "dismissed": 2,
    "near_expiry": 4,
    "expired": 1
  }
}
```

---

## Impuestos Variables

### Cálculo Automático de Impuestos

Los impuestos se calculan automáticamente al crear una venta según el `tax_rate` del producto:

| tax_rate | Porcentaje | Uso Típico |
|----------|-----------|------------|
| 0 | 0% | Productos exentos |
| 7 | 7% | Medicamentos y productos médicos |
| 10 | 10% | Productos generales |
| 15 | 15% | Productos premium |

**Ejemplo en Sale Detail:**
```json
{
  "product_id": 5,
  "quantities": 10,
  "price": 5.00,
  "subtotal": 50.00,
  "tax_rate": "7",
  "tax_amount": 3.50,
  "total": 53.50
}
```

**Fórmula:**
- `subtotal = price × quantities`
- `tax_amount = subtotal × (tax_rate / 100)`
- `total = subtotal + tax_amount`

---

## Comandos Artisan

### Verificar Lotes Próximos a Vencer

```bash
php artisan batches:check-expiring
```

**Qué hace:**
- Busca lotes que vencen en menos de 30 días
- Marca lotes vencidos como `expired`
- Crea notificaciones automáticas
- Se ejecuta automáticamente cada día a medianoche

### Ejecutar Manualmente

```bash
php artisan batches:check-expiring
```

---

## Migraciones

### Ejecutar Migraciones

```bash
php artisan migrate
```

**Tablas Creadas:**
1. `product_batches` - Lotes de productos
2. `batch_transactions` - Historial de movimientos
3. `expired_batches_notifications` - Notificaciones de vencimiento
4. `batch_sale_details` - Relación venta-lote
5. Modificaciones a `products` - Campos de lotes e impuestos
6. Modificaciones a `sale_details` - Campos de impuestos

---

## Ejemplos de Flujo Completo

### Flujo 1: Compra → Stock → Venta

**1. Crear Producto con Lotes**
```json
POST /api/v1/products
{
  "productCode": "MED-100",
  "productName": "Ibuprofeno 400mg",
  "track_by_batches": true,
  "is_medicine": true,
  "tax_rate": "7"
}
```

**2. Registrar Compra (Crea Lote Automáticamente)**
```json
POST /api/v1/purchase
{
  "party_id": 20,
  "totalAmount": 500.00,
  "products": [
    {
      "product_id": 10,
      "quantities": 200,
      "purchasePrice": 2.50,
      "manufacture_date": "2024-01-15",
      "expiry_date": "2026-01-15"
    }
  ]
}
```

**3. Consultar Lotes Disponibles**
```
GET /api/v1/product-batches/product/10/available
```

**4. Realizar Venta (FEFO Automático)**
```json
POST /api/v1/sales
{
  "party_id": 50,
  "totalAmount": 100.00,
  "products": [
    {
      "product_id": 10,
      "quantities": 20,
      "price": 5.00
    }
  ]
}
```

---

## Códigos de Estado

| Código | Descripción |
|--------|-------------|
| 200 | Operación exitosa |
| 201 | Recurso creado |
| 400 | Error de validación |
| 404 | Recurso no encontrado |
| 500 | Error del servidor |

---

## Notas Importantes

### Productos sin Seguimiento por Lotes
- Si `track_by_batches = false`, el producto funciona de forma tradicional
- El stock se actualiza directamente en `productStock`
- No se crean lotes ni se aplica FEFO

### Validaciones Automáticas
- No se puede vender más stock del disponible
- Los lotes se asignan automáticamente por FEFO
- Las fechas de vencimiento se validan al crear lotes
- Los impuestos se calculan automáticamente

### Transacciones
- Todas las operaciones de venta usan transacciones DB
- Si falla cualquier paso, se hace rollback completo
- Garantiza integridad de datos

---

## Soporte y Preguntas

Para más información sobre la implementación, consultar:
- `PLAN_IMPLEMENTACION_LOTES.md` - Plan completo
- Modelos en `app/Models/`
- Servicios en `app/Services/`
- Controladores en `app/Http/Controllers/Api/`
