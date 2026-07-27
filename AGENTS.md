---
title: Swinx Agent Guide
status: canonical
owner: Platform Team
last_verified: 2026-07-25
scope: agent-operations
---

# AGENTS.md

This is the single entry point for AI coding agents working in Swinx. Read this
file first, then load only the task-specific documents listed below.

## Project

- Laravel 13 modular monolith on PHP 8.4.
- Vue 3, TypeScript, Inertia v3, Tailwind CSS 4.
- MySQL 8, Redis, FrankenPHP, Docker-first development.
- Staff/admin web application plus student and lecturer API surfaces.
- Separate Nuxt portal repositories live under `FE/` and are ignored by this
  repository.

## Source precedence

When sources disagree, use this order:

1. The current user-approved requirement.
2. Active product/API contracts and accepted ADRs for intended behavior.
3. Code and tests for observed current behavior.
4. This file and `docs/rules/` for implementation practice.
5. Operational and integration runbooks.

Report contract-to-code drift explicitly. Do not silently treat either side as
correct.

## Always follow

- Preserve unrelated worktree changes.
- Apply YAGNI, KISS, then DRY.
- Verify current behavior before documenting it.
- Use descriptive names and existing repository conventions.
- New business logic belongs in `app/Modules/{Domain}`.
- Controllers orchestrate: validate, call an Action/Query, return a response.
- Cross-context access uses contracts, events, or projections; never import
  another context's Eloquent model for business decisions.
- API responses use `ApiResponse::success()` or `ApiResponse::error()`.
- Use named routes and `route(...)` rather than literal application URLs.
- All new PHP files declare `strict_types=1`.
- Do not add dependencies or new top-level directories without approval.

## Task routing

| Task | Read |
| --- | --- |
| Product behavior | `docs/project-overview-pdr.md`, relevant API/feature contract |
| Architecture/module boundary | `docs/system-architecture.md`, `docs/rules/architecture.md`, relevant ADRs |
| Backend PHP | `docs/rules/backend.md`, `docs/rules/naming.md` |
| Cross-context work | `docs/rules/contracts.md`, relevant ADRs |
| Frontend/Inertia | `docs/rules/frontend.md`, `docs/design-guidelines.md` |
| Filtered/paginated page | `docs/rules/filtering.md` |
| Legacy migration/refactor | `docs/rules/legacy-migration.md` |
| Security/auth/permissions | `docs/rules/security.md` |
| Realtime/notifications | `docs/rules/realtime.md`, relevant feature runbook |
| Tests | `docs/rules/testing.md` |
| Student/lecturer API | `docs/portal-repos.md`, matching `docs/api/` contract |
| Deployment/operations | `docs/deployment-guide.md`, relevant feature runbook |

Use `docs/README.md` as the canonical documentation registry.

## Commands

Run application commands through Docker wrappers:

```bash
./scripts/dev.sh start
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test
./scripts/portal-status.sh
```

Never run host `php artisan` for this project.

## Scoped verification

Run the narrowest checks that prove the changed behavior:

```bash
./scripts/dev.sh artisan test --compact --filter=<test>
./scripts/dev.sh npm exec eslint -- <changed-files>
./scripts/dev.sh npm exec prettier --check <changed-files>
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/check-docs.sh
./scripts/check-docs-freshness.sh
```

Do not run repository-wide PHP tests, global type-check, or global frontend lint
for an individual change unless requested or the change is cross-cutting.
Record broader checks intentionally not run as a scope decision.

## Backend rules

- Form validation uses FormRequest classes.
- Multi-write state changes use `DB::transaction()`.
- Actions own writes and business decisions; Queries own complex reads.
- Module controllers live under `app/Modules/{Domain}/Http/`.
- Cross-context interfaces live under `app/Shared/Contracts/`.
- Use Eloquent API Resources for public API payloads unless the existing
  contract explicitly uses another project convention.

## Frontend rules

