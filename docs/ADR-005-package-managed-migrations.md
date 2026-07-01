# ADR-005: laratickets — migraciones package-managed (sin publicación)

> **Status**: Accepted — implementado en v1.0.1 (AID-290)
> **Date**: 2026-07-01
> **Supersedes**: nada — corrige el camino de instalación roto heredado de v0.x
> **Bump**: patch v1.0.0 → v1.0.1 — corrección de un camino roto, no retirada de feature funcional
> **Enfoque**: B (solo auto-load) — A (paridad larabill) descartado
> **Origen**: AID-290 (hijo de la auditoría AID-288 del patrón `.php`/`.stub` en el umbrella)
> **Relacionados**: ADR-001 (UUID-first), ADR-004 (contrato v1.0), STD-001; larabill ADR-006/007 (contexto contrastivo)

## Contexto

El `InstallCommand` de laratickets replicaba el patrón de publicación ordenada
de larabill (`$migrationOrder` + `publishMigrationsInOrder()`), pero **roto** en
tres frentes (auditoría AID-288):

1. **`$migrationOrder` incompleto** — 8 entradas para 13 migraciones `.php`. El
   camino publicado entregaba **8 de las 10 tablas**: faltaban
   `ticket_attachments` y `ticket_messages`, más los 3 alters
   (`mailbox_email`, `head_user_id`, `requester_id` nullable). Un esquema
   parcial e inservible.
2. **Doble camino de migración.** El `ServiceProvider` **ya auto-carga** las 13
   migraciones vía `loadMigrationsFrom()` (`LaraticketsServiceProvider:22`),
   mientras el comando **además** publicaba copias con timestamps reescritos. No
   es solo incompleto: es un camino que puede producir **doble ejecución** y
   fallar por `table already exists`.
3. **0 stubs dedicados.** `publishMigrationsInOrder()` dependía del fallback glob
   sobre los `.php` — sin `.php.stub` byte-idénticos, sin `bin/sync-migration-stubs`,
   sin `MigrationOrderConsistencyTest`, sin test del camino de instalación.

AID-290 planteó la disyuntiva de arranque **A vs B**:

- **A) Paridad con larabill:** portar el aparato ADR-007 completo (stubs
  byte-idénticos, guard `production`, `$migrationOrder` completo, `sync-stubs`,
  tests anti-drift + MySQL).
- **B) Solo auto-load:** eliminar `publishMigrationsInOrder()` + `$migrationOrder`
  y quedarse con `loadMigrationsFrom()` + el `InstallCommand` como UX.

## Decisión

**Enfoque B.** laratickets **es dueño de su propio schema y lo carga con
`loadMigrationsFrom()`**. No publica migraciones al consumidor.

Este es un contrato válido para **cualquier** app Laravel — no ata a nadie al
umbrella de AichaDigital. La razón para B no es "es un paquete del umbrella",
es que el paquete gestiona su esquema de extremo a extremo y no hay motivo para
que cada consumidor posea/copie las migraciones.

### Razón de diseño (independiente del recuento de consumidores)

> El camino oficial de instalación estaba roto porque **mezclaba publicación
> parcial con migraciones auto-cargadas** — dos orígenes de schema en conflicto,
> uno de ellos incompleto. La corrección no es *arreglar* la publicación, sino
> **retirarla** y dejar un **único origen de schema**: las migraciones del
> paquete cargadas por `loadMigrationsFrom()`.

Esta es la razón que decide B, y vale con independencia de cuántas apps usen
`laratickets:install` hoy. Aunque hubiera consumidores del camino publicado, la
respuesta seguiría siendo retirarlo: es el camino roto y redundante. El recuento
de consumidores mide el **riesgo del bump**, no el diseño.

Concretamente:

- **Se elimina** `$migrationOrder`, `publishMigrationsInOrder()` y el import
  `File` del `InstallCommand`.
- **Se mantiene** `loadMigrationsFrom()` en el `ServiceProvider` **en todos los
  entornos, producción incluida**.
- **Se mantiene** `laratickets:install` como UX oficial: preflight UUID +
  publicar config + `migrate` (que ejecuta las migraciones del paquete) + seed
  opcional. Deja de copiar migraciones.

### Guardarraíl (no reabrir por error)

El `ServiceProvider` **no lleva** guard `! environment('production')` alrededor
de `loadMigrationsFrom()`. El de larabill (`LarabillServiceProvider:60`) existe
**porque larabill publica** y en producción delega en los stubs publicados;
laratickets **no publica**, así que el paquete debe descubrir su schema también
en producción, o el `php artisan migrate` del deploy del consumidor no vería las
tablas.

> **Do not add a production guard around `loadMigrationsFrom()`; deploy
> migrations must discover package migrations.**

Corolario: de los 3 problemas de AID-288, en el mundo B el nº 2 ("falta el guard
production") **no es un bug — es un no-problema**. Solo era un problema bajo el
supuesto de que existe publicación. No se porta el guard de larabill.

### Por qué NO A

A convierte una decisión de producto en **deuda técnica permanente**: dos
fuentes de verdad editables a mano (`.php` + `.php.stub`), orden manual,
`sync-stubs`, tests anti-drift y el riesgo constante de que el `install`
publique algo distinto de lo que el paquete ejecuta. Solo se justifica si un
consumidor real necesita **modificar** las migraciones en su propio repo — hoy
no existe tal consumidor.

## Posicionamiento honesto

laratickets **no es** "agnostic to any Laravel app": STD-001 exige `users.id`
como UUID v7 `char(36)`. Es un **paquete opinionated, UUID-first**, y debe
anunciarse como tal:

> *general-purpose Laravel ticketing package for UUID-first applications.*

## Consecuencias — contrato documentado

En README/docs se fija explícitamente:

- **Laratickets manages its own database migrations.**
- **Applications should not publish or edit package migrations.**
- **User foreign keys require UUID-compatible (`char(36)` UUID v7) user IDs.**
- **If you need custom schema ownership, fork or request an extension point.**

## Compatibilidad y upgrade (v1.0.0 → v1.0.1)

Retirar la publicación **corrige un camino roto** (doble origen + esquema
parcial), no retira una feature funcional → patch limpio. **No** toca el contrato
congelado del ADR-004 (que sella operaciones de dominio, no el mecanismo de
instalación).

El recuento de consumidores solo acota el **riesgo** de este bump (no es la razón
de B): v1.0.0 se tagueó el 2026-06-27, `aicha` (ADR-004) es el primer consumidor
**futuro** y la migración de clientes aún no ha ocurrido — de modo que, en la
práctica, no hay instalaciones de v1.0.0 que la retirada pueda afectar.

**Upgrade note (seguro barato):** quien haya corrido `laratickets:install` en
v1.0.0 y le quedaran migraciones copiadas en `database/migrations` debe
**borrar esas copias antes de migrar** — ahora son package-managed y se
auto-cargan; conservarlas provocaría doble ejecución (`table already exists`).

## Criterios de reapertura (cuándo reconsiderar A)

1. Aparece un consumidor real que necesita **modificar/extender** las
   migraciones del paquete en su propio repo. Entonces la publicación de stubs
   se implementa como **feature deliberada** (su propio ADR), no como parche de
   AID-290.
2. laratickets deja de ser dueño único del schema (co-ownership con la app).

Hasta entonces, B es la decisión.
