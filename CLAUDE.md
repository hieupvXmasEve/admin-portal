# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Purpose

This file is a short operating guide. Keep detailed architecture, standards, and feature contracts in `docs/`; do not duplicate long framework manuals here.

## Project Context

- Product: Swinx university operations platform.
- Backend: Laravel 12 monolith, PHP 8.4, MySQL 8, Redis, FrankenPHP.
- Frontend: Vue 3, TypeScript, Inertia v2, Tailwind CSS 4.
- Architecture: hybrid modular monolith.
  - Prefer new business logic in `app/Modules/{Domain}`.
  - Extend `app/Services/*` only when modifying existing service-led areas.
- Auth baseline: Sanctum + actor middleware for student/parent/lecturer API surfaces.
- Local runtime: Docker-first. Prefer `./scripts/dev.sh ...` wrappers.

## Source Of Truth

Read these before planning meaningful changes:

- `README.md` - current baseline, commands, operational risks.
- `docs/code-standards.md` - implementation rules and quality gates.
- `docs/system-architecture.md` - architecture and module boundaries.
- `docs/project-overview-pdr.md` - product and domain baseline.
- `docs/codebase-summary.md` - current codebase snapshot.
- `docs/design-guidelines.md` - UI and frontend standards.

Use focused docs under `docs/rules/`, `docs/api/`, and feature folders when the task touches those areas.

## Working Rules

- Inspect existing code and docs before asking for context.
- Keep changes scoped to the user request.
- Do not invent database fields, relationships, route names, permissions, props, or API contracts. Verify them in code/schema/docs.
- Follow YAGNI, KISS, DRY.
- Update existing files directly. Do not create duplicate "enhanced" files.
- Preserve user changes in the worktree. Do not revert unrelated edits.
- Prefer small, reviewable changes over broad rewrites.
- Keep reports concise. Put unresolved questions at the end.

## Commands

Use Docker wrappers unless there is a documented reason not to:

```bash
./scripts/dev.sh start
./scripts/dev.sh status
./scripts/dev.sh logs app
./scripts/dev.sh artisan <command>
./scripts/dev.sh composer <command>
./scripts/dev.sh npm <command>
./scripts/dev.sh test
```

Common checks:

```bash
./scripts/dev.sh test
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint
```

If a check cannot be run, state that clearly in the final summary.

## Backend Guidelines

- Controllers orchestrate only: validate, authorize, call action/query/service, return response.
- Use FormRequest for request validation.
- Use transactions for multi-write flows.
- Keep campus scoping explicit where data is campus-bound.
- Prefer Eloquent models/relationships; avoid raw queries unless justified by complexity.
- For APIs, follow the existing route group/auth style in that module unless the task explicitly migrates it.
- For new module logic, prefer `Actions/` for use cases and `Queries/` for complex reads.

## Frontend Guidelines

- Use `<script setup lang="ts">`.
- Prefer existing shared components and composables in `resources/js/components` and `resources/js/composables`.
- Use Inertia props/forms for page workflows.
- Use JSON API composables only for modal, drawer, inline, or non-navigating interactions that already follow that pattern.
- Prefer `route(...)` helpers and typed route constants over literal paths.
- Use `lucide-vue-next` icons and existing UI primitives.
- For server-driven tables/filters, follow the established shared table/filter patterns documented in `docs/code-standards.md`.

## Documentation

Update docs in the same change window when behavior, routes, contracts, auth, deployment, or architecture changes.

Core docs should stay evidence-first and include unresolved questions when decisions remain open.

For documentation-only edits like this file, application tests are not required; do a Markdown/readability sanity check instead.

## Done Criteria

Before final response, verify the relevant scope:

- Code compiles or the appropriate targeted checks were run.
- Tests were run for behavior changes, or the testing gap is explicit.
- Docs were updated when contracts or behavior changed.
- Final answer summarizes changed files, validation, and unresolved questions if any.
