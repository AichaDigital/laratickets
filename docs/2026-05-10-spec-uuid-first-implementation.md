# SPEC: Implementación UUID-first en laratickets

> **Tipo:** Implementation specification
> **Fecha:** 2026-05-10
> **Status:** Pending implementation in dedicated session
> **Sesión recomendada:** sesión propia tras completar larabill v0.8.0 (que es la referencia probada)
> **Estimación:** 1.5 - 2.5 horas de trabajo continuo

## 0. Lectura obligatoria antes de empezar (en orden)

1. **Umbrella standard:** `~/development/packages/aichadigital/STANDARDS.md` STD-001 — el porqué a nivel ecosistema.
2. **ADR canonical (en larabill, referencia):** `larabill/docs/ADR-006-uuid-first-no-agnostic.md` — la decisión arquitectónica completa.
3. **ADR local del paquete:** `laratickets/docs/ADR-001-uuid-first.md` — materialización en laratickets, incluye la cuestión específica de "actor semantics".
4. **SPEC larabill (referencia probada):** `larabill/docs/2026-05-10-spec-uuid-first-implementation.md` — patrón de ejecución validado. Esta SPEC sigue el mismo esquema simplificado.
5. **Setup guide consumidores compartida:** `larabill/docs/setup-uuid.md` — los consumidores de laratickets también necesitan `users.id` UUID, esta guía sirve.
6. **CLAUDE.md raíz umbrella:** `~/development/packages/aichadigital/CLAUDE.md` — reglas transversales del ecosistema.
7. **README + CHANGELOG del paquete:** `laratickets/README.md`, `laratickets/CHANGELOG.md` — para entender estado actual.

## 1. Contexto del paquete

`laratickets` es el sistema de tickets de soporte del ecosistema AichaDigital. Pre-v1, gestionado en `dev-main`. Único consumidor real conocido: `~/SitesLR12/clientes`, que usa UUID v7. Tiene su propia copia (byte-similar pero independiente) de `MigrationHelper` heredada del patrón agnóstico que larabill abandonó en ADR-006.

Esta SPEC materializa la decisión codificada en `laratickets/docs/ADR-001-uuid-first.md`.

**Diferencia clave frente a larabill:** laratickets tiene 9 columnas con semántica de "actor" (created_by, resolved_by, requester_id, approver_id, evaluator_id, agent_id, rater_id, assessor_id, user_id de assignments). El ADR local nombra explícitamente que la puerta al polimorfismo selectivo futuro (cuando aparezca `BotAgent`/`ApiClient`/`SystemActor` real) queda abierta — pero **esta SPEC no lo introduce**. Hoy todas son monomórficas a User.

## 2. Inspección frozen (datos verificados 2026-05-10, no re-investigar)

### 2.1 Surface en `src/`

| Archivo | Estado actual | Acción |
|---|---|---|
| `src/Support/MigrationHelper.php` | Copia byte-similar a la de larabill pre-v0.8.0. Misma estructura: `userIdColumn`, `getUserIdType`, etc. | Mantener `userIdColumn` simplificado a UUID. Borrar métodos de detección. **Verificar si tiene `agnosticIdColumn` (larabill sí lo tiene). Si no, no hace falta añadirlo.** |
| `src/Commands/InstallCommand.php` | Existe. Sin lógica de detección user_id_type (verificado por grep — 0 refs). | Añadir preflight `verifyUsersTableUuid()` antes de migraciones. Patrón: copiar el de `LarabillInstallCommand` post-Sprint1. |
| `src/LaraticketsServiceProvider.php` | Registra `InstallCommand::class` vía `->hasCommand(InstallCommand::class)`. Sin DetectUserIdTypeCommand. | No requiere cambios. |
| `config/laratickets.php` | Tiene `'user' => ['id_type' => env('LARATICKETS_USER_ID_TYPE', 'auto'), 'model' => env('LARATICKETS_USER_MODEL', config('auth.providers.users.model'))]` | Borrar key `id_type` y su ENV. Mantener `model`. |

### 2.2 Las 9 columnas FK a User (verificadas)

Todas usan `MigrationHelper::userIdColumn()` ya, pasarán a UUID automáticamente al simplificar el helper:

