# Sistema de Notificaciones por Email - Lotes por Vencer

**Fecha de implementación:** 2 de diciembre de 2025

---

## 🚀 Nueva Funcionalidad: Notificaciones por Email

El sistema de notificaciones de lotes por vencer ahora **envía emails automáticos** a los usuarios del negocio cuando se detecta un lote próximo a vencer, vencido o sin stock.

---

## 📧 Características de los Emails

### Destinatarios
Los emails se envían automáticamente a:
- ✅ Usuarios con rol **admin**
- ✅ Usuarios con rol **manager**
- ✅ Usuarios con rol **owner**
- ✅ Que tengan email registrado
- ✅ Que pertenezcan al negocio del lote

### Tipos de Notificaciones por Email

| Tipo | Asunto | Prioridad | Icono |
|------|--------|-----------|-------|
| **Vencido** | ⚠️ Alerta: Lote Vencido | VENCIDO | 🚫 |
| **15 días** | 🚨 URGENTE: Lote por Vencer en X días | CRÍTICO | ⏰ |
| **30 días (1 mes)** | ⚠️ Importante: Lote por Vencer en X días | URGENTE | ⏰ |
| **60 días (2 meses)** | ⚡ Aviso: Lote por Vencer en X días | ADVERTENCIA | ⏰ |
| **90 días (3 meses)** | 📅 Información: Lote por Vencer en X días | INFORMACIÓN | ⏰ |
| **Sin Stock** | 📦 Alerta: Lote Sin Stock | SIN STOCK | 📦 |

---

## 🎨 Diseño del Email

El email incluye:

### 1. **Encabezado con Badge de Urgencia**
- Color dinámico según nivel de urgencia
- Badge con el nivel (CRÍTICO, URGENTE, etc.)
- Icono representativo

### 2. **Detalles del Lote**
- Número de lote
- Nombre del producto
- Código del producto
- Cantidad restante (con código de colores)
- Fecha de vencimiento (resaltada)
- Fecha de fabricación
- Estado actual

### 3. **Sección de Acción**
- Mensaje contextual según urgencia
- Botón para ver el lote en el sistema
- Color del botón según urgencia

### 4. **Códigos de Color por Urgencia**

| Nivel | Color | Código |
|-------|-------|--------|
| Vencido | Rojo oscuro | #8B0000 |
| Crítico (≤15 días) | Carmesí | #DC143C |
| Urgente (≤30 días) | Naranja-rojo | #FF4500 |
| Advertencia (≤60 días) | Naranja | #FFA500 |
| Información (≤90 días) | Azul | #4169E1 |
| Sin Stock | Gris | #696969 |

---

## 🔧 Archivos Implementados

### 1. **BatchExpiryNotification.php**
**Ubicación:** `app/Mail/BatchExpiryNotification.php`

Clase Mailable que genera y envía los emails de notificación.

**Métodos principales:**
```php
- __construct(ProductBatch $batch, string $notificationType, int $daysUntilExpiry, string $businessName)
- envelope(): Envelope           // Define asunto y remitente
- content(): Content              // Define vista y datos
- getSubject(): string            // Genera asunto dinámico
- getUrgencyLevel(): string       // Calcula nivel de urgencia
- getUrgencyColor(): string       // Define color según urgencia
- getActionMessage(): string      // Mensaje de acción recomendada
```

---

### 2. **batch-expiry-notification.blade.php**
**Ubicación:** `resources/views/mail/batch-expiry-notification.blade.php`

Vista HTML del email con diseño responsive y profesional.

**Características:**
- ✅ Diseño responsive (mobile-friendly)
- ✅ Colores dinámicos según urgencia
- ✅ Información completa del lote
- ✅ Botón de acción directo al sistema
- ✅ Estilos inline para compatibilidad con clientes de email

---

### 3. **ExpiryNotificationService.php** (Actualizado)
**Ubicación:** `app/Services/ExpiryNotificationService.php`

