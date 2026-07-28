---
title: "Zero Migration Debt Closure"
description: "Eliminate every tracked migration-debt category without baseline inflation, compatibility concealment, or unsafe schema retirement."
status: in-progress
priority: P1
effort: "XL / multi-release"
tags: [architecture, domain-migration, data-migration, release]
created: 2026-07-26
---

# Zero Migration Debt Closure

## Overview

Drive all fourteen migration-debt rules to exact zero while preserving behavior,
data integrity, portal contracts, and campus-specific recovery. Physical tables
may remain when they are the canonical store; only superseded schema is removed
after its reader/writer count and unexplained data exceptions are both zero.

## Progress

- Phases 1–3 completed and approved; Phase 4 is in progress.
- Current phase: 4 — Migrate Academic catalog and delivery.

## Goals

| Goal | Current | Exit |
|---|---:|---:|
| Shared model imports | 568 | 0 exact |
| Frozen services/controllers/routes | 86 / 76 / 32 | 0 / 0 / 0 exact |
| Direct JSON / inline validation | 45 / 77 files | 0 / 0 exact |
| Missing PHP/route strict types | 178 / 22 | 0 / 0 exact |
| Legacy filters/URLs/page directories | 25 / 60 / 0 | 0 / 0 / 0 exact |
| Migration commands | 7 | 0 migration-classified commands |
| Cross-context concrete imports / removed Inertia APIs | 0 / 0 | remain 0 exact |

## Non-goals

- No baseline or allowlist increase to hide findings.
- No mass namespace move without real owner/consumer separation.
- No rewriting historical migrations or dropping canonical tables for aesthetics.
- No production migration until both campus dossiers independently pass.

## Phases

| # | Phase | Depends on |
|---:|---|---|
| 1 | [Trustworthy zero contract](./phase-01-trustworthy-zero-contract.md) | — |
| 2 | [Canonicalize frontend page paths](./phase-02-canonicalize-frontend-page-paths.md) | 1; owner batches only |
| 3 | [Close shared reference foundations](./phase-03-close-shared-reference-foundations.md) | 1 |
| 4 | [Migrate Academic catalog and delivery](./phase-04-migrate-academic-catalog-and-delivery.md) | 3; relevant phase-2 batch |
| 5 | [Migrate Academic progression and portals](./phase-05-migrate-academic-progression-and-portals.md) | 3, 4 |
| 6 | [Close Finance domain debt](./phase-06-close-finance-domain-debt.md) | 3, 5 |
| 7 | [Migrate operational domains and platform HTTP](./phase-07-migrate-operational-domains-and-platform-http.md) | 3, 5 for portal packages |
| 8 | [Close reporting, AI, and frontend interaction debt](./phase-08-close-reporting-ai-and-frontend-interaction-debt.md) | 4–7 |
| 9 | [Retire frozen shells and strict-type residue](./phase-09-retire-frozen-shells-and-strict-type-residue.md) | 4–8 |
| 10 | [Prepare data reconciliation and migration tooling](./phase-10-reconcile-data-and-retire-migration-tooling.md) | 5, 6, 9 |
| 11 | [Rehearse schema cleanup per campus](./phase-11-rehearse-schema-cleanup-per-campus.md) | 10 |
| 12 | [Lock exact zero and roll out production](./phase-12-lock-exact-zero-and-roll-out-production.md) | 11 |

## Execution rules

- Work in vertical owner slices: characterize → introduce seam → switch readers
  and writers → migrate HTTP/frontend → remove compatibility shell → ratchet.
- Every phase is a program of bounded PR work packages, never one mega-PR. Before
  coding, freeze a ledger row containing owner/workflow, exact files, entry points,
  rule/before/delta/after, interfaces, dependencies, portal/data impact, tests,
  rollback, and merge gate; one file has exactly one terminal-disposition owner.
- After every slice, commit code and its lowered inventory baseline together.
- Backfill approval never authorizes cleanup; forward-only cleanup is a separate release.
- Portal impact is declared per slice and nested repository checks remain separate.
- Research basis: [research summary](./research/research-summary.md).

## Deferred defects

Defects this program uncovered but did not fix, because fixing them is product
work rather than migration work. Each is pinned by a test that records current
behaviour explicitly as current, not intended.

| Found | Defect | Evidence |
|---|---|---|
| 2026-07-28, phase 4 | `v1.student.attendance.course-attendance` has never worked for an enrolled student. `StudentAttendanceService::getCourseAttendance` filters `attendances.course_offering_id` and orders by `attendances.session_date`; neither is a column on `attendances` — both live on `class_sessions`. Every enrolled request returns 422 with a SQL error. `formatAttendanceRecords` also reads `$record->session_date`, so the fix has to reshape the read, not just the where clause. | `tests/Feature/Api/V1/Student/AttendanceControllerTest.php` |

## Success Criteria

- [ ] The immutable set of 14 rule IDs and required runtime roots is present; every
  rule is configured as `exact: 0` and both configured and independent audits pass.
- [ ] No undocumented `ApiResponse::compatible()` or model-returning shared contract remains.
- [ ] Academic reconciliation is eligible for retirement with zero unexplained exceptions/consumers.
- [ ] Finance canonical totals reconcile per campus; permanent commands are owner-classified.
- [ ] Full backend, frontend, both portals, docs, route, schedule, queue, and health gates pass.
- [ ] Metropolia and Asia each have a restore-tested backup, signed dossier, soak,
  and rollback path; rollout order is a signed risk decision, not hard-coded.

<!-- slug: zero-migration-debt-closure -->