| Tabla | Columna | Migration file |
|---|---|---|
| `tickets` | `created_by` | `2024_11_01_000003_create_tickets_table.php` |
| `tickets` | `resolved_by` (nullable) | mismo |
| `ticket_assignments` | `user_id` | `2024_11_01_000004_create_ticket_assignments_table.php` |
| `escalation_requests` | `requester_id` | `2024_11_01_000005_create_escalation_requests_table.php` |
| `escalation_requests` | `approver_id` (nullable) | mismo |
| `ticket_evaluations` | `evaluator_id` | `2024_11_01_000006_create_ticket_evaluations_table.php` |
| `agent_ratings` | `agent_id` | `2024_11_01_000007_create_agent_ratings_table.php` |
| `agent_ratings` | `rater_id` | mismo |
| `risk_assessments` | `assessor_id` | `2024_11_01_000008_create_risk_assessments_table.php` |

Ninguna requiere edición de migration directa — el helper hace el trabajo.

### 2.3 Surface en `tests/`

**Mucho menor que larabill.** Inventario:

| Archivo | Estado | Acción |
|---|---|---|
| `tests/TestCase.php` | NO carga ninguna tabla `users`. Solo carga las 8 tablas del paquete. Sin config `user_id_type`. | Verificar si necesita constantes UUID (sí, para tests con user_id). No requiere migración de users table porque no existe en el fixture. |
| `tests/Pest.php` | Setup Pest estándar | No requiere cambios salvo añadir constantes UUID si se prefiere allí. |
| `tests/Models/TestUser.php` | **NO EXISTE en laratickets** (laratickets no tiene un TestUser propio — los tests no instancian usuarios) | No requiere acción. |
| `tests/Database/migrations/` | **NO EXISTE en laratickets** | No requiere acción. |

### 2.4 Tests con user_id hardcoded — INVENTARIO COMPLETO

Identificado por grep (verificado 2026-05-10):

| Archivo | Ocurrencias | Patrón actual |
|---|---|---|
| `tests/Unit/Services/TicketServiceTest.php` | 3 | `'user_id' => 1`, `'user_id' => 2` |

**Total: 3 ocurrencias en 1 archivo.** Mucho menor que larabill.

### 2.5 Tests del MigrationHelper a borrar/reducir

Verificar existencia: `tests/Unit/Support/MigrationHelperTest.php` (probable). Si existe, mismas acciones que larabill: borrar tests de métodos eliminados, reducir matrix a UUID.

### 2.6 MySQL Integration

**laratickets NO tiene `tests/Integration/Mysql/`.** Esta SPEC añade la suite, espejando el patrón de larabill v0.7.4+:

| # | Acción |
|---|---|
| 2.6.1 | Crear `tests/Integration/Mysql/MysqlIntegrationTestCase.php` (espejo simplificado del de larabill — extends Orchestra directly, requiere envs `LARATICKETS_TEST_MYSQL_*` o reutiliza `LARABILL_TEST_MYSQL_*` por simetría umbrella, define `bootstrap()` que crea `users` UUID y migra tablas del paquete) |
| 2.6.2 | Crear `tests/Integration/Mysql/FreshInstallTest.php` que verifica que las 9 columnas FK son `char(36)` tras `larabill:install` (o equivalente en laratickets — verificar nombre real del comando) |
| 2.6.3 | Bind en `tests/Pest.php` para que el TestCase aplique solo a `Integration/Mysql/` |
| 2.6.4 | Añadir job `mysql-integration` al CI (copia del de larabill, ajustando nombres de envs si difieren) |

### 2.7 Docs a actualizar

| Archivo | Sección/línea | Acción |
|---|---|---|
| `README.md` línea 29 | "Multi-ID Support: Works with integer, UUID (v4/v7), UUID binary, and ULID primary keys" | Reescribir: "UUID-first: requires `users.id` UUID v7 char(36). See aichadigital/larabill setup-uuid.md guide for consumer setup." |
| `README.md` líneas 66-67 | `'id_type' => env('LARATICKETS_USER_ID_TYPE', 'auto')` en bloque de config | Borrar línea `id_type`. Mantener `model`. |
| `docs/Laratickets.md` | Buscar y limpiar menciones a agnostic / user_id_type | Verificar contenido, alinear con UUID-first |
| `CHANGELOG.md` | Entry nueva | `[0.X.0] - YYYY-MM-DD` marcando breaking change. La versión actual hay que ver qué tag tiene. |

## 3. Decisión arquitectónica frozen

Idéntica a larabill: **Camino B (limpio)**. Los 3 hardcoded `'user_id' => 1` migran a UUIDs reales. No queda fixture int.

### 3.1 Constantes UUID determinísticas

Patrón espejo de larabill, en `tests/TestCase.php`:

