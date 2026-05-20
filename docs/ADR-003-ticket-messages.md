# ADR-003: Ticket messages (comments/replies)

> **Status**: Accepted (diseño) — implementación pendiente
> **Date**: 2026-05-20
> **Supersedes**: nada (feature aditiva)
> **Bump**: minor v0.4.0 → v0.5.0 — schema-additive; **source-breaking** para implementadores directos del contract (ver § Versionado)
> **Revisión adversarial**: Codex (gpt-5-codex) 2026-05-20 — 5 concerns incorporados
> **Documento fuente**: `larafactu/clientes` → `docs/specs/2026-05-20-laratickets-v0.5.0-messages.md` (el consumer priorizó la feature vía los triggers de su ADR-011)

## Contexto

El paquete `laratickets` (v0.4.0) modela el ciclo de vida completo de un
ticket — creación, asignación, escalación, estados, evaluación, agent
rating, adjuntos — pero **no tiene concepto de conversación**. El loop
cliente↔staff es implícito: el staff cambia el estado o sube un adjunto y
el consumer dispara un email. No hay diálogo asíncrono persistido.

La consola staff del consumer de referencia (`larafactu`, su ADR-011
§ A10 + § A11) cerró la operativa básica: ver ticket, cambiar estado,
gestionar adjuntos, asignar agentes. Con la asignación cerrada existe la
pieza que faltaba para diseñar conversación sin supuestos ambiguos sobre
autoría y responsabilidad: el staff que responde se identifica vía
`Ticket::activeAssignments`.

Este ADR define el modelo de datos, los contratos públicos, el evento y
los no-objetivos de la feature. Una vez `TicketMessage` toca dominio
central (autoría, visibilidad, eventos, contratos), corregir esas
decisiones después es caro — de ahí el diseño cerrado antes de implementar.

## Decisión

Añadir una tabla `ticket_messages` con su modelo, dos enums, un service
dedicado, un evento, y extensión del `TicketAuthorizationContract`.
Conversación **flat cronológica**, mensajes **append-only salvo redacción
administrativa** (ver § Redacción), autoría **user-bound** (consistente con
el resto del paquete), notificación **event-only** (el consumer engancha
listeners).

### Resumen producto

| Aspecto | Decisión |
|---|---|
| Modelo | `TicketMessage` único. NO entidades separadas reply/note. |
| Autoría | User-bound (`author_id` + `author_role`), consistente con `TicketAttachment.uploader_id/uploader_role`. NO polimórfica. NO mensajes de sistema/bot. |
| Visibilidad | Campo `visibility` (`public`\|`internal`) **existe en schema desde v0.5.0**, default `public`. v0.5.0 **solo crea públicos** — `post()` no expone creación `internal`. Lectura ya filtra. |
| Lifecycle | **Append-only** para usuarios. Sin edición, sin borrado. **Excepción**: redacción administrativa irreversible (seguridad/privacidad) — ver § Redacción. |
| Estructura | Flat cronológica. Sin threading. |
| Relación con estado | `post()` **nunca** muta `TicketStatus`. Reply sobre `RESOLVED` deja el ticket `RESOLVED`. Sin auto-reopen, sin config flag. |
| Adjuntos | Sin vínculo mensaje↔adjunto. `TicketAttachment` no gana `message_id`. Coexisten bajo `ticket_id`. |
| Notificaciones | Event-only (`TicketMessagePosted`). El paquete no incluye listener ni toca el `NotificationContract` vestigial. |
| Body | Texto crudo. El paquete NO emite HTML ni clases CSS. El consumer decide rendering. |
| API REST | Implementación v0.5.0: endpoints básicos de timeline y redacción (`GET/POST /tickets/{id}/messages`, `POST /tickets/{id}/messages/{id}/redact`). |
| Bump | Minor v0.4.0 → **v0.5.0**. Schema-additive; source-breaking para implementadores directos del contract — ver § Versionado. |

## Versionado y compatibilidad

