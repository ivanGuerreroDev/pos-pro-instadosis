# Actualización de Periodicidad de Notificaciones de Lotes por Vencer

**Fecha de actualización:** 2 de diciembre de 2025

---

## 📊 Nuevos Períodos de Alerta

El sistema de notificaciones de lotes por vencer ha sido actualizado para alertar en los siguientes períodos:

| Período | Días | Categoría | Descripción |
|---------|------|-----------|-------------|
| **3 meses** | 90 días | Info | Alerta informativa temprana |
| **2 meses** | 60 días | Warning | Alerta de advertencia |
| **1 mes** | 30 días | Warning | Alerta de advertencia importante |
| **15 días** | 15 días | Crítico | Alerta crítica |
| **Vencido** | 0 días | Expired | Lote ya vencido |

---

## 🔧 Cambios Realizados

### 1. **ExpiryNotificationService.php**

**Ubicación:** `app/Services/ExpiryNotificationService.php`

#### a) Actualización de contadores de resultados

```php
$results = [
    'expired' => 0,
    'near_expiry_15' => 0,   // 15 días
    'near_expiry_30' => 0,   // 1 mes (30 días)
    'near_expiry_60' => 0,   // 2 meses (60 días)
    'near_expiry_90' => 0,   // 3 meses (90 días)
    'out_of_stock' => 0,
    'updated_status' => 0,
];
```

#### b) Lógica de verificación actualizada

```php
if ($daysUntilExpiry !== null) {
    if ($daysUntilExpiry <= 15 && $daysUntilExpiry > 0) {
        // Alerta crítica: 15 días o menos
        $this->createOrUpdateNotification($batch, 'near_expiry', $daysUntilExpiry);
        $results['near_expiry_15']++;
    } elseif ($daysUntilExpiry <= 30 && $daysUntilExpiry > 15) {
        // 1 mes: entre 16 y 30 días
        $this->createOrUpdateNotification($batch, 'near_expiry', $daysUntilExpiry);
        $results['near_expiry_30']++;
    } elseif ($daysUntilExpiry <= 60 && $daysUntilExpiry > 30) {
        // 2 meses: entre 31 y 60 días
        $this->createOrUpdateNotification($batch, 'near_expiry', $daysUntilExpiry);
        $results['near_expiry_60']++;
    } elseif ($daysUntilExpiry <= 90 && $daysUntilExpiry > 60) {
        // 3 meses: entre 61 y 90 días
        $this->createOrUpdateNotification($batch, 'near_expiry', $daysUntilExpiry);
        $results['near_expiry_90']++;
    }
}
```

#### c) Estadísticas actualizadas

```php
public function getNotificationStats(int $businessId): array
{
    return [
        'total_notifications' => ...,
        'critical' => ...,           // <= 15 días
        'warning_30' => ...,         // 16-30 días (1 mes)
        'warning_60' => ...,         // 31-60 días (2 meses)
        'info' => ...,               // 61-90 días (3 meses)
        'expired' => ...,            // Vencidos
        'out_of_stock' => ...,       // Sin stock
    ];
}
```

---

### 2. **CheckExpiringBatches.php**

**Ubicación:** `app/Console/Commands/CheckExpiringBatches.php`

Salida del comando actualizada:

```php
$this->line("  Expired batches: {$results['expired']}");
$this->line("  Near expiry (15 days): {$results['near_expiry_15']}");
$this->line("  Near expiry (1 month/30 days): {$results['near_expiry_30']}");
$this->line("  Near expiry (2 months/60 days): {$results['near_expiry_60']}");
$this->line("  Near expiry (3 months/90 days): {$results['near_expiry_90']}");
$this->line("  Out of stock batches: {$results['out_of_stock']}");
```

---

### 3. **ProductBatch.php**

**Ubicación:** `app/Models/ProductBatch.php`

#### a) Método `isNearExpiry()` actualizado

```php
public function isNearExpiry($days = 90): bool
{
    // Ahora el valor por defecto es 90 días (3 meses)
    // en lugar de 30 días
}
```

#### b) Atributo `expiry_warning` mejorado

```php
public function getExpiryWarningAttribute(): string
{
    $days = $this->getDaysUntilExpiry();
    if ($days !== null && $days <= 90 && $days > 0) {
        if ($days <= 15) {
            return "Vence en $days días - CRÍTICO";
        } elseif ($days <= 30) {
            return "Vence en $days días (1 mes)";
        } elseif ($days <= 60) {
            return "Vence en $days días (2 meses)";
        } else {
            return "Vence en $days días (3 meses)";
        }
    }
    return '';
}
```

---

## 📡 Endpoints API

### **GET /api/batch-notifications**

Las notificaciones ahora incluirán lotes que vencen en hasta 90 días (3 meses).

