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

- Phases 1–3 completed and approved; phase 4 is in progress; phase 4b is done.
- Phases 5–12 were rescoped on 2026-08-16 against a fresh measurement after a
  validation pass found the plan 19 days and 212 commits behind the code.
- Phase 1b is done: `.github/workflows/migration-debt-guard.yml` now blocks merge
  on drift, and all 14 baselines are re-pinned to the 2026-08-16 measured counts.
- Phase 6 started 2026-08-16: `DeferCase`/`DeferCaseItem` moved from `app/Models`
  into `app/Modules/Finance/Models` (their only consumers). `shared_model_imports`
  398→387, phase-6 findings 144→133, zero regressions (`tests/Feature/Finance`
  gained 35 passes against its pre-existing red baseline). Still open: the bulk
  of the remaining `shared_model_imports` findings and all frozen-shell work.
  That change shipped without lowering its baseline, so the ratchet held 11
  findings of stale headroom until the phase 4 session below re-pinned it.
- Phase 4, 2026-08-16: Delivery's `Lecture` cluster (3 `shared_model_imports`
  findings across `AssignExamResitInvigilatorAction`, `LecturerAssessmentRequest`,
  `GetCourseOfferingCatalogFormQuery`) moved onto already-bound
  `LecturerReferenceReader`/`LecturerTeachingActor`/`AvailableLecturerReader`
  contracts. `shared_model_imports` 379→376, both baselines re-pinned in the
  same change. Zero regressions; a pre-existing `Architecture` failure
  (`TeachingEligibilityAssignmentBoundaryTest`) now passes as a side effect.
  Phase 4 remaining scope: 44 (see phase-04's Remaining Scope table).

## Goals

Start values are the 2026-07-26 snapshot. Live values were measured on
2026-08-16 with `migration-debt:inventory`. Run that command for current truth;
these numbers go stale quickly and are recorded for direction, not as authority.

| Goal | Start (2026-07-26) | Live (2026-08-16) | Exit |
|---|---:|---:|---:|
| Shared model imports | 568 | 376 | 0 exact |
| Frozen services / controllers / routes | 86 / 76 / 32 | 55 / 30 / 12 | 0 / 0 / 0 exact |
| Direct JSON / inline validation | 45 / 77 | 20 / 38 | 0 / 0 exact |
| Missing PHP / route strict types | 178 / 22 | 133 / 11 | 0 / 0 exact |
| Legacy filters / URLs / page directories | 25 / 60 / 0 | 24 / 40 / 0 | 0 / 0 / 0 exact |
| Migration commands | 7 | 7 | 0 migration-classified commands |
| Cross-context concrete imports / removed Inertia APIs | 0 / 0 | 0 / 0 | remain 0 exact |

Total open findings on 2026-08-16: 771 at the session-start measurement, 746
live (after the phase-4 `Lecture` cluster below; 749 immediately before it). The delta is 11 from the `DeferCase`/`DeferCaseItem` move in `c78b66f6e`
(committed without re-pinning its baseline, so the ratchet carried 11 findings of
stale headroom until this session caught it) plus 11 from the two phase 4 slices
— 3 `direct_json_responses`/`inline_request_validation` and 8
`shared_model_imports` one-offs, both in Academic Catalog/Delivery; see phase 4.
All 14 baselines are re-pinned to the live count, so every rule again sits
exactly at its ceiling.

Shared model imports and legacy filter stacks are the two rules that moved the
wrong way. Both regressed against an approved baseline with no CI to stop them;
phase 1b addresses the cause and re-pins the floor.

## Non-goals

- No baseline or allowlist increase to hide findings. Phase 1b's one-time re-pin
  is the single permitted exception, is recorded with its cause, and ships in the
  same change that makes further increases impossible.
- No mass namespace move without real owner/consumer separation.
- No rewriting historical migrations or dropping canonical tables for aesthetics.
- No production schema cleanup until both in-scope campus dossiers independently pass.

## Campus scope

The deploy workflow targets five production campuses. Schema cleanup covers two.

| Campus | Deploy | Schema cleanup |
|---|---|---|
| metropolia | SSH | In scope |
| asia-vn | SSH | In scope |
| knu | Docker | Out of scope |
| gachon | Docker | Out of scope |
| jinan | Docker | Out of scope |

The three Docker campuses were added on 2026-07-30, after this plan was frozen.
The product owner confirmed on 2026-08-16 that they hold no real production data
yet and scoped them out on that basis.

They are live deploy targets, so emptiness is a current state rather than a
guarantee. Phase 11 re-confirms it immediately before the drop and makes cleanup
migrations refuse to run against an out-of-scope target; documentation alone does
not protect an irreversible drop.

## Phases

| # | Phase | Depends on |
|---:|---|---|
| 1 | [Trustworthy zero contract](./phase-01-trustworthy-zero-contract.md) | — |
| 1b | [Enforce the ratchet in CI](./phase-01b-enforce-ratchet-in-ci.md) | 1 |
| 2 | [Canonicalize frontend page paths](./phase-02-canonicalize-frontend-page-paths.md) | 1; owner batches only |
| 3 | [Close shared reference foundations](./phase-03-close-shared-reference-foundations.md) | 1 |
| 4 | [Migrate Academic catalog and delivery](./phase-04-migrate-academic-catalog-and-delivery.md) | 3; relevant phase-2 batch |
| 4b | [Retire SemesterEnrollmentController](./phase-04b-retire-semester-enrollment.md) | 4 |
| 5 | [Migrate Academic progression and portals](./phase-05-migrate-academic-progression-and-portals.md) | 3, 4 |
| 6 | [Close Finance domain debt](./phase-06-close-finance-domain-debt.md) | 3, 5 |
| 7 | [Migrate operational domains and platform HTTP](./phase-07-migrate-operational-domains-and-platform-http.md) | 3, 5 for portal packages |
| 8 | [Close reporting, AI, and frontend interaction debt](./phase-08-close-reporting-ai-and-frontend-interaction-debt.md) | 4–7 |
| 9 | [Retire frozen shells and strict-type residue](./phase-09-retire-frozen-shells-and-strict-type-residue.md) | 4–8 |
| 10 | [Prepare data reconciliation and migration tooling](./phase-10-reconcile-data-and-retire-migration-tooling.md) | 5, 6, 9 |
| 11 | [Rehearse schema cleanup per campus](./phase-11-rehearse-schema-cleanup-per-campus.md) | 10 |
| 12 | [Lock exact zero and roll out production](./phase-12-lock-exact-zero-and-roll-out-production.md) | 11 |

### Measured distribution (2026-08-16, after the owner-map correction)

| Phase | Findings | Dominant rules |
|---|---:|---|
| 4 | 47 | shared model imports |
| 5 | 117 | `Academic/Progression` imports plus the Student/Lecturer API shells |
| 6 | 144 | shared model imports, concentrated in Finance models and queries |
| 7 | 167 | shared model imports; Engagement largest, Merchandise new |
| 8 | 97 | literal URLs, AI model imports, filter stacks |
| 9 | 181 | strict types |
| 10 | 7 | migration commands |

`MigrationDebtInventory::workPackage()` previously routed every `frozen_services`,
`frozen_controllers`, and `frozen_routes` finding to phase 9 regardless of whether
the shell still served a live workflow. Phase 9 does not perform business
cutovers, so those findings had no real owner. `frozenShellPhase()` now routes the
Student and Lecturer API surfaces to phase 5, the scholarship, tuition-plan,
voucher, and financial-import surfaces to phase 6, and the email, notification,
gold, wallet, and Admissions surfaces to phase 7; genuine residue still falls
through to 9. Phase 9 dropped from 241 to 181 and is now strict-types dominated,
which is the correct shape for a residue phase. Total findings are unchanged at 771.

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

## Related completed work

Several sibling plans closed legacy work in this program's territory after it was
frozen. Their outcomes are already reflected in the live measurement above; do
not re-plan what they finished.

| Plan | Status | Overlap |
|---|---|---|
| `260807-0042-legacy-notificationemail-decommission` | completed | Phase 7 notification and email |
| `260809-1557-legacy-model-module-migration` | done | Shared model imports |
| `260809-2135-cut-student-legacy-finance-reverse-relations` | completed | Phase 6 Student/Finance boundary |
| `260811-0012-deprecated-model-shim-namespace-sweep` | completed | Shared model imports |
| `260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup` | completed | Phase 9 residue |

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
- [ ] The ratchet is enforced by a required CI check, proven by a real blocked pull request.
- [ ] No undocumented `ApiResponse::compatible()` or model-returning shared contract remains.
- [ ] Academic reconciliation is eligible for retirement with zero unexplained exceptions/consumers.
- [ ] Finance canonical totals reconcile per campus; permanent commands are owner-classified.
- [ ] Full backend, frontend, both portals, docs, route, schedule, queue, and health gates pass.
- [ ] Metropolia and Asia each have a restore-tested backup, signed dossier, soak,
  and rollback path; rollout order is a signed risk decision, not hard-coded.
- [ ] The three out-of-scope campuses are re-proven data-free at execution time and
  cannot execute a cleanup migration.

## Validation Log

### Session 1 — 2026-08-16

Full-tier verification, 12 phases at the time of the run.

#### Verification Results

- **Tier:** Full
- **Claims checked:** 41
- **Verified:** 33 | **Failed:** 6 | **Unverified:** 2

Verified highlights: all 14 rule IDs exist in `config/migration_debt.php` and
`MigrationDebtContract::BASELINE_CEILINGS`; the guard, inventory, and both config
files exist at the cited paths; the July 9 `active_source_key` drop migration
exists and that column has zero application consumers, only the two migrations
that add and drop it; the seven `migration_commands` files match the phase 10
inventory exactly; phase 3's retired paths (`app/Services/PermissionService.php`,
`routes/web/auth.php`, `routes/web/settings.php`) are absent as expected.

