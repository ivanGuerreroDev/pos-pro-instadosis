# Sistema de Notificaciones de Lotes Vacíos

## 📋 Resumen de Cambios Implementados

Se ha mejorado el sistema de notificaciones de lotes para incluir alertas cuando un lote llega a cantidad 0.

---

## ✨ Nuevas Funcionalidades

### 1. **Notificaciones de Lotes Vacíos**
- Ahora se genera automáticamente una notificación cuando un lote llega a `remaining_quantity = 0`
- Tipo de notificación: `'out_of_stock'`
- Las notificaciones de lotes vacíos **NO se eliminan** automáticamente

### 2. **Visualización de Lotes en 0**
- Los lotes con cantidad 0 ahora se muestran en el API
- Endpoint: `GET /api/batches`
- Parámetros de filtro disponibles:
  - `out_of_stock=true` - Solo lotes en 0
  - `with_stock=true` - Solo lotes con stock
  - Sin parámetro - Muestra todos los lotes (incluyendo los en 0)

---

## 🔧 Archivos Modificados

### 1. **ProductBatch.php** (`app/Models/ProductBatch.php`)
- Método `decreaseQuantity()` modificado para crear notificación automática cuando cantidad llega a 0

```php
public function decreaseQuantity(int $quantity): bool
{
    // ... código existente ...
    
    // Create notification if batch is now out of stock
    if ($saved && $this->remaining_quantity <= 0) {
        ExpiredBatchNotification::createOrUpdate(
            $this->id,
            $this->business_id,
            'out_of_stock',
            0
        );
    }
    
    return $saved;
}
```

### 2. **ExpiryNotificationService.php** (`app/Services/ExpiryNotificationService.php`)

#### Cambios realizados:

**a) Verificación de lotes sin stock**
```php
public function checkExpiringBatches(): array
{
    // Se agregó contador 'out_of_stock'
    $results = [
        'expired' => 0,
        'near_expiry_7' => 0,
        'near_expiry_15' => 0,
        'near_expiry_30' => 0,
        'out_of_stock' => 0,  // ✅ NUEVO
        'updated_status' => 0,
    ];
    
    foreach ($batches as $batch) {
        // ✅ NUEVO: Verificar lotes sin stock primero
        if ($batch->remaining_quantity <= 0) {
            $this->createOrUpdateNotification(
                $batch,
                'out_of_stock',
                0
            );
            $results['out_of_stock']++;
            continue;
        }
        // ... resto del código
    }
}
```

**b) Modificación de limpieza**
```php
public function cleanupOldNotifications(): int
{
    // Solo elimina notificaciones de lotes descartados
    // YA NO elimina notificaciones de lotes en 0
    $discardedBatches = ProductBatch::where('status', 'discarded')->pluck('id');
    $deletedDiscarded = ExpiredBatchNotification::whereIn('batch_id', $discardedBatches)->delete();
    
    return $deletedDiscarded;
}
```

**c) Estadísticas actualizadas**
```php
public function getNotificationStats(int $businessId): array
{
    return [
        'total_notifications' => ...,
        'critical' => ...,
        'warning' => ...,
        'info' => ...,
        'expired' => ...,
        'out_of_stock' => ...  // ✅ NUEVO
    ];
}
```

### 3. **ProductBatchController.php** (`app/Http/Controllers/Api/ProductBatchController.php`)

```php
public function index(Request $request)
{
    // Filtros opcionales para stock
    if ($request->has('out_of_stock') && $request->out_of_stock) {
        $query->where('remaining_quantity', '<=', 0);
    } elseif ($request->has('with_stock') && $request->with_stock) {
        $query->where('remaining_quantity', '>', 0);
    }
    // Por defecto, muestra TODOS los lotes (incluyendo en 0)
}
```

### 4. **CheckExpiringBatches.php** (`app/Console/Commands/CheckExpiringBatches.php`)
- Agregada línea de reporte para lotes sin stock

```php
$this->line("  Out of stock batches: {$results['out_of_stock']}");
```

---

## 🗄️ Cambios en Base de Datos

### Nueva Migración
**Archivo:** `2025_12_03_073854_add_out_of_stock_type_to_expired_batches_notifications_table.php`

```sql
ALTER TABLE expired_batches_notifications 
MODIFY COLUMN notification_type ENUM('near_expiry', 'expired', 'out_of_stock')
```

**Ejecutar:**
```bash
php artisan migrate
```

---

## 📡 Endpoints API

### **GET /api/batches**
Obtiene todos los lotes (incluyendo los en 0 por defecto)

**Parámetros de filtro:**
- `?out_of_stock=true` - Solo lotes vacíos
- `?with_stock=true` - Solo lotes con stock
- `?product_id=123` - Filtrar por producto
- `?status=active` - Filtrar por estado
- `?near_expiry=30` - Filtrar próximos a vencer

**Ejemplo de respuesta:**
```json
{
  "message": "Data fetched successfully.",
  "data": [
    {
      "id": 1,
      "batch_number": "LOT-001",
      "remaining_quantity": 0,
      "status": "active",
      "is_near_expiry": false,
      "status_display": "Agotado"
    }
  ]
}
```