```php
class TestCase extends Orchestra
{
    public const USER_UUID_1 = '0194a000-0000-7000-8000-000000000001';
    public const USER_UUID_2 = '0194a000-0000-7000-8000-000000000002';
    // (USER_UUID_3 si algún test lo necesita; aquí 2 bastan según inventario actual)
}
```

Decisión deliberada: usar el **mismo prefix UUID** que larabill (`0194a000-...`) para que en `clientes` (que consume ambos paquetes) los UUIDs de test sean reconocibles como pertenecientes al ecosistema AichaDigital test fixtures.

### 3.2 Polimorfismo descartado en esta SPEC, pero puerta abierta

ADR-001 deja explícito que columnas como `created_by`, `agent_id`, `assessor_id` tienen semántica de "actor" y podrían convertirse a `nullableMorphs` en el futuro **por columna individual**, no preventivamente. Esta SPEC no introduce nada al respecto. Si durante la implementación aparece evidencia de que una de las 9 columnas debería ser polimórfica ya, **parar y reportar** (criterio sección 6).

## 4. Plan de ejecución en 5 fases

### Fase 1 — Código del paquete

| # | Acción | Archivo |
|---|---|---|
| 1.1 | Verificar (grep `Detect`) que NO existe `DetectUserIdTypeCommand` (laratickets parece no tenerlo). Si existiera, borrar como en larabill. | `src/Console/` o `src/Commands/` |
| 1.2 | Simplificar `MigrationHelper`: `userIdColumn` usa `$table->uuid($column)`. Borrar `getUserIdType`, `detectUserIdType`, `getIdTypeDescription`, `isSupportedIdType`. Verificar si tiene `agnosticIdColumn` (probable que no — no se vio en migrations); si no, no añadirlo. | `src/Support/MigrationHelper.php` |
| 1.3 | Añadir preflight `verifyUsersTableUuid()` en `InstallCommand`. Mensaje de error apunta a `larabill/docs/setup-uuid.md` (compartida). Aborta install si `users.id` no es char(36). | `src/Commands/InstallCommand.php` |
| 1.4 | Borrar key `'user' => ['id_type' => ...]` (mantener `'model'`). Borrar bloque comentario asociado. | `config/laratickets.php` |

### Fase 2 — Tests (Camino B)

| # | Acción | Archivo |
|---|---|---|
| 2.1 | Migrar 3 ocurrencias `'user_id' => N` a `$this::USER_UUID_X`. | `tests/Unit/Services/TicketServiceTest.php` |
| 2.2 | Definir constantes USER_UUID_1, USER_UUID_2 en `TestCase` (mismas que larabill). | `tests/TestCase.php` |
| 2.3 | Si `tests/Unit/Support/MigrationHelperTest.php` existe, borrar tests de métodos eliminados y reducir matrix a UUID. | (verificar) |
| 2.4 | Crear `tests/Integration/Mysql/MysqlIntegrationTestCase.php` (espejo larabill, simplificado). | nuevo |
| 2.5 | Crear `tests/Integration/Mysql/FreshInstallTest.php` que verifica las 9 columnas FK son `char(36)` tras migrar el paquete sobre `users` UUID. | nuevo |
| 2.6 | Bind `MysqlIntegrationTestCase` a `Integration/Mysql/` en `tests/Pest.php`. | `tests/Pest.php` |

### Fase 3 — Verificación obligatoria

| # | Acción | Bloqueante? |
|---|---|---|
| 3.1 | `composer test` (suite SQLite, ~12 tests) | sí |
| 3.2 | `composer phpstan` si está configurado (verificar `composer.json`) | sí si existe |
| 3.3 | `composer pint` o equivalente | recomendado |
| 3.4 | **MySQL Integration:** ejecutar localmente con envs o validar CI antes de merge | sí (uno de los dos) |
| 3.5 | Añadir job `mysql-integration` al `.github/workflows/` (copia del larabill v0.8.0+) | sí (sin él, paso 3.4 vía CI no ocurre) |

### Fase 4 — Documentación

Aplicar todas las acciones de la sección 2.7.

### Fase 5 — Cierre

| # | Acción |
|---|---|
| 5.1 | CHANGELOG entry breaking change |
| 5.2 | Commit (recomendado serie: `refactor: simplify MigrationHelper to UUID-only`, `test: migrate fixtures + add MySQL Integration suite`, `docs: ADR-001 + README/config refresh`, `chore: bump version + CHANGELOG`) |
| 5.3 | PR / merge tras CI verde + tag de versión |
| 5.4 | Actualizar fila laratickets en `~/development/packages/aichadigital/STANDARDS.md` (working note umbrella, NO commit en repo laratickets): columnas Code migrated, Preflight, MySQL test → ✓. Estado global → "migrated". |

