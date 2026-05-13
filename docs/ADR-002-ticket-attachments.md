# ADR-002: Ticket attachments (archivos adjuntos)

> **Status**: Accepted
> **Date**: 2026-05-13
> **Supersedes**: nada (feature aditiva)
> **Bump**: minor v0.3.0 → v0.4.0

## Contexto

El paquete `laratickets` v0.3.0 expone backend completo de tickets (lifecycle, escalación, evaluación, agent rating) pero **no soporta archivos adjuntos**. Sin attachments, casos de uso reales (screenshots de errores, logs, PDFs de facturas) requieren que el cliente describa con texto + URL externa o que abra varios tickets.

Análisis previo en el consumer `larafactu/clientes` (`docs/analysis/2026-05-12-laratickets-followup.md`) confirmó attachments como feature #4 del backlog cliente, requiriendo extensión del paquete.

## Decisión

Añadir una tabla `ticket_attachments` con su modelo, service dedicado, eventos, y extensión del `TicketAuthorizationContract`. Implementación opinada para los 2 roles (cliente / staff) pero con autorización rebindable y storage disk configurable.

### Resumen producto

| Aspecto | Decisión |
|---|---|
| Quién puede subir | Cliente (creator) si ticket en `NEW` / `ASSIGNED` / `IN_PROGRESS`. Staff (resto de usuarios autorizados por contract) sin restricción de estado. |
| Quién puede descargar | Creator del ticket + cualquier staff autorizado. |
| Quién puede borrar | El propio uploader (cliente borra sus subidas, staff borra las suyas). Purga global queda como TODO consumer-level. |
| Tipos permitidos | Configurable. Defaults restrictivos: pdf, png, jpg/jpeg, txt, log. |
| Tamaños | Configurable. Defaults: 5 MB / file, 25 MB total / ticket. |
| Storage | Configurable via disk name. Default: `local`. |
| Path en disk | Subdirectorio `ticket-attachments/` (configurable). |
| Persistencia | Mientras viva el ticket (cascade on delete). Purga programada queda como TODO consumer-side. |

### Schema

```php
Schema::create('ticket_attachments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('ticket_id');
    MigrationHelper::userIdColumn($table, 'uploader_id');
    $table->string('uploader_role', 16);  // 'client' | 'staff'
    $table->string('disk', 50);
    $table->string('path', 500);
    $table->string('original_name', 255);
    $table->string('mime_type', 100);
    $table->unsignedInteger('size_bytes');
    $table->timestamps();

    $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
    $table->index('ticket_id');
    $table->index('uploader_id');
});
```

`uploader_role` es VARCHAR string-backed (no enum MySQL — STD del proyecto AichaDigital: NO usar `enum` MySQL).

### Estructura de paths en disk

```
{disk}/ticket-attachments/{ticket_id}/{attachment_uuid}.{ext}
```

- `{ticket_id}` permite borrado en cascada manual del directorio si emerge necesidad.
- `{attachment_uuid}.{ext}` evita colisiones y conserva pista de extensión para servir mime correcto.
- `original_name` se preserva en BD para mostrar al cliente; el filesystem usa UUID.

### Autorización (contract extension)

`TicketAuthorizationContract` gana 3 métodos nuevos:

```php
public function canAttachFile($user, Ticket $ticket): bool;
public function canDownloadFile($user, TicketAttachment $attachment): bool;
public function canDeleteAttachment($user, TicketAttachment $attachment): bool;
```

`BasicTicketAuthorization` (default) implementa:

- **`canAttachFile`**: `true` si `(user === ticket.creator && status in [NEW,ASSIGNED,IN_PROGRESS])` OR `user` es staff (proxy: cualquier usuario que NO sea el creator se considera staff en la implementación basic — se delega a la app real para refinar).
- **`canDownloadFile`**: `true` si `user === ticket.creator` OR `user` es staff.
- **`canDeleteAttachment`**: `true` si `user.id === attachment.uploader_id`.

