# Establish the Migration Debt inventory and regression guards

Status: completed

Portal impact: none

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Create the prefactoring safety net that makes subsequent migrations measurable. Produce a reproducible read-only inventory of supported runtime paths and enforce that no new frozen-zone, ownership-boundary, Laravel 13, Inertia v3, API, frontend-filter, route-helper, or strict-types debt can be introduced while existing debt is retired.

## Acceptance criteria

- [x] Inventory covers HTTP, API, queue, scheduler, commands, imports/exports, reports, webhooks, broadcasts, MCP tools, and both portal contracts without scanning generated/vendor output as application debt.
- [x] Every allowlist entry records an owner, reason, canonical replacement, and retirement condition.
- [x] Architecture checks reject new frozen-zone code, concrete cross-context imports, and growth of approved debt baselines.
- [x] Counts are reproducible from a clean checkout and are reported by owning context and runtime surface.
- [x] The guards pass after the Billing Exceptions baseline is restored.

## Blocked by

- [Restore the Finance Billing Exceptions queue](02-restore-finance-billing-exceptions-queue.md)

## Comments

- 2026-07-22: Added the read-only `migration-debt:inventory` scanner and guard. It reports runtime surfaces, owning contexts, debt findings, allowlist metadata, and explicit student/lecturer portal contract coverage. Portal consumer inventories classify types, composables, stores, and pages separately; nested portal repositories remain excluded from Swinx debt findings.
- 2026-07-22: Added path-level frozen-zone snapshots for services, top-level controllers, and split routes. The guard rejects unapproved new paths even when an aggregate baseline count is unchanged. Cross-context detection covers module imports and fully qualified module references.
- 2026-07-22: Validation passed: `./scripts/dev.sh artisan migration-debt:inventory --check --format=table`; `./scripts/dev.sh composer exec pint -- --dirty --format agent`; inventory tests (7 tests, 143 assertions); Billing Exceptions plus inventory tests (23 tests, 206 assertions); architecture suite (63 tests, 322 assertions); and `git diff --check`.
- 2026-07-22: `./scripts/dev.sh npm run type-check` could not complete because the existing Node/Vue type-check process exceeded its approximately 2 GB V8 heap. The full `./scripts/dev.sh test` run was attempted but could not complete after an existing PHP 256 MB memory-limit fatal in Laravel Eloquent attribute handling. No application data or portal files were changed. Parent issue remains `ready-for-agent`; sibling issue statuses were not changed.