> Esta sección existe porque la revisión adversarial (Codex concern #1)
> señaló que llamar a v0.5.0 "no breaking" era incorrecto.

v0.5.0 extiende el `TicketAuthorizationContract` — un `interface` PHP — con
4 métodos nuevos (ver § Contract). Esto es:

- **Schema-additive**: la tabla `ticket_messages` es nueva, ninguna tabla
  existente cambia. Los consumers corren `migrate` y nada se rompe a nivel
  datos.
- **Source-breaking para implementadores directos del contract**: cualquier
  consumer que tenga una clase `implements TicketAuthorizationContract`
  (sin extender `BasicTicketAuthorization`) **dejará de compilar** hasta que
  añada los 4 métodos nuevos. PHP exige que una clase implemente todos los
  métodos del interface.

Decisión: **se acepta el source-break y se documenta honestamente.** No se
introduce un `TicketMessageAuthorizationContract` separado. Razones:

- El paquete está **pre-v1.0**: en SemVer 0.x el minor actúa como frontera
  de compatibilidad. `composer update` no salta de `^0.4` a `0.5.0`
  automáticamente.
- El consumer de referencia (`larafactu`) **extiende
  `BasicTicketAuthorization`**, no implementa el interface directo — no se
  rompe.
- `BasicTicketAuthorization` implementa los 4 métodos nuevos con
  comportamiento default sensato, así que los consumers que extienden el
  Basic heredan todo sin tocar nada.
- Mensajes, adjuntos y lifecycle son políticas sobre el **mismo agregado
  `Ticket`**. Partir la autorización en 2 interfaces sería más "perfecto"
  para compatibilidad pero menos coherente, e inconsistente con cómo
  ADR-002 añadió los métodos de attachments al mismo contract.

**Upgrade note (obligatoria en el CHANGELOG):**

> v0.5.0 añade 4 métodos a `TicketAuthorizationContract`. Si tu app
> **implementa el interface directamente**, debes añadir `canPostMessage`,
> `canViewInternalMessages`, `canViewMessage` y `canRedactMessage`. Si
> **extiendes `BasicTicketAuthorization`** (recomendado), no necesitas
> hacer nada — heredas el comportamiento default.

## Schema

```php
Schema::create('ticket_messages', function (Blueprint $table) {
    MigrationHelper::uuidPrimaryKey($table);

    MigrationHelper::uuidForeignKey($table, 'ticket_id');
    $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

    MigrationHelper::userIdColumn($table, 'author_id');
    $table->string('author_role', 16)->comment('client | staff');
    $table->string('visibility', 16)->default('public')->comment('public | internal');
    $table->text('body');

    // Redacción administrativa (NO edición). body se sustituye por un
    // placeholder estable; estas 3 columnas dejan rastro auditable.
    $table->timestamp('redacted_at')->nullable();
    MigrationHelper::userIdColumn($table, 'redacted_by')->nullable();
    $table->string('redaction_reason', 255)->nullable();

    $table->timestamps();   // append-only: SIN edited_at, SIN deleted_at

    $table->index(['ticket_id', 'visibility', 'created_at']);
    $table->index('author_id');
});
```

Notas de schema:

- `author_role` y `visibility` son `VARCHAR` string-backed — STD AichaDigital:
  NO usar `enum` MySQL.
- El índice compuesto `(ticket_id, visibility, created_at)` **soporta
  físicamente** el camino de la query de timeline (`WHERE ticket_id = ? AND
  visibility = ? ORDER BY created_at`). No sustituye a la regla de
  seguridad — la regla vive en el service y el contract; el índice solo
  hace que el camino seguro sea también el camino rápido. Cubre además el
  prefijo `ticket_id` solo, así que no se necesita un índice simple aparte.
- `id` es UUID v7 (`HasUuid`), time-ordered: sirve como desempate
  cronológico estable en la query de orden.
- `onDelete('cascade')`: los mensajes mueren con el ticket.
- `redacted_by` es nullable y FK a users (`MigrationHelper::userIdColumn`).
  `redacted_at`/`redaction_reason` nullable: un mensaje sin redactar tiene
  los 3 campos en `NULL`.

## Enums

Dos enums nuevos, string-backed, con `label()` (patrón de
`AttachmentUploaderRole`):

```php
enum MessageAuthorRole: string
{
    case CLIENT = 'client';
    case STAFF = 'staff';
}

enum MessageVisibility: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';
}
```

Se acepta la duplicación conceptual menor entre `MessageAuthorRole` y
`AttachmentUploaderRole` (ambos `client|staff`): son dominios distintos que
podrían divergir. NO se introduce un enum genérico compartido.

## Modelo

`TicketMessage`:

- `use HasUuid, HasFactory`.
- `protected array $userColumns = ['author_id', 'redacted_by'];` — declara
  las columnas de usuario, pero **ver la nota crítica abajo sobre la
  relación `author`**.
- `$fillable`: `ticket_id`, `author_id`, `author_role`, `visibility`,
  `body`. **Las columnas de redacción NO van en `$fillable`** — se setean
  exclusivamente vía `TicketMessageService::redact()`.
- `$casts`: `author_role => MessageAuthorRole::class`,
  `visibility => MessageVisibility::class`, `redacted_at => 'datetime'`.
- `ticket(): BelongsTo<Ticket, TicketMessage>` — método explícito.
- **`author(): BelongsTo` — método explícito.** Ver nota crítica.
- `isRedacted(): bool` — helper: `$this->redacted_at !== null`.

> **Nota crítica (Codex concern #3 — premisa falsa corregida).** El borrador
> original del diseño afirmaba que la relación `author` venía "vía
> `HasUserRelation` + `$userColumns`". **Es falso.** El trait
> `HasUserRelation` define métodos de relación FIJOS y nombrados
> (`creator()`, `resolver()`, `user()`, `agent()`...); NO genera relaciones
> dinámicamente a partir del array `$userColumns`. El mismo error ya existe
> latente en `TicketAttachment`, que declara `@property-read Model $uploader`
> sin que exista un método `uploader()`. Para `TicketMessage` se declara un
> método **explícito**:
>
> ```php
> public function author(): BelongsTo
> {
>     return $this->belongsTo(config('laratickets.user.model'), 'author_id');
> }
> ```
>
> No se confía en el trait para una relación que no implementa.

`Ticket` gana:

- `messages(): HasMany<TicketMessage, Ticket>` — todos, orden natural.
- scope `publicMessages()` — `where('visibility', MessageVisibility::PUBLIC)`.

## Service API

`TicketMessageService` (patrón de `AttachmentService`):

```php
namespace AichaDigital\Laratickets\Services;

class TicketMessageService
{
    /**
     * Postea un mensaje público en el ticket.
     *
     * v0.5.0: la visibilidad NO es parámetro — el service crea SIEMPRE
     * MessageVisibility::PUBLIC explícitamente (no se confía en el default
     * de la columna como comportamiento principal). Cuando una versión
     * futura habilite internal notes, se añade el parámetro.
     *
     * El $role lo pasa el caller pero la autorización LO VALIDA: el
     * service llama canPostMessage($author, $ticket, $role). Un caller
     * que intente postear con un rol que no le corresponde recibe
     * RuntimeException.
     */
    public function post(
        Ticket $ticket,
        $author,
        string $body,
        MessageAuthorRole $role,
    ): TicketMessage;

    /**
     * Lista los mensajes del ticket visibles para $viewer.
     *
     * El filtro de visibilidad se resuelve ANTES de la query vía
     * canViewInternalMessages($viewer, $ticket):
     *   - true  → todos los mensajes
     *   - false → WHERE visibility = public
     *
     * Orden: created_at ASC, id ASC (desempate cronológico estable).
     */
    public function listFor(Ticket $ticket, $viewer): Collection;

    /**
     * Redacta el body de un mensaje (seguridad/privacidad).
     *
     * NO es edición ni borrado: la fila persiste, la autoría y el
     * timestamp se conservan. body se sustituye por un placeholder
     * estable ('[redacted]'); redacted_at / redacted_by / redaction_reason
     * dejan rastro auditable. Operación irreversible.
     *
     * Auth vía canRedactMessage($redactor, $message). Idempotente: si el
     * mensaje ya está redactado, no hace nada.
     */
    public function redact(TicketMessage $message, $redactor, string $reason): TicketMessage;
}
```

`post()` valida, en orden:

1. `config('laratickets.messages.enabled')` — si false, `RuntimeException`.
2. `body` tras `trim()` no vacío — si vacío, `RuntimeException`.
3. `mb_strlen(body) <= config('laratickets.messages.max_body_length')` — si excede, `RuntimeException`.
4. Autorización vía `canPostMessage($author, $ticket, $role)` — si false, `RuntimeException`.

Tras persistir, `post()` dispara `TicketMessagePosted`. Todas las
excepciones llevan mensaje accionable (patrón del paquete).

`listFor()` resuelve el filtro con `canViewInternalMessages($viewer,
$ticket)` y construye el `WHERE` correspondiente. **El filtro de
visibilidad se aplica en la query, no en post-proceso de la colección** —
la seguridad es un boundary de backend, no de UI.

`redact()` valida `canRedactMessage`, y si el mensaje no está ya redactado,
setea `body = '[redacted]'`, `redacted_at = now()`, `redacted_by`,
`redaction_reason`. **No dispara evento** en v0.5.0 (ver § No-objetivos).

## Contract (autorización)

Se extiende el `TicketAuthorizationContract` existente con **4 métodos**
(consistencia con ADR-002, que añadió los métodos de attachments al mismo
contract). Esto es source-breaking — ver § Versionado.

```php
/**
 * ¿Puede $user postear un mensaje en $ticket con el rol $role?
 *
 * El $role es parte de la decisión: un cliente NO puede postear con
 * MessageAuthorRole::STAFF aunque tenga acceso al ticket. author_role
 * gobierna el routing de notificaciones, así que la autorización debe
 * verlo (Codex concern #2).
 */
public function canPostMessage($user, Ticket $ticket, MessageAuthorRole $role): bool;

/**
 * ¿Puede $user ver los mensajes internos de $ticket?
 *
 * Método a nivel de ticket — query-friendly. listFor() lo usa para
 * decidir el WHERE de visibilidad ANTES de cargar mensajes. NO se puede
 * resolver el filtro de lista con un check per-row.
 */
public function canViewInternalMessages($user, Ticket $ticket): bool;

/**
 * ¿Puede $user ver este mensaje concreto?
 *
 * Autorización puntual (p.ej. validar acceso a un mensaje individual por
 * id). NO es el camino del filtro de listFor() — ese lo dueña
 * canViewInternalMessages(). Reservado sobre todo para uso futuro.
 */
public function canViewMessage($user, TicketMessage $message): bool;

/**
 * ¿Puede $user redactar (neutralizar el body de) este mensaje?
 *
 * Operación administrativa de seguridad/privacidad. En el Basic, solo
 * staff. El consumer refina.
 */
public function canRedactMessage($user, TicketMessage $message): bool;
```

`BasicTicketAuthorization` (implementación default, permissive por diseño):

- **`canPostMessage`**: el ticket NO debe estar en estado terminal duro
  (`CLOSED`, `CANCELLED`). `RESOLVED` está permitido — un cliente puede
  responder a un ticket resuelto sin reabrirlo (el mensaje no muta el
  estado). Reglas por rol:
  - `$role === CLIENT`: `true` solo si `$user` es el creator del ticket.
  - `$role === STAFF`: `true` (el Basic asume staff a cualquier no-creator;
    el consumer refina con su criterio real de staff).
- **`canViewInternalMessages`**: `false` en el Basic (conservador para
  datos internos — el consumer lo sube a `true` para staff). Mientras
  v0.5.0 no cree mensajes `internal`, el valor es inocuo.
- **`canViewMessage`**: si `message.visibility === public` → `true`; si
  `internal` → delega en `canViewInternalMessages($user, $message->ticket)`.
- **`canRedactMessage`**: `false` en el Basic (la redacción es una acción
  sensible — el consumer la habilita explícitamente para su staff/admin).

El consumer refina en su propia implementación del contract: rama por rol —
staff/superadmin postean con rol `staff`, ven internos, pueden redactar;
cliente postea con rol `client` solo en sus tickets no-terminales-duros,
nunca ve internos, nunca redacta.

## Evento

```php
namespace AichaDigital\Laratickets\Events;

/**
 * Disparado tras persistir un TicketMessage (v0.5.0).
 *
 * Extension point para listeners del consumer: notificaciones email,
 * métricas, automatizaciones (p.ej. auto-reopen de un ticket RESOLVED
 * cuando el cliente responde — el paquete NO lo hace, es decisión del
 * consumer).
 *
 * CONTRATO PARA CONSUMERS: los listeners de notificación solo deben
 * actuar cuando $message->visibility === MessageVisibility::PUBLIC.
 * Los mensajes internos (cuando una versión futura los habilite) no
 * generan notificación al cliente.
 */
class TicketMessagePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketMessage $message) {}
}
```

La redacción (`redact()`) **no dispara evento** en v0.5.0 — ver
§ No-objetivos.

## Notificaciones

**Event-only.** El paquete NO incluye listener de notificación y NO toca el
`NotificationContract`.

Contexto: el paquete tiene un `NotificationContract` +
`DefaultNotificationHandler`, pero es **vestigial** — el handler default
solo hace `Log::info`, y el consumer de referencia no lo rebindea: consume
**events + listeners propios**. v0.5.0 es coherente con eso: expone el
evento `TicketMessagePosted` y nada más. El `NotificationContract` queda
como deuda del paquete, fuera del alcance de este ADR.

Política de notificación del **consumer** (la implementa el consumer en su
capa, NO el paquete):

- `author_role = staff` → notificar al **creator** del ticket.
- `author_role = client` → notificar a los **staff con asignación activa**
  (`Ticket::activeAssignments`).
- Solo para `visibility = public`.

> **Fallback obligatorio (Codex concern #4).** Si un ticket NO tiene
> asignación activa y el cliente postea un mensaje, la regla anterior no
> tiene destinatario — el mensaje quedaría sin owner operativo. La capa de
> integración del consumer DEBE definir el fallback: notificar al
> departamento del ticket, o a un buzón de soporte, o a todo el staff. El
> paquete solo deja registrado que el caso "sin asignación activa" no puede
> quedar silencioso.

## Config

```php
// config/laratickets.php
'messages' => [
    'enabled'         => env('LARATICKETS_MESSAGES_ENABLED', true),
    'max_body_length' => env('LARATICKETS_MESSAGES_MAX_BODY', 5000),
],
```

## Redacción (append-only con remediación auditada)

> Esta sección existe porque la revisión adversarial (Codex concern #5)
> señaló que "append-only puro + mitigación manual" deja datos sensibles
> (tokens, datos fiscales pegados por error) permanentemente en BD sin vía
> de remediación.

Los mensajes son **append-only para usuarios**: nadie edita ni borra. La
**única** excepción es la redacción administrativa:

- La redacción **no corrige** una conversación ni borra historial. Sustituye
  el `body` por un placeholder estable (`[redacted]`) y deja rastro
  auditable: `redacted_at`, `redacted_by`, `redaction_reason`.
- Solo staff/admin puede redactar (`canRedactMessage`).
- Un mensaje redactado **sigue apareciendo en el timeline** con su autoría,
  su timestamp y un indicador visual de estado redactado.
- Es **irreversible**: el body original no se conserva.

Describir esto como **"append-only salvo redacción administrativa
irreversible por seguridad/privacidad"** — NO como "mensajes editables".
La diferencia es semántica y deliberada: editar implica corregir
contenido; redactar implica neutralizar contenido sensible preservando la
evidencia de que existió.

## Consecuencias

### Positivas

- El loop cliente↔staff deja de ser implícito (cambios de estado + email) y
  pasa a ser conversación persistida y auditable.
- Backend listo para múltiples consumers con UI distinta (cliente Livewire,
  consola staff, futura API).
- El campo `visibility` desde el día uno evita una migración estructural
  cuando emerjan las internal notes.
- La redacción auditada da una vía de remediación de datos sensibles sin
  romper el carácter append-only.

### Negativas / aceptadas

- Tabla nueva → consumers corren `migrate`. Documentado en CHANGELOG.
- Source-breaking para implementadores directos del contract — ver
  § Versionado. Aceptado: pre-v1.0, sin consumers afectados conocidos.
- Schema dormido parcial: `visibility` existe pero v0.5.0 no crea
  `internal`. Aceptado: es propiedad intrínseca del mensaje, no relación
  entre features.

## No-objetivos explícitos de v0.5.0

- **Internal notes (creación)**: el campo `visibility` existe y
  `listFor`/`canViewInternalMessages`/`canViewMessage` lo contemplan, pero
  `post()` no expone creación `internal`. Una versión posterior añade el
  parámetro + la policy de creación. Razón de incluir el campo ahora:
  `visibility` es propiedad intrínseca del mensaje y afecta la seguridad
  desde el diseño inicial; añadirla después tocaría schema, factories,
  eventos, contratos, resources y queries.
- **Evento `MessageRedacted`**: la redacción no dispara evento en v0.5.0.
  Añadirlo ampliaría superficie sin consumer que lo necesite. Si emerge la
  necesidad de auditar redacciones vía listener, ADR posterior lo añade.
- **Adjuntos por mensaje**: `TicketAttachment` no gana `message_id`. A
  diferencia de `visibility` (propiedad intrínseca), el vínculo
  mensaje↔adjunto es una relación entre dos features ya separadas; añadirla
  sin uso real deja schema dormido. Futuro ADR aditivo si emerge el caso.
- **Edición de mensajes**: no existe. Append-only. Corrección = mensaje
  nuevo. La redacción (§ Redacción) NO es edición.
- **Auto-reopen / cambio de estado**: `post()` no muta `TicketStatus`. Sin
  config flag. Un consumer que quiera auto-reopen lo implementa con un
  listener propio sobre `TicketMessagePosted`.
- **Threading**: conversación flat cronológica. Sin `parent_message_id`.
- **API REST**: implementado en esta fase con endpoints de timeline/listado
  y redacción. Cualquier API adicional (filtrado por autor, paginación avanzada,
  soft-delete de mensajes) queda fuera del alcance de v0.5.0 y para eso
  requeriría ADR posterior.
- **Markdown / HTML rendering**: `body` se persiste como texto crudo. El
  paquete no parsea ni emite HTML. El consumer decide el render.
- **Mensajes de sistema / bot**: `author_role` es siempre `client|staff`.
  No hay autor "sistema".
- **Método nuevo en `NotificationContract`**: no se toca. Event-only.
- **`TicketMessageAuthorizationContract` separado**: descartado. Los
  métodos van al `TicketAuthorizationContract` existente — ver § Versionado.

## Criterios de reapertura

Esta decisión se revisa si:

1. Emerge demanda real de **internal notes** operativas → versión que
   habilita `post()` con `visibility` + policy de creación.
2. Un consumer adjunta archivos como parte semántica de una respuesta y la
   falta de vínculo mensaje↔adjunto duele → ADR aditivo `message_id`.
3. Se necesita **auditar redacciones** vía listener → ADR que añade el
   evento `MessageRedacted`.
4. El volumen de un ticket hace la conversación flat ilegible y se pide
   **threading** → ADR de rediseño estructural.

## Alcance de implementación de v0.5.0 (este ADR)

Esta versión del paquete entrega:

- Migración `2026_xx_xx_create_ticket_messages_table.php`.
- Modelo `TicketMessage` (con método `author()` explícito).
- Enums `MessageAuthorRole`, `MessageVisibility`.
- `TicketMessageService` con `post()`, `listFor()`, `redact()`.
- Extensión de `TicketAuthorizationContract` (4 métodos) + impl en
  `BasicTicketAuthorization`.
- Evento `TicketMessagePosted`.
- Config `messages.*`.
- Relación `Ticket::messages()` + scope `publicMessages()`.
- Tests Pest cubriendo: post happy-path, validaciones de `post()`,
  autorización por rol en `canPostMessage`, filtro de visibilidad en
  `listFor()`, redacción + idempotencia, evento disparado.
- CHANGELOG con la upgrade note de § Versionado.

La UI de cliente y de staff vive en el consumer (`larafactu`), NO en el
paquete — ver § Plan de integración del consumer.

## Plan de integración del consumer (informativo — fuera del paquete)

> Orden corregido por la revisión adversarial (Codex concern #4): el
> borrador del diseño ordenaba paquete → UI cliente → UI staff, lo que
> dejaba al cliente postear mensajes antes de que el staff pudiera leerlos.
> El orden correcto cierra primero la superficie staff.

1. **Este ADR — paquete v0.5.0**: lo descrito arriba. Tag v0.5.0 en Packagist.
2. **Consumer, UI staff**: `composer update`, timeline + form de reply
   staff en la consola admin, refinar la implementación del contract
   (rama por rol para los 4 métodos nuevos).
3. **Consumer, UI cliente**: timeline + form de reply cliente, listener de
   notificación + fallback "sin asignación activa" (ver § Notificaciones).

## Historial de revisión del diseño

| Fecha | Revisor | Resultado |
|---|---|---|
| 2026-05-20 | Diseño inicial | Borrador con 4 decisiones de producto cerradas vía clarifying questions (visibility diferida, no auto-reopen, sin vínculo adjunto, append-only). |
| 2026-05-20 | Codex (gpt-5-codex), adversarial | 5 concerns. **#1** versionado: el doc afirmaba "no breaking" siendo source-breaking → § Versionado + honestidad en el header. **#2** `canPostMessage` no veía el `author_role` → firma a 3 params. **#3** premisa falsa: `HasUserRelation` no genera la relación `author` → método `author()` explícito. **#4** secuenciación: cliente posteaba antes de que staff leyera → orden de integración invertido + fallback "sin asignación activa". **#5** append-only sin remediación → § Redacción (3 columnas + `canRedactMessage`). Validó sin objeción: conversación flat, sin vínculo mensaje-adjunto, notificación event-only. |

## Referencias

- `docs/ADR-001-uuid-first.md` — STD-001 UUID-first.
- `docs/ADR-002-ticket-attachments.md` — template de feature aditiva + patrón de extensión del contract.
- `~/development/packages/aichadigital/STANDARDS.md` — STD AichaDigital.
- Documento fuente del diseño: `larafactu/clientes` → `docs/specs/2026-05-20-laratickets-v0.5.0-messages.md`.