La app consumidora puede rebindar el contract para introducir policy específica (ej. role-based, level-based).

### Config (`config/laratickets.php`)

```php
'attachments' => [
    'enabled' => env('LARATICKETS_ATTACHMENTS_ENABLED', true),
    'disk' => env('LARATICKETS_ATTACHMENTS_DISK', 'local'),
    'path' => env('LARATICKETS_ATTACHMENTS_PATH', 'ticket-attachments'),
    'max_file_size_kb' => env('LARATICKETS_ATTACHMENTS_MAX_FILE_KB', 5120),
    'max_total_size_kb_per_ticket' => env('LARATICKETS_ATTACHMENTS_MAX_TOTAL_KB', 25600),
    'allowed_mime_types' => [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'text/plain',
    ],
    'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'log'],
],
```

Apps pueden ampliar los arrays para añadir tipos (zip, doc/docx, etc.) sin parchear el paquete.

### Service API

```php
namespace AichaDigital\Laratickets\Services;

class AttachmentService {
    public function attach(
        Ticket $ticket,
        $uploader,
        UploadedFile $file,
        AttachmentUploaderRole $role,
    ): TicketAttachment;

    public function delete(TicketAttachment $attachment, $actor): void;

    public function listFor(Ticket $ticket, $viewer): Collection;

    public function totalSizeBytes(Ticket $ticket): int;
}
```

`attach()` valida: enabled, allowed_mime + allowed_extension, max_file_size, max_total_size, auth via `canAttachFile`. Lanza `RuntimeException` con mensaje accionable.

`delete()` borra fila + archivo físico del disk. Auth via `canDeleteAttachment`.

`listFor()` aplica `canDownloadFile` por attachment (security boundary backend, no solo UI).

### Event

```php
AttachmentUploaded { public TicketAttachment $attachment; }
```

Extensión point para notificaciones, antivirus scan, S3 lifecycle, etc.

## Consecuencias

### Positivas

- Casos de uso reales (screenshot, log, PDF) soportados sin abrir tickets paralelos.
- Backend listo para múltiples consumers con UI distinta (cliente Livewire, futura admin, API mobile…).
- Contract extension permite policy refinada per-app sin parchear el paquete.
- Disk configurable: local en demo, S3 en producción, sin cambios de código.

### Negativas / aceptadas

- Tabla nueva → consumers deben correr `migrate`. Documentado en CHANGELOG.
- Sin antivirus scan default. Mitigado: `AttachmentUploaded` event permite engancharlo si el operador quiere (ClamAV, etc.).
- Sin purga programada. Storage crece sin tope hasta que el consumer implemente retention. Documentado como TODO consumer-side.

## No-objetivos explícitos

- **Versionado de attachments** (revisión de un mismo archivo): out of scope. Si emerge, ADR aparte.
- **Embedded preview** (PDF/image viewer in-app): el consumer decide UI. Backend solo sirve el archivo.
- **Antivirus / malware scan**: punto de extensión via event, no se incluye implementación por defecto.
- **Compresión / dedupe** de archivos idénticos: out of scope. Si emerge, ADR aparte.
- **Retention / purga programada**: out of scope del paquete. Consumer-level decision.

## Criterios de reapertura

Esta decisión se revisa si:

1. Emerge un consumer real con requirement de versionado / preview embed / antivirus → ADR específica.
2. El volumen de archivos crece >100GB y la falta de dedupe duele → ADR de optimización.
3. GDPR / regulación impone retention obligatoria → mover responsabilidad de purga al paquete vía command artisan publicable.

## Referencias

- Análisis previo: `~/SitesLR12/clientes/docs/analysis/2026-05-12-laratickets-followup.md` § Feature 4
- STD-001 UUID-first: `~/development/packages/aichadigital/STANDARDS.md`
- ADR-001 paquete: `docs/ADR-001-uuid-first.md`