Servicio actualizado para enviar emails automáticamente.

**Nuevos métodos:**
```php
private function sendEmailNotification(
    ProductBatch $batch,
    string $type,
    int $daysUntilExpiry
): void
```

**Flujo:**
1. Verifica que el servicio de mail esté configurado
2. Obtiene usuarios del negocio con roles admin/manager/owner
3. Carga relaciones del lote (producto, negocio)
4. Envía email a cada usuario autorizado
5. Usa queue si está habilitado
6. Registra errores sin detener el proceso

---

## ⚙️ Configuración Requerida

### Variables de Entorno (.env)

```bash
# Configuración de Mail (REQUERIDO)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-contraseña-de-aplicacion
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tuempresa.com
MAIL_FROM_NAME="Sistema POS"

# Queue Mail (OPCIONAL - para envío en segundo plano)
QUEUE_MAIL=true

# URL de la aplicación
APP_URL=https://tudominio.com
```

### Configuración de Gmail (Ejemplo)

Para usar Gmail:
1. Habilitar autenticación de 2 factores
2. Generar contraseña de aplicación
3. Usar la contraseña de aplicación en `MAIL_PASSWORD`

### Otros proveedores SMTP

| Proveedor | Host | Puerto |
|-----------|------|--------|
| Gmail | smtp.gmail.com | 587 |
| Outlook | smtp.office365.com | 587 |
| SendGrid | smtp.sendgrid.net | 587 |
| Mailgun | smtp.mailgun.org | 587 |
| Amazon SES | email-smtp.us-east-1.amazonaws.com | 587 |

---

## 🔄 Flujo de Envío de Emails

```
┌─────────────────────────────────────┐
│  Comando Cron se ejecuta diariamente│
│  php artisan batches:check-expiry   │
└──────────────┬──────────────────────┘
               │
               ▼
   ┌───────────────────────────┐
   │ ExpiryNotificationService │
   │  checkExpiringBatches()   │
   └─────────┬─────────────────┘
             │
             ▼
   ┌─────────────────────────────┐
   │ Para cada lote detectado:   │
   └─────────┬───────────────────┘
             │
             ├─► Crea/Actualiza registro en BD
             │
             └─► sendEmailNotification()
                 │
                 ├─► Verifica configuración MAIL
                 │
                 ├─► Obtiene usuarios del negocio
                 │   (admin/manager/owner)
                 │
                 ├─► Carga datos del lote
                 │
                 └─► Para cada usuario:
                     │
                     ├─► Crea BatchExpiryNotification
                     │
                     └─► Envía email
                         (queue o directo)
```

---

## 📊 Ejemplo de Email Enviado

### Asunto:
```
🚨 URGENTE: Lote por Vencer en 12 días
```

### Contenido:
```
┌─────────────────────────────────────┐
│         [ CRÍTICO ]                 │
│   ⏰ Lote Próximo a Vencer          │
│   Mi Farmacia S.A.                  │
└─────────────────────────────────────┘

⚠️ Este lote vencerá en 12 días

📋 Detalles del Lote
─────────────────────────────────────
Número de Lote:       BATCH-2024-001
Producto:             Paracetamol 500mg
Código del Producto:  MED-001
Cantidad Restante:    150 unidades
Fecha de Vencimiento: 15/12/2024
Fecha de Fabricación: 15/06/2023
Estado:               ⚠️ Activo - Próximo a Vencer

┌─────────────────────────────────────┐
│ ACCIÓN INMEDIATA REQUERIDA:         │
│ Venda o use este lote urgentemente  │
│ antes de que venza.                 │
│                                     │
│   [ Ver Lote en el Sistema ]        │
└─────────────────────────────────────┘
```

---

## 🧪 Testing

### Prueba Manual

