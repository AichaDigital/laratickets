# Codex Instructions for Laratickets

## Operating Rules

- Conversation with the user is Spanish by default.
- Code, comments, docblocks, class names, methods, variables, tests, and commit messages are always written in English.
- Before multi-step work, state assumptions, possible interpretations, the simpler approach when it exists, and concrete verification criteria.
- If a material requirement is unclear, stop and ask before editing.
- Make surgical changes only. Do not clean adjacent code, formatting, docs, or configuration unless the current task directly requires it.
- Respect existing style, even when a different style would be preferred.
- Remove only imports, variables, handlers, config keys, or files made unused by the current change.
- Treat unrelated untracked or modified files as user work. Do not revert them.

## Host Environment

- The local host is macOS/Darwin with BSD userland, not GNU/Linux.
- Prefer POSIX-compatible shell commands.
- Do not assume GNU flags for `sed`, `date`, `mktemp`, `readlink`, `xargs`, or `grep`.
- If GNU behavior is required, check for Homebrew-prefixed tools such as `gsed` first.
- Linux/GNU assumptions are acceptable only for commands executed remotely on Linux hosts.

## Project Context

- Package: `aichadigital/laratickets`.
- Role: Laravel support ticket package for the AichaDigital/Larafactu ecosystem.
- Source path: `/Users/abkrim/development/packages/aichadigital/laratickets`.
- Primary staging app: `/Users/abkrim/SitesLR12/larafactu`.
- Current stack from `composer.json`: PHP `^8.3`, Illuminate `^12.0||^13.0`, Pest 4, Orchestra Testbench 10/11, Larastan 3.
- Public package docs: `README.md`, `CONTRIBUTING.md`, `docs/AGENT_CONTEXT.md`, `docs/API.md`, `docs/Laratickets.md`.

## Required Reading

Before non-trivial code changes, read the smallest relevant set:

1. `docs/AGENT_CONTEXT.md` for package overview and conventions.
2. `CONTRIBUTING.md` for migration conventions.
3. `composer.json` for commands and supported versions.
4. The target source and tests for the requested change.

Before UUID/user-FK work, also read:

1. `docs/ADR-001-uuid-first.md`.
2. `docs/2026-05-10-spec-uuid-first-implementation.md`.
3. `src/Support/MigrationHelper.php`.
4. `config/laratickets.php`.
5. The affected migrations and tests.

## Architecture Rules

- Use the package service layer for business operations where possible.
- Respect the ticket escalation flow and auditability requirements.
- Do not bypass authorization/capability contracts when touching request or service behavior.
- Package migrations are timestamped `.php` files and load through `loadMigrationsFrom()` in `LaraticketsServiceProvider`.
- User foreign-key columns currently go through `MigrationHelper::userIdColumn()`.
- Ticket IDs and package-owned foreign keys use UUID string columns through the package helper/traits.
- The accepted direction for user foreign keys is UUID-first. Do not reintroduce public `int|uuid|ulid` agnosticism unless a new ADR explicitly changes that.
- Do not add preventive actor polymorphism. If real evidence appears for `ApiClient`, `BotAgent`, or system actors in user/agent columns, stop and report.

## Verification

Use the narrowest verification that proves the change:

- Full tests: `composer test`.
- Unit tests: `composer test -- --filter=Name`.
- Static analysis: `composer phpstan`.
- Formatting: `composer lint`.
- Project check: `composer check`.

For Laravel app/staging failures, read logs before guessing:

```bash
cd /Users/abkrim/SitesLR12/larafactu
cat storage/logs/laravel.log
```

Do not run destructive commands or application migrations in staging without explicit user approval.
