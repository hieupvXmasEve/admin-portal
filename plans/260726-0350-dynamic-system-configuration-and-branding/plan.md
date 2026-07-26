---
title: "Dynamic System Configuration and Branding"
description: "Make system_settings the sole runtime authority for global settings and remove the legacy system_config.json dependency."
status: in-progress
priority: P1
effort: "5-7 developer days in one release"
branch: dev
tags: [feature, backend, database, frontend, infra]
blockedBy: []
blocks: []
created: 2026-07-26
---

# Dynamic System Configuration and Branding

## Overview

Make MySQL the authority for mutable global settings and use the Upload module
for immutable, durable branding objects. The staff app will update its name,
logos, favicon, and Apple touch icon without a source change or rebuild. Code
continues to own setting definitions, validation, and the public allowlist.

## Scope Challenge

- Existing code: keep Platform routes, permissions, audit logger,
  `SystemConfigurationReader`, Actions/Queries, and the Upload gateway.
- Minimum change: replace whole-file JSON writes, route branding uploads through
  Upload, and make Blade/Inertia consume one cached branding projection.
- Complexity: four phases; database, storage, backend delivery, frontend, and
  operations are inseparable for deploy-safe branding.
- Selected mode: HOLD SCOPE (assumed from the request).
- Portal impact: none. Student and lecturer Nuxt branding remains deploy-time.

## Decisions

| Topic | Decision |
|---|---|
| Settings authority | Global `system_settings` key/value rows in MySQL; every required runtime value is a materialized row |
| Public exposure | Code-owned allowlist; administrators cannot make an arbitrary key public |
| Asset authority | Upload records/object storage IDs, never raw paths or blobs in settings |
| Cache | One shared snapshot cache, invalidated only after committed writes; no process-local/file cache in multi-instance production |
| URLs | Immutable generated object names; no fixed-file overwrite or DOM cache-busting |
| Upload types | Raster PNG/WebP/JPEG initially; SVG upload deferred until an approved sanitizer exists |
| Rollout | Migration creates explicit default rows; activate DB-only runtime and delete the legacy JSON without import/backfill |
| Global authorization | Mutations require the existing `super_admin` system role; one campus permission is insufficient |
| Missing setting | Readiness fails for required rows; no JSON/source-value runtime fallback |

## Phases

| # | Phase | Status | Depends on |
|---:|---|---|---|
| 1 | [Database authority and compatibility](./phase-01-database-authority-and-compatibility.md) | In progress | — |
| 2 | [Managed branding assets](./phase-02-managed-branding-assets.md) | In progress | 1 |
| 3 | [Runtime branding delivery](./phase-03-runtime-branding-delivery.md) | In progress | 1, 2 |
| 4 | [Migration, deployment, and verification](./phase-04-migration-deployment-and-verification.md) | In progress | 1-3 |

## User Flow

```text
authorized system-role super administrator
  -> edit text or select a branding file
  -> FormRequest + permission check
  -> Platform Action
       -> FileUploadGateway stores immutable object (asset changes only)
       -> DB transaction upserts setting/reference
       -> activity audit records changed keys
  -> after commit: invalidate the single configuration snapshot cache
  -> redirect/response reloads shared Inertia branding
  -> Blade head and Vue components use the same resolved projection
```

## Cross-Plan Dependencies

No blocking dependency was found. The unfinished
`260726-0333-zero-migration-debt-closure` plan does not cite these files.
Coordinate execution if its Platform HTTP phase later claims the same files.

## Non-Goals

- No tenant/campus-specific branding.
- No secret/provider configuration in this generic store.
- No arbitrary admin-created setting definitions or public flags.
- No automatic rebranding of `FE/student-nuxt` or `FE/lecturer-nuxt`.
- No import/backfill of values or branding assets from the legacy JSON/storage paths.
- No automatic deletion of previous successful brand assets in the first release.

## Success Criteria

- [ ] Changing app name/logo/favicon persists in MySQL/object storage and survives a normal image rebuild/deploy.
- [ ] Blade title/favicon and all staff Vue consumers render the same current branding on first response.
- [ ] Facilities and Engagement receive explicit typed defaults and remain functional.
- [ ] Unauthorized writes, arbitrary path overwrite, MIME spoofing, and non-public key exposure are rejected.
- [ ] Cache invalidation gives read-after-write consistency across app instances.
- [ ] Migration creates default text/typed setting rows, asset references start empty, and the legacy JSON is deleted without import.
- [ ] Targeted backend/frontend/docs checks pass; portal impact remains none.

## Research

- [Evidence and trade-offs](./research/research-summary.md)

