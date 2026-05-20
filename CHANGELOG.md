# Changelog

All notable changes to `laratickets` will be documented in this file.

## [0.5.1] - 2026-05-20

### Added — Ticket messages API

- Added `GET /api/v1/laratickets/tickets/{id}/messages` to list visible messages for the authenticated viewer.
- Added `POST /api/v1/laratickets/tickets/{id}/messages` to post a message.
- Added `POST /api/v1/laratickets/tickets/{id}/messages/{message_id}/redact` to redact message body.
- Added `StoreTicketMessageRequest`, `RedactTicketMessageRequest`, `TicketMessageResource`, and `TicketMessageController` to keep message concerns in package API surface.

## [0.5.0] - 2026-05-20

### Added — Ticket messages (ADR-003)

- New migration `2026_05_20_000001_create_ticket_messages_table.php`.
- New model `TicketMessage` (`ticket_messages` table) with `author_id`, `author_role`, `visibility`, `body`, redact fields.
- New enums `MessageAuthorRole` (`client` | `staff`) and `MessageVisibility` (`public` | `internal`).
- New service `TicketMessageService` with `post()`, `listFor()`, `redact()`.
- New event `TicketMessagePosted` dispatched after posting.
- `TicketAuthorizationContract` extended with `canPostMessage`, `canViewInternalMessages`, `canViewMessage`, `canRedactMessage`.
- `BasicTicketAuthorization` default implementation for message operations (conservative internal visibility).
- `Ticket` gains `messages()` and `publicMessages()` relations.
- Config `messages.*`: `enabled` and `max_body_length`.
- New test suite `tests/Unit/Services/TicketMessageServiceTest.php` covering happy path, validation, auth gating, visibility filter and redact idempotency.

### Versioning note

- This change adds 4 new methods to `TicketAuthorizationContract` and is source-incompatible with consumers that implement the contract directly without adding them. Consumers extending `BasicTicketAuthorization` keep compatibility.
- Consumers must run migrations after `composer update`.

## [0.4.0] - 2026-05-13

### Added — Ticket attachments (ADR-002)

- New table `ticket_attachments` (UUID PK, ticket FK cascade, uploader UUID + role string-backed).
- New model `TicketAttachment` (HasUuid + HasUserRelation + casts).
- New enum `AttachmentUploaderRole` (`client` | `staff`).
- New service `AttachmentService` (`attach`, `delete`, `listFor`, `totalSizeBytes`).
- New event `AttachmentUploaded` (extension point: email notifications, antivirus scan, S3 lifecycle…).
- `TicketAuthorizationContract` extended with `canAttachFile`, `canDownloadFile`, `canDeleteAttachment`.
- `BasicTicketAuthorization` implements defaults (creator-can-attach gated by status NEW/ASSIGNED/IN_PROGRESS; staff proxy permissive — apps con role system DEBEN rebindar).
- `Ticket::attachments()` relation (HasMany, ordered by created_at).
- Config `attachments.*`: `enabled`, `disk`, `path`, `max_file_size_kb` (default 5120 / 5 MB), `max_total_size_kb_per_ticket` (default 25600 / 25 MB), `allowed_mime_types` (pdf, png, jpg/jpeg, txt), `allowed_extensions` (pdf, png, jpg, jpeg, txt, log).
- Tests Pest (10 unit tests cubriendo happy path + 6 validaciones + auth gating + listFor filtering).

### Migration

- New migration `2026_05_13_000001_create_ticket_attachments_table.php`. Consumers deben correr `php artisan migrate` tras `composer update`.

### No-objetivos explícitos en esta versión

- Sin versionado de attachments (revisión del mismo archivo).
- Sin preview embed / image viewer (UI consumer-side).
- Sin antivirus scan default (extensión via event).
- Sin retention/purga programada (consumer-level decision).

Ver [ADR-002](docs/ADR-002-ticket-attachments.md) para rationale completo.

## [0.3.0] - 2026-05-10

### BREAKING CHANGES

Laratickets is now **UUID-first**. The agnostic `int|uuid|ulid` support promised by previous versions is removed.
The 9 user FK columns (`created_by`, `resolved_by`, `user_id`, `requester_id`, `approver_id`, `evaluator_id`,
`agent_id`, `rater_id`, `assessor_id`) are emitted as `char(36)` exclusively. Consumer apps **must** have
`users.id` as UUID v7 char(36).

See [ADR-001](docs/ADR-001-uuid-first.md) for rationale and [larabill setup-uuid.md](https://github.com/AichaDigital/larabill/blob/main/docs/setup-uuid.md) (shared) for consumer setup.

### Removed

- `config/laratickets.php`: key `user.id_type` and ENV `LARATICKETS_USER_ID_TYPE`.
- `MigrationHelper::getUserIdType()`, `detectUserIdType()`, `getIdTypeDescription()`, `isSupportedIdType()`.

### Added

- `MigrationHelper` simplified to `userIdColumn()` (UUID-only), `uuidPrimaryKey()`, `uuidForeignKey()`.
- `InstallCommand` preflight `verifyUsersTableUuid()`: aborts install if `users.id` is not char(36) UUID. Bypass with `--skip-uuid-check`.
- MySQL Integration test suite (`tests/Integration/Mysql/`): asserts the 9 user FK columns are CHAR(36) on a real MySQL 8 database via `larabill:install` equivalent flow.
- CI job `mysql-integration` running the new suite against MySQL 8 in GitHub Actions.

### Changed

- `tests/Unit/Services/TicketServiceTest.php`: int fixtures `'created_by' => 1`, `'user_id' => N` migrated to deterministic UUID constants `TestCase::USER_UUID_{1,2,3}`.
