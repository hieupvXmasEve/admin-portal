# Quality Guidelines

> **Canonical rules:** `docs/rules/` is the source of truth for coding standards.
> This file supplements the Trellis workflow context only. When in doubt, defer to `docs/rules/`.

> Code quality standards for frontend development.

---

## Overview

- Local frontend quality gates are ESLint, Prettier, and `vue-tsc`.
- The app prefers route helpers, typed props, shared composables, and shared UI primitives.
- Local verification remains the practical gate for frontend changes in this repo.

Sources:

- Frontend scripts: `package.json`
- Shared route helper convention: `resources/js/utils/routes.ts`
- App/plugin baseline: `resources/js/app.ts`

---

## Forbidden Patterns

- New literal endpoint or page URLs when a route helper already exists.
- Rebuilding filter/table behavior that already exists in shared components/composables.
- Untyped props on new shared components.
- Mixing raw `fetch` calls into pages that should use Inertia or `useApi`.

Reality note: some older pages still use literal `router.visit('/...')` paths, so treat those as migration debt, not a pattern to copy.

---

## Required Patterns

- Use `<script setup lang="ts">` for new Vue files.
- Use `route(...)` or wrapper helpers from `resources/js/utils/routes.ts` when available.
- Prefer `useForm` for Inertia submissions and `useApi` for non-navigating JSON flows.
- Reuse `@/components/ui` and shared filter/table primitives before adding custom one-offs.

---

## Testing Requirements

- Frontend behavior is currently verified mostly through Laravel/Pest feature tests and local smoke checks rather than a dedicated component test suite.
- For UI changes coupled to backend/Inertia props, update or add the related feature test when possible.
- At minimum run:
    - `npm run type-check`
    - `npm run lint`
    - `npm run format:check`

---

## Code Review Checklist

- Does the page/component reuse existing shared primitives?
- Are props, filters, and payloads typed clearly?
- Are route names/helpers used instead of fragile literal strings?
- Is the state model appropriate: local vs URL-driven vs Pinia?
- Did the author avoid copying legacy patterns when a newer shared composable already exists?
- Were local frontend checks run, or was the gap reported?