### **GET /api/batch-notifications**
Obtiene notificaciones de lotes

**Parámetros:**
- `?notification_type=out_of_stock` - Solo notificaciones de lotes vacíos
- `?notification_type=expired` - Solo lotes vencidos
- `?notification_type=near_expiry` - Solo próximos a vencer
- `?is_read=false` - No leídas
- `?is_dismissed=false` - No descartadas

**Ejemplo de respuesta:**
```json
{
  "message": "Data fetched successfully.",
  "data": [
    {
      "id": 1,
      "batch_id": 5,
      "notification_type": "out_of_stock",
      "days_until_expiry": 0,
      "is_read": false,
      "is_dismissed": false,
      "batch": {
        "batch_number": "LOT-001",
        "remaining_quantity": 0,
        "product": {
          "productName": "Medicamento X"
        }
      }
    }
  ],
  "stats": {
    "total_notifications": 15,
    "critical": 3,
    "warning": 5,
    "info": 4,
    "expired": 2,
    "out_of_stock": 1
  }
}
```

### **GET /api/batch-notifications/stats**
Obtiene estadísticas de notificaciones

```json
{
  "data": {
    "total_notifications": 15,
    "critical": 3,
    "warning": 5,
    "info": 4,
    "expired": 2,
    "out_of_stock": 1
  }
}
```

---

## 🔄 Flujo de Trabajo

### Cuando un lote llega a 0:

1. **Venta/Ajuste reduce cantidad**
   ```php
   $batch->decreaseQuantity($quantity);
   ```

2. **Se guarda el lote con `remaining_quantity = 0`**

3. **Automáticamente se crea notificación**
   ```php
   ExpiredBatchNotification::createOrUpdate(
       $batch->id,
       $batch->business_id,
       'out_of_stock',
       0
   );
   ```

4. **La notificación aparece en:**
   - `GET /api/batch-notifications?notification_type=out_of_stock`
   - `GET /api/batch-notifications/unread`
   - Dashboard de estadísticas

5. **El lote sigue visible en:**
   - `GET /api/batches` (por defecto)
   - `GET /api/batches?out_of_stock=true` (filtrado)

---

## 🎯 Tipos de Notificaciones

| Tipo | Descripción | `days_until_expiry` |
|------|-------------|---------------------|
| `near_expiry` | Lote próximo a vencer (≤30 días) | 1-30 |
| `expired` | Lote vencido | 0 |
| `out_of_stock` | Lote sin stock disponible | 0 |

---

## ⏰ Tarea Programada

**Comando:** `php artisan batches:check-expiry`

**Programación:** Diariamente a las 00:00 (medianoche)

**Ubicación:** `app/Console/Kernel.php`
```php
$schedule->command('batches:check-expiry')->daily()->at('00:00');
```

**Ejecución manual:**
```bash
php artisan batches:check-expiry
php artisan batches:check-expiry --cleanup
```

**Salida del comando:**
```
Checking for expiring batches...

Results:
  Expired batches: 2
  Near expiry (7 days): 3
  Near expiry (15 days): 5
  Near expiry (30 days): 8
  Out of stock batches: 4      ← NUEVO
  Batch statuses updated: 2

Batch expiry check completed successfully!
```

---

## 🧪 Pruebas

### Probar notificación de lote vacío:

1. **Crear un lote con stock bajo**
```bash
POST /api/batches
{
  "product_id": 1,
  "quantity": 5,
  "batch_number": "TEST-001"
}
```

2. **Reducir cantidad a 0**
```bash
POST /api/batches/{id}/adjust
{
  "adjustment_type": "remove",
  "quantity": 5,
  "reason": "Test"
}
```

3. **Verificar notificación creada**
```bash
GET /api/batch-notifications?notification_type=out_of_stock
```

4. **Verificar lote visible**
```bash
GET /api/batches
GET /api/batches?out_of_stock=true
```

---

## 📊 Beneficios

✅ **Visibilidad completa:** Los lotes en 0 no desaparecen del sistema
✅ **Alertas proactivas:** Notificación automática cuando un lote se agota
✅ **Mejor gestión:** Facilita la reposición y seguimiento de inventario
✅ **Historial completo:** Se mantiene el registro de todos los lotes
✅ **APIs flexibles:** Filtros para ver todos, solo vacíos, o solo con stock

---

## 🔍 Configuración Hardcoded

Los días de alerta para vencimiento están configurados en:
- **7 días** - Alerta crítica
- **15 días** - Alerta de advertencia
- **30 días** - Alerta informativa

Para hacerlos configurables por negocio, agregar campos a la tabla `businesses`.

---

## 📝 Notas Importantes

- Las notificaciones de lotes vacíos **NO se eliminan** en el cleanup
- Solo se eliminan notificaciones de lotes con estado `'discarded'`
- Los lotes en 0 mantienen su estado `'active'` (no cambian a otro estado)
- La verificación de stock se ejecuta **antes** de verificar vencimiento
- Si un lote está en 0, no genera notificaciones de vencimiento

---

Fecha de implementación: 3 de diciembre de 2025