- New Vue components use `<script setup lang="ts">` and a single root element.
- Inertia page forms use `useForm` from `@inertiajs/vue3`.
- Non-navigating modal/drawer forms use vee-validate, Zod, and `useApi`.
- Filter/pagination pages use `useDataTable`.
- Reuse `@/components/ui` and `lucide-vue-next`.
- Use `DatePicker` and `TimePicker`, not native date/time inputs.
- Modal date pickers set `portal-to` to the modal content element.
- Keep `useNativeDialog: false` in `resources/js/app.ts`.
- Laravel/Inertia props use snake_case in TypeScript interfaces.

## Inertia v3

- Deferred props: `Inertia::defer(fn () => ...)`.
- One-time props: `Inertia::once(fn () => ...)`.
- Flash data: `Inertia::flash(...)`.
- `<Deferred>` receives a string data prop, for example
  `<Deferred data="scores_data">`.
- Do not use removed APIs such as `Inertia::lazy()` or `router.cancel()`.
- Query official version-specific documentation before implementing unfamiliar
  Laravel or Inertia behavior.

## Portal boundaries

Before changing student- or lecturer-facing contracts:

1. Set `Portal impact: none | student | lecturer | both`.
2. Run `./scripts/portal-status.sh`.
3. Read the matching API contract under `docs/api/`.
4. Update only the affected nested portal.
5. Keep Swinx and portal Git states separate.
6. Run portal lint, typecheck, and build when portal code changes, or record why
   a check could not run.

## Documentation

- English is the canonical technical-documentation language.
- Every canonical Markdown file uses YAML frontmatter.
- One knowledge type has one home; reference instead of copying.
- `CONTEXT.md` contains stable business vocabulary only.
- `docs/adr/` contains accepted, currently applicable decisions only.
- `docs/api/` contains public API contracts.
- `docs/features/` contains current integration and operations runbooks.
- `docs-site/` holds the Vietnamese end-user guide. It is the only home for
  user-facing instructions; do not duplicate them under `docs/`.
- Do not create plans, reports, stories, archives, or duplicate framework docs
  in the repository.

### End-user guide

A change to the staff sidebar or to a user-facing screen must update the
matching page under `docs-site/src/content/docs/` in the same change.

Each page declares the files it documents in its frontmatter `source:` list.
`./scripts/check-docs-freshness.sh` fails when a listed file changed and the
page did not. When a screen moves or is retired, update the `source:` list too.

The guide is published in Vietnamese at the site root, English under `en/`, and
Korean under `ko/`. Vietnamese is authoritative: write it first, then update the
English page in the same change. A locale with no translated page falls back to
Vietnamese, so a missing Korean page is expected and is not a failure.

Write for staff who do not read code: use the labels shown on screen, describe
one action per step, and never mention routes, permission codes, table names,
or framework internals.

## Forbidden patterns

| Avoid | Use |
| --- | --- |
| New business logic in `app/Services/` | Module Actions/Queries |
| Direct cross-module Eloquent access | Shared contract/event/projection |
| `response()->json()` for application APIs | `ApiResponse` |
| `useInertiaFilters` or `useServerTableQuery` | `useDataTable` |
| vee-validate for full Inertia page forms | Inertia `useForm` |
| Literal application URLs | Named `route(...)` |
| `Inertia::lazy()` | `Inertia::defer()` or `optional()` |
| `->with('success', ...)` for Inertia flash | `Inertia::flash()` |
| Solid primary/accent selection background | Subtle border/ring selection |
| New duplicated documentation | Update the canonical document |

## Done criteria

- Changed behavior is covered by targeted checks.
- PHP changes are formatted with Pint.
- Frontend changes pass file-scoped lint/format and relevant tests.
- API, auth, route, schema, and architecture changes update canonical docs.
- Cross-repo portal impact is resolved.
- `scripts/check-docs.sh` passes for documentation changes.
- Final response lists changed files, validation results, scope decisions, and
  unresolved questions.