#### Failures

1. [Flow Tracer] Phase 1 records "make path/baseline drift fail CI" as satisfied.
   `.github/workflows/` contains only `admin-portal-user-guide.yml` and
   `deploy.yml`; there is no test, lint, or check workflow in the repository. The
   guard has only ever run by hand.
2. [Fact Checker] The guard fails on `dev` as of this run. `shared_model_imports`
   398 against baseline 289; `legacy_filter_stacks` 24 against baseline 22.
   212 commits landed between the last ratchet update and this measurement.
3. [Scope Auditor] `deploy.yml` deploys five production campuses; phases 11 and 12
   were written for two. knu, gachon, and jinan were added 2026-07-30.
4. [Fact Checker] Phase 8 cited `app/Modules/Academic/Reporting`, which does not
   exist. Academic reporting projections live at
   `app/Modules/Academic/Progression/Queries/Reporting`.
5. [Contract Verifier] The `Merchandise` module carries 24 findings and is tagged
   to phase 7 by the scanner, but no phase named it. It shipped 2026-08-01.
6. [Fact Checker] `plan.md`'s Goals column presented the 2026-07-26 start snapshot
   as current state; phase 4's residual count read 53 against a live 55.

Both unverified items were resolved in session 2 below.

#### Decisions

| Question | Decision |
|---|---|
| Plan is 19 days and 212 commits behind | Re-measure the code and rewrite phases 5–12 against it. Phases 1–4 keep their record. |
| Ratchet regressed with no enforcement | Add a blocking pull-request check, then re-pin every baseline to the measured live count. Recorded as phase 1b. |
| Five deploy campuses versus two planned | Keep schema cleanup at two campuses; record knu, gachon, and jinan as out of scope. |
| Merchandise has no owning phase | Name it explicitly in phase 7's scope, requirements, and success criteria. |

