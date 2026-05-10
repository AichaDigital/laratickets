# Changelog

All notable changes to `laratickets` will be documented in this file.

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

