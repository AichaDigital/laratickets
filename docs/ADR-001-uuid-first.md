# ADR-001: UUID-first, retirada del agnosticismo de tipo de PK

> **Status**: Accepted
> **Date**: 2026-05-10
> **Supersedes**: La pretensión agnostic `int|uuid|ulid` en `laratickets.user.id_type` (default anterior: `auto`).
> **Canonical rationale**: Ver [`larabill/docs/ADR-006-uuid-first-no-agnostic.md`](https://github.com/AichaDigital/larabill/blob/main/docs/ADR-006-uuid-first-no-agnostic.md). Esta ADR es la materialización en laratickets del estándar AichaDigital UUID-first (STD-001).

## Decisión

`laratickets` adopta **UUID v7 string char(36) como tipo único** para toda FK al `users` de la app consumidora. No se ofrece soporte público para `bigint` ni `ulid`.

## Alcance específico en laratickets

El paquete tiene **9 columnas** que referencian al `users` consumidor, todas vía `MigrationHelper::userIdColumn()`:

| Tabla | Columna | Semántica |
|---|---|---|
| `tickets` | `created_by` | Quien creó el ticket |
| `tickets` | `resolved_by` | Quien lo cerró (nullable) |
| `ticket_assignments` | `user_id` | Agente asignado |
| `escalation_requests` | `requester_id` | Solicitante de escalación |
| `escalation_requests` | `approver_id` | Quien aprueba/deniega (nullable) |
| `ticket_evaluations` | `evaluator_id` | Quien evalúa |
| `agent_ratings` | `agent_id` | Agente calificado |
| `agent_ratings` | `rater_id` | Quien califica |
| `risk_assessments` | `assessor_id` | Quien evalúa riesgo |

Tras esta ADR, **todas estas columnas son `char(36)` UUID**. No se contempla mezcla, ni opt-out por columna, ni configurabilidad por consumidor.

## Cambios concretos

1. **Config:** `laratickets.user.id_type` se elimina. La opción ENV `LARATICKETS_USER_ID_TYPE` deja de leerse.
2. **MigrationHelper:** se simplifica a emisión UUID exclusiva. Ramas `int`, `ulid` y `auto-detect` desaparecen.
3. **Default inmediato (cambio de 1 línea, antes de la simplificación completa):** `laratickets.user.id_type` cambia de `'auto'` a `'uuid'` para mitigar el bug latente de auto-detect cuando `users` no existe al boot.
4. **Tests:** si existen pruebas matrix agnósticas, reducir a UUID.
5. **README:** añadir requisito explícito "Requires `users.id` UUID v7 char(36)". Enlazar a `larabill/docs/setup-uuid.md`.
6. **CHANGELOG:** entrada marcando breaking change respecto a la promesa pública anterior.

## Cuestión específica abierta — semántica de "actor"

Codex (revisión adversarial 2026-05-09) señaló correctamente que columnas como `created_by`, `resolved_by`, `requester_id`, `evaluator_id`, `assessor_id`, `agent_id`, `rater_id` tienen semántica de **actor**, no estrictamente de **usuario humano**. En el futuro podría aparecer demanda real de:

- `created_by` apuntando a un `ApiClient` o `AutomationActor`
- `resolved_by` apuntando a un sistema (auto-cierre por SLA)
- `agent_id` apuntando a un `BotAgent`

Esa evolución, **si ocurre**, se introducirá **por columna individual** convertida a polimórfica (`nullableMorphs`), con su propia ADR. No se hace preventivamente. Pre-v1, todas las columnas son monomórficas a User.

Esto significa que la decisión UUID-first **no cierra** la puerta al polimorfismo selectivo futuro — solo cierra la puerta al agnosticismo de tipo de PK.

## No-objetivos

- Soporte bigint: fuera de scope.
- Soporte ULID: fuera de scope.
- Migración entre tipos de PK post-instalación: no soportada.
- Polimorfismo preventivo en todas las columnas actor: no se introduce.

## Criterios de reapertura

Idénticos a los de larabill ADR-006:

1. Cliente concreto con presupuesto que requiera bigint o ULID.
2. Caso de uso justifica el coste permanente.
3. Reapertura como major nueva con ADR propio que supersede esta.

## Referencias

- [larabill ADR-006](https://github.com/AichaDigital/larabill/blob/main/docs/ADR-006-uuid-first-no-agnostic.md) — rationale canonical.
- `~/development/packages/aichadigital/STANDARDS.md` STD-001 — estándar umbrella.
- [larabill setup-uuid.md](https://github.com/AichaDigital/larabill/blob/main/docs/setup-uuid.md) — guía de setup compartida para apps consumidoras.