Advisory attached to the campus decision: exclusion is only safe if enforced.
Phase 11 now requires a per-campus data check and a preflight guard that aborts a
cleanup migration on an out-of-scope target, because a forward-only drop cannot be
undone by documentation.

#### Changes applied

- Created `phase-01b-enforce-ratchet-in-ci.md`.
- Rewrote phases 5, 6, 7, 8, 9, 10, 11, and 12 against the 2026-08-16 measurement.
- Rewrote `plan.md`: Goals split into start and live columns, campus scope section,
  measured distribution table, related completed work, phase 1b in the phases table,
  and two new success criteria.
- Corrected the phase 8 path, the phase 4 residual count, and the phase 9 ownership
  inflation.

#### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, `phase-01`, `phase-01b`, `phase-02`, `phase-03`,
  `phase-04`, `phase-04b`, `phase-05` through `phase-12`.
- Decision deltas checked: 4
- Reconciled stale references: 6
- Unresolved contradictions: 0

### Session 2 — 2026-08-16, resolving session 1's open items

#### Answers

1. **Do knu, gachon, and jinan hold production data?** No. The product owner
   confirmed none of the three holds real data yet. The exclusion stands, but
   phase 11 now re-confirms emptiness immediately before the drop rather than
   relying on this observation, because all three are live deploy targets and can
   take on data before that phase runs.
2. **Was the phase-9 tagging of Student API surfaces configuration or inference?**
   Neither: it was a hardcoded rule in the scanner. Corrected in code.

#### Code change

`app/Support/MigrationDebt/MigrationDebtInventory.php`: `workPackage()` mapped all
three frozen-shell rules to phase 9 unconditionally. Added `frozenShellPhase()`,
which routes a shell to the phase that owns its workflow and leaves genuine
residue on 9. Effect on the measurement, with the total unchanged at 771:

| Phase | Before | After |
|---|---:|---:|
| 5 | 93 | 117 |
| 6 | 131 | 144 |
| 7 | 144 | 167 |
| 9 | 241 | 181 |

`tests/Feature/Architecture/MigrationDebtInventoryTest.php` reports 2 failed and
11 passed both with and without this change, verified by stashing it. Both
failures are the baseline regression phase 1b addresses, not a regression from
this edit.

#### Sibling plan status correction

`plans/260801-0203-merchandise-store/index.md` led with "DRAFT — chờ user chốt.
Chưa implement." while its own phases table marked all four phases DONE and the
code, admin UI, portal pages, contract doc, and tests all exist. Corrected to
COMPLETED 2026-08-02 with its deferred items named.

#### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, `phase-01b`, `phase-05`, `phase-06`, `phase-07`,
  `phase-09`, `phase-11`, `phase-12`.
- Decision deltas checked: 3
- Reconciled stale references: 5
- Unresolved contradictions: 0

<!-- slug: zero-migration-debt-closure -->