## 5. Para una segunda voz adversarial

### Asunciones cuestionables

1. **Asunción:** copiar el patrón `MysqlIntegrationTestCase` de larabill es lo correcto.
   **Cuestionable:** ¿extraer a un paquete compartido `aichadigital/lara-test-helpers`? Codex evaluó (2026-05-09) y descartó por coupled-release-timing. Esta SPEC sigue ese descarte.
   **Decisión:** copiar byte-similar, asumir mantenimiento manual.

2. **Asunción:** `LARATICKETS_TEST_MYSQL_*` envs separadas de `LARABILL_TEST_MYSQL_*`.
   **Cuestionable:** ¿reutilizar las mismas envs (LARABILL_TEST_MYSQL_*) por simetría umbrella?
   **Sugerencia:** evaluar al implementar. Si CI las define como matriz compartida, reusar es más limpio.

3. **Asunción:** ninguna de las 9 columnas requiere polimorfismo HOY.
   **Cuestionable:** ¿hay evidencia en `clientes` de un caso real que ya empuja por morph en `created_by` o `agent_id`?
   **Verificación:** grep en `clientes` por `BotAgent`, `ApiClient`, `automation` antes de cerrar. Si aparece, parar (criterio 6).

4. **Asunción:** el InstallCommand de laratickets puede recibir un preflight análogo al de larabill.
   **Cuestionable:** ¿tiene la misma estructura de `validatePrerequisites()`? Si difiere mucho, el preflight necesitará adaptación, no copy-paste.
   **Verificación:** leer `InstallCommand.php` antes de empezar Fase 1.

### Alternativas explícitamente descartadas

- Polimorfismo preventivo en las 9 columnas: NO. Por columna individual cuando aparezca caso real.
- Soporte ULID además de UUID: NO. Sin demanda real.
- Mantener config `id_type` con valor fijo `uuid`: NO. La config se retira completamente; el tipo no es configurable.

## 6. Criterio de parada — "algo conceptual rompe"

Detener y reportar si:

1. Lógica de negocio que asume IDs incrementales en cualquiera de las 9 columnas FK (ordering, comparaciones aritméticas).
2. Validation rules `'integer'` sobre user_id en alguna FormRequest del paquete.
3. **Evidencia de uso real polimórfico** en `clientes` (BotAgent, ApiClient, system actor) que invalide la asunción "monomórfica a User pre-v1".
4. Más de 2 tests rompiendo por causas no triviales tras migración de los 3 hardcoded user_ids.
5. El `InstallCommand` actual difiere tanto del de larabill que el preflight no se puede portar — entonces re-evaluar approach (¿implementar from scratch? ¿saltar preflight a una versión posterior?).

## 7. Rollback strategy

Branch `feat/uuid-first-vX.Y.Z`. Catastrophe:

```bash
git checkout main && git branch -D feat/uuid-first-vX.Y.Z
```

`clientes` no se ve afectado mientras la rama no se mergee.

## 8. Estimación honesta

- Fase 1: 30 min
- Fase 2: 45-60 min (3 tests trivial migration + crear MySQL Integration suite desde cero — esto es el grueso)
- Fase 3: 20 min (pocos tests, fácil)
- Fase 4: 15 min (docs, mecánico)
- Fase 5: 5 min

**Total: 1.5 - 2.5 horas continuas.** Significativamente más rápido que larabill por menos surface y por tener larabill como referencia probada.

## 9. Glosario

- **Camino B**: tests con UUIDs reales (decisión congelada, hereda de larabill).
- **"Algo conceptual"**: criterio de parada — sección 6, especialmente punto 3 sobre polimorfismo real.
- **Working note umbrella**: STANDARDS.md, fuera del repo laratickets, no se commitea desde aquí.

## 10. Referencias cruzadas

- ADR canonical: `larabill/docs/ADR-006-uuid-first-no-agnostic.md`
- ADR local: `laratickets/docs/ADR-001-uuid-first.md`
- SPEC referencia probada: `larabill/docs/2026-05-10-spec-uuid-first-implementation.md`
- Setup guide compartida (consumidores): `larabill/docs/setup-uuid.md`
- Standard umbrella: `~/development/packages/aichadigital/STANDARDS.md` STD-001
- Sesión adversarial origen: 2026-05-09, Claude Opus 4.7 + OpenAI Codex
- Sesión que produjo este SPEC: 2026-05-10
- SPEC hermana: `lara-content/docs/2026-05-10-spec-uuid-first-implementation.md`