```json
{
  "data": [
    {
      "id": 1,
      "batch_id": 5,
      "notification_type": "near_expiry",
      "days_until_expiry": 75,
      "message": "Vence en 75 días (3 meses)"
    },
    {
      "id": 2,
      "batch_id": 8,
      "notification_type": "near_expiry",
      "days_until_expiry": 45,
      "message": "Vence en 45 días (2 meses)"
    }
  ]
}
```

### **GET /api/batch-notifications/stats**

Las estadísticas ahora están segmentadas por período:

```json
{
  "data": {
    "total_notifications": 25,
    "critical": 3,        // <= 15 días
    "warning_30": 5,      // 16-30 días
    "warning_60": 8,      // 31-60 días
    "info": 7,            // 61-90 días
    "expired": 2,
    "out_of_stock": 0
  }
}
```

---

## ⏰ Ejecución del Comando

### Manual

```bash
php artisan batches:check-expiry
```

**Salida esperada:**
```
Checking for expiring batches...

Results:
  Expired batches: 2
  Near expiry (15 days): 3
  Near expiry (1 month/30 days): 5
  Near expiry (2 months/60 days): 8
  Near expiry (3 months/90 days): 12
  Out of stock batches: 1
  Batch statuses updated: 2

Batch expiry check completed successfully!
```

### Automático

El comando se ejecuta automáticamente cada día a las **00:00** (medianoche).

**Configuración:** `app/Console/Kernel.php`
```php
$schedule->command('batches:check-expiry')->daily()->at('00:00');
```

---

## 🎯 Flujo de Notificaciones

```
┌─────────────────────────────────────────┐
│  Lote con fecha de vencimiento          │
└──────────────┬──────────────────────────┘
               │
               ▼
      ¿Cuántos días faltan?
               │
      ┌────────┴────────┐
      │                 │
  <= 90 días       > 90 días
      │                 │
      ▼                 ▼
  Genera           No genera
  notificación     notificación
      │
      └─────┬──────────────────────────────┐
            │                              │
      <= 15 días                    16-90 días
      │                                    │
      ▼                                    ▼
  CRÍTICO                           WARNING/INFO
  (near_expiry)                     (near_expiry)
```

---

## 📊 Categorización de Notificaciones

### Por Urgencia

| Días hasta vencer | Categoría | Color sugerido | Acción recomendada |
|------------------|-----------|----------------|-------------------|
| 0 (vencido) | Expired | Rojo oscuro | Descartar inmediatamente |
| 1-15 días | Crítico | Rojo | Vender o usar urgente |
| 16-30 días | Warning | Naranja | Promover venta |
| 31-60 días | Warning | Amarillo | Monitorear |
| 61-90 días | Info | Azul | Información |

---

## ✅ Ventajas de los Nuevos Períodos

1. **Mayor anticipación:** Notificaciones desde 3 meses antes permiten mejor planificación
2. **Menos pérdidas:** Más tiempo para tomar acciones correctivas
3. **Mejor gestión:** Permite estrategias de venta progresivas
4. **Visibilidad mejorada:** Clasificación clara por urgencia

---

## 🔧 Configuración Hardcoded

Los períodos de alerta están actualmente configurados como valores fijos:
- **90 días** (3 meses) - Alerta informativa
- **60 días** (2 meses) - Alerta de advertencia
- **30 días** (1 mes) - Alerta de advertencia importante
- **15 días** - Alerta crítica

### Futura Mejora: Configuración por Negocio

Para hacer estos períodos configurables por cada negocio, se pueden agregar campos a la tabla `businesses`:

```php
// Migración futura sugerida
Schema::table('businesses', function (Blueprint $table) {
    $table->integer('alert_days_critical')->default(15);
    $table->integer('alert_days_warning_1')->default(30);
    $table->integer('alert_days_warning_2')->default(60);
    $table->integer('alert_days_info')->default(90);
});
```

---

## 📝 Notas Importantes

- Las notificaciones se generan automáticamente cada día a medianoche
- Los lotes sin stock (remaining_quantity = 0) generan notificación de tipo `out_of_stock`
- Los lotes descartados (`status = 'discarded'`) tienen sus notificaciones eliminadas
- Las notificaciones se actualizan si ya existen para el mismo lote
- Un lote puede tener solo una notificación activa a la vez (se actualiza según el período más reciente)

---

## 🔍 Retrocompatibilidad

✅ **Los cambios son retrocompatibles:**
- Las notificaciones existentes siguen funcionando
- La API mantiene la misma estructura de respuesta
- Solo se amplió el rango de días para generar notificaciones
- Los filtros existentes siguen funcionando correctamente

---

**Implementado por:** GitHub Copilot
**Fecha:** 2 de diciembre de 2025