1. **Configurar mail en .env**
   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=tu-usuario-mailtrap
   MAIL_PASSWORD=tu-contraseña-mailtrap
   ```

2. **Ejecutar comando manualmente**
   ```bash
   php artisan batches:check-expiry
   ```

3. **Verificar en Mailtrap/Gmail**
   - Revisar bandeja de entrada
   - Verificar diseño responsive
   - Comprobar enlaces

### Prueba con Mailtrap

Mailtrap es ideal para testing sin enviar emails reales:
- URL: https://mailtrap.io
- Crea cuenta gratuita
- Usa credenciales SMTP de Mailtrap
- Revisa emails en su inbox virtual

---

## 🔍 Logs y Debugging

Los errores de envío se registran en:
```
storage/logs/laravel.log
```

Ejemplo de log de error:
```
[2024-12-02 10:30:00] local.ERROR: Failed to send batch expiry notification email 
{
    "user_id": 5,
    "batch_id": 123,
    "error": "Connection timeout"
}
```

---

## ⚡ Optimización con Queue

### Habilitar Queue

En `.env`:
```bash
QUEUE_MAIL=true
QUEUE_CONNECTION=database
```

### Ejecutar Queue Worker

```bash
php artisan queue:work
```

### Ventajas del Queue
- ✅ Respuesta más rápida del comando
- ✅ No bloquea procesos
- ✅ Reintentos automáticos si falla
- ✅ Mejor para múltiples usuarios

---

## 📝 Personalización

### Cambiar Diseño del Email

Edita: `resources/views/mail/batch-expiry-notification.blade.php`

### Cambiar Asuntos

Edita método `getSubject()` en: `app/Mail/BatchExpiryNotification.php`

### Cambiar Destinatarios

Modifica la query en `sendEmailNotification()`:
```php
$users = User::where('business_id', $batch->business_id)
    ->whereIn('role', ['admin', 'manager', 'owner', 'custom_role'])
    ->whereNotNull('email')
    ->get();
```

### Agregar CC o BCC

En `BatchExpiryNotification.php`:
```php
public function envelope(): Envelope
{
    return new Envelope(
        from: new Address($fromAddress, $fromName),
        subject: $subject,
        cc: [new Address('manager@empresa.com')],
        bcc: [new Address('audit@empresa.com')],
    );
}
```

---

## ❌ Troubleshooting

### Email no se envía

1. **Verificar configuración**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Probar conexión SMTP**
   ```bash
   php artisan tinker
   Mail::raw('Test', function($message) {
       $message->to('test@example.com')->subject('Test');
   });
   ```

3. **Revisar logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Email va a spam

- Usa dominio verificado
- Configura SPF, DKIM, DMARC
- Evita palabras spam en asunto
- Usa servicio SMTP profesional (SendGrid, Mailgun)

### Emails lentos

- Habilita `QUEUE_MAIL=true`
- Usa servicio SMTP rápido
- Limita número de destinatarios por lote

---

## 🎯 Beneficios

1. **Notificación proactiva:** Los usuarios reciben alertas sin necesidad de entrar al sistema
2. **Múltiples canales:** Notificaciones en sistema + email
3. **Profesional:** Emails con diseño corporativo
4. **Accionable:** Enlaces directos al sistema
5. **Escalable:** Funciona con múltiples negocios
6. **Rastreable:** Logs de todos los envíos

---

## 🔐 Seguridad

- ✅ Solo usuarios autorizados reciben emails
- ✅ Emails filtrados por business_id
- ✅ Contraseñas mail nunca en código
- ✅ Validación de configuración antes de enviar
- ✅ Manejo de errores sin exponer datos

---

## 📚 Referencias

- Laravel Mail: https://laravel.com/docs/10.x/mail
- Laravel Queue: https://laravel.com/docs/10.x/queues
- Mailtrap: https://mailtrap.io
- Email HTML Best Practices: https://www.campaignmonitor.com/dev-resources/guides/

---

**Implementado por:** GitHub Copilot  
**Fecha:** 2 de diciembre de 2025  
**Versión:** 1.0