## Red Team Review

### Session — 2026-07-26

**Findings:** 9 deduplicated (8 accepted, 1 superseded by user decision)
**Severity breakdown:** 3 Critical, 6 High, 0 Medium

| # | Finding | Severity | Disposition | Applied To |
|---:|---|---|---|---|
| 1 | Legacy paths/static icons had no Upload-ID migration | Critical | Superseded: intentional reset, no backfill | Scope, Phases 2, 4 |
| 2 | Old file-based rollback would hide post-cutover DB changes; rollback is restricted to DB-capable builds | Critical | Accept | Phases 1, 4 |
| 3 | Campus permission could mutate global anonymous branding | Critical | Accept | Phases 1, 3 |
| 4 | Generic Upload API could create/delete branding records | High | Accept | Phase 2 |
| 5 | Upload could leak an object before record creation | High | Accept | Phase 2 |
| 6 | Dimension/canonical-extension guarantees lacked owning files | High | Accept | Phase 2 |
| 7 | API mutation compatibility was optional | High | Accept | Phase 3 |
| 8 | Inertia title/shared TS/OAuth branding were omitted | High | Accept | Phase 3 |
| 9 | Two caches and an assumed Redis store could stay stale | High | Accept | Phases 1, 3, 4 |

### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, all four phase files, research summary.
- Decision deltas checked: 9.
- Reconciled stale references: 9.
- Unresolved contradictions: 0.

## Validation Log

### Execution Sync — 2026-07-26

- Implemented: DB-only `system_settings` authority, Platform cache projection,
  internal immutable branding uploads, shared Blade/Inertia/API branding,
  canonical cutover documentation, and deletion of the legacy JSON file.
- Focused validation: Platform, architecture, and Upload suites passed (20
  tests, 117 assertions); frontend file-scoped lint/format/typecheck, Pint, and
  documentation validation passed.
- Remaining operations: production-style rebuild/redeploy, two-instance shared
  cache smoke test, operator text/logo/favicon update, and paired DB/object
  storage recovery rehearsal. The plan remains in progress until these are
  completed and the remaining acceptance coverage is added.

### Verification Results — 2026-07-26

- Tier: Standard (Fact Checker + Contract Verifier).
- Claims checked: 37.
- Verified: 37; failed: 0; unverified: 0.
- Verified surfaces: Platform routes/Actions/Queries/contracts, Facilities and
  Engagement readers, Upload gateway/manager/validator/policy, Blade/Inertia
  branding consumers, shared TypeScript props, Docker storage volumes, cache
  defaults, tests, and canonical docs.
- Decision interview: deferred; no blocking question remains under the explicit
  staff-only, global, raster-first scope.

### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, all four phase files, research summary.
- Renamed files/APIs/fields checked: DB storage seam, Upload IDs, branding
  slots, cache owner, permission boundary, API adapters.
- Unresolved contradictions: 0.

### Validation Session 2 — 2026-07-26 (Superseded)

- User decision: `system_settings` is the sole runtime configuration authority.
- Legacy file policy: one-time import source only; delete after reconciliation.
- Runtime policy: no JSON dual-read/write and no source-value fallback for
  required settings.
- Recovery policy: use DB/object backups and DB-capable images; never roll back
  to file-based code after cutover.
- Propagated to: overview decisions, phases 1/2/4, success criteria, risks, and
  deployment gates.

This import decision was replaced by Validation Session 3.

### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, all four phase files, research summary.
- Superseded terms checked: dual-read, export-to-legacy, file fallback,
  source defaults, file-based rollback.
- Unresolved contradictions: 0.

### Validation Session 3 — 2026-07-26

- User decision (Annotation 1): do not import/backfill legacy configuration or
  assets.
- Initialization: migration inserts explicit default text/typed rows in
  `system_settings`; branding upload IDs start `null`.
- Operator flow: user edits the defaults and uploads logo/favicon again.
- Legacy cleanup: delete `storage/app/private/system_config.json` after the
  DB-only build and default rows pass smoke checks.
- Propagated to: overview, phases 1/2/4, research, success criteria, risks, and
  red-team disposition.

### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, all four phase files, research summary.
- Superseded terms checked: importer, import dry-run, asset reconciliation,
  backfill, temporary migration command.
- Unresolved contradictions: 0.

## Open Questions

- None blocking. Scope assumes a deliberate reset to DB defaults, no legacy
  backfill, DB-only runtime persistence, staff-only branding,
  super-admin-only mutation,
  and raster uploads for the
  first release; expanding either changes the contract and plan.

<!-- slug: dynamic-system-configuration-and-branding -->
