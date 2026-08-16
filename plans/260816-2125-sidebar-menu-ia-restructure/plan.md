---
title: "sidebar menu ia restructure"
description: "Rebuild the admin sidebar information architecture from per-permission holder sets, normalize titles to English, and sync the user-guide site that mirrors the group labels."
status: pending
priority: P2
effort: "3d"
tags: [frontend, ia, navigation, docs]
created: 2026-08-16
baseline_commit: 31bea61e8cd16715f99b1c896cdd31189d64838f
---

# sidebar menu ia restructure

## Overview

`resources/js/constants/menu-sidebar.ts` (804 lines) drives the admin sidebar. It
has accumulated placement drift: pure-HQ commerce screens sit in a facilities
group, money instruments sit outside Finance, one integration is listed twice,
titles mix Vietnamese and English, and a 30-line dead comment block plus a dead
export still ship.

This plan restructures the group tree so each group maps to the roles that
actually hold its permissions, normalizes titles to English, and updates the two
downstream surfaces that mirror those labels: three Pest tests that assert on the
file's literal contents, and the `docs-site` user guide.

**This plan was rewritten after an adversarial review.** The first version was
built on a domain-prefix rollup of role permissions, which produced three wrong
moves, and it omitted the `docs-site` surface entirely. See
`plans/reports/red-team-260816-2129-sidebar-menu-ia-restructure.md` for the 15
findings. Every placement below is now derived from per-permission holder sets.

## Evidence

### Rendering contract

- `AppSidebar.vue:17` — `filterMenuGroups(mainNavGroups)` is the only consumer.
- `usePermissions.ts:34-61` — `filterMenuItems` is recursive; a group with no
  surviving item is dropped. **Important correction:** the function returns early
  for any item carrying `children` (`:36-44`), so a *wrapper's own*
  `requiredPermissions` is never evaluated. Visibility of a subgroup is decided
  entirely by its leaves.
- **This filtering is client-side only.** The whole tree ships in the JS bundle
  and `page.props.auth.permissions` is a render input, not an access control.
  Menu placement is a usability decision, not a security one. Server-side gaps
  found during review are filed separately as
  [#135](https://github.com/swinburne-edu/asia-admin-portal/issues/135).
- `NavMain.vue:85,89` and `NavMenuItem.vue:112,133` — `group.label`,
  `item.title`, and `child.title` are the Vue `v-for` **keys**. They are not
  display-only. Duplicate siblings produce duplicate keys and mis-patched
  collapsible state.
- `NavMenuItem.vue:56,59-70` — `isOpen` is only ever set to `true`; collapsibles
  latch open for the session once auto-opened.
- `NavMain.vue:30-58` — `findBestMatch` iterates in array order and returns on
  the first exact match, so array order is semantically meaningful.
- `menu-sidebar.ts:804` — `mainNavItems` export is dead; `AppHeader.vue:41`
  declares its own local array.
- `menu-sidebar.ts:440-469` — commented `Finance Legacy (Old UI)` block.

### Downstream consumers (both were missed in v1)

1. **Pest tests read this file as text and assert literal labels:**
   - `tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php:110` requires
     `Lập yêu cầu thanh toán DNG`; `:118-125` requires `Sinh HP/Tuition` and
     `Sinh phí EGC` **and forbids** `Generate HP (Tuition)` / `EGC · Generate Charges`
   - `tests/Feature/Finance/Reporting/FinanceReportingShellTest.php:82,90`
     requires `Finance Reporting`
   - `tests/Feature/Finance/DetailRouteContractTest.php:111`

   Note `Sinh HP/Tuition` and `Sinh phí EGC` exist **only inside the commented
   block**. That assertion has been passing vacuously against a comment since the
   items were moved to Legacy — it is not protecting live behavior.

2. **`docs-site` mirrors the group labels.** `docs-site/astro.config.mjs:57-89`
   hard-codes `Attendance & Completion`, `Student Services`, `Faculty & Teaching`,
   `Forms & Quality`, `Campus Operations`, `Finance Office` as navigation labels
   with `ko`/`zh` translations and directory slugs. 64 pages under
   `docs-site/src/content/docs` declare `menu-sidebar.ts` in their `source:`
   frontmatter, and `scripts/check-docs-freshness.sh` exits 1 when a declared
   source changes without its pages — wired into CI at
   `.github/workflows/admin-portal-user-guide.yml:26`.

### Permission holder sets

All 71 permissions referenced by the menu were queried per-permission (not by
name prefix). Legend: SA=Super Admin, DIR=Giám Đốc Đào Tạo, ACA=Cán Bộ Đào tạo,
ADM=Hành chính, TP=Trưởng Phòng, CB=Cán Bộ.

| Cluster | Holders | Permissions |
|---------|---------|-------------|
| Pure HQ | `HQ\|SA` | all `finance_*` except revenue/settings/dng-campus-mappings, all `egc_*`, `clubs`, `merchandise`, `merchandise_report`, `redemption_order`, `scholarship`, `tuition_plan`, `voucher`, `crm_value_mapping` |
| Pure Hành chính | `ADM\|SA` | `view_room_booking`, `create_room_booking`, `approve_room_booking` |
| Pure Academic Staff | `ACA\|SA` | `exam_resit`, `manage_exam_schedule`, `student_action`, `academic_report` |
| Academic both | `ACA\|DIR\|SA` | `canvas_integration`, `lecturer` |
| Super Admin only | `SA` | `notification_ops`, `send_manual_notification`, `role`, `system_config`, `system_log`, `ai_metrics`, `ai_provider_settings`, `manage_departments`, `finance_revenue_report`, `finance_settings`, `finance_dng_campus_mappings` |
| Broadly shared | `ACA\|DIR\|HQ\|SA` | `attendance`, `campus`, `course_offering`, `curriculum_version`, `form`, `grade`, `semester`, `syllabus`, `event`, `review_form` |
| Widest | 5-6 roles | `student`, `program`, `unit`, `user` (excludes ACA), `room` |

**Status of this evidence.** The maintainer (hieupv) attested on 2026-08-16 that
the local database matches production, so the table above is treated as
authoritative (validation decision 1). This is an attestation, not a diff — no
production query was run — if local and production have in fact drifted, the
placement decisions drawn from this table are wrong, and that is the single
largest risk in this plan.

Two known qualifications remain, both accepted rather than fixed:

- The seeder (`RoleAndPermissionSeeder.php:71-75`) creates 5 roles (`Super
  Admin`, `Giám Đốc Đào Tạo`, `Trưởng Phòng`, `Cán Bộ`, `Phụ huynh`); the
  database has 9. `HQ`, `Cán Bộ Đào tạo`, and `Hành chính` exist in no seeder,
  and role IDs have drifted (the seeder pins ID 5 to `Phụ huynh`/`parent`; the
  live database has `Check student application` there). They are
  environment-provisioned; `migrate:fresh --seed` or
  `./scripts/reset-local-asia-db.sh` destroys them (validation decision 2). This
  is a known repo defect owned elsewhere, not by this plan. **Phase 4's
  per-persona spot check requires a non-reset database** — do not run either
  reset command on the verification machine during this plan's execution.
- Permissions are campus-scoped and cached for a day
  (`EloquentCampusPermissionReader.php:18,24-26,82`), so a role-level rollup is
  an upper bound on any individual user's sidebar. Verification must name the
  campus and clear the cache.

### Constraints

- ADR-0007 fixes the Students and Reports & Audits group shapes. Both keep their
  current contents.
- ADR-0026 puts Progression Actions in Academic and money in Finance. This is why
  `Scholarship Adjustments` moves to Academic rather than into Finance.
- Titles become English by user decision (2026-08-16). This **reverses** the
  Vietnamese business-naming decision encoded in `FinanceOfficeCutoverTest`
  (commit `fa6da56a7`, 2026-06-18). Phase 1 records an ADR for the reversal;
  Phase 2 updates the test in the same commit as the rename.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Each group has one clear owner; items shared across roles are declared as shared rather than pretended to be single-owner | P1 |
| 2 | All menu titles in English, with the reversal of the prior naming decision recorded | P1 |
| 3 | Zero dead code in `menu-sidebar.ts` | P1 |
| 4 | No screen in two places; no nesting deeper than group > item > child; no duplicate sibling titles | P2 |
| 5 | Every `href` reachable before the change is reachable after, proven mechanically against a pinned baseline | P1 |
| 6 | `docs-site` navigation, slugs, translations, and freshness markers match the shipped menu | P1 |

Goal 1 deliberately does **not** claim one persona per group. Holder sets make
that achievable only for Finance, Store & Clubs, and Super-Admin-only entries.

## Target information architecture

10 groups (from 11 active + 1 commented):

| # | Group | Contents | Owner |
|---|-------|----------|-------|
| 1 | Overview | Dashboard | all |
| 2 | Academic Operations | Curriculum Setup, Course Delivery (+ Canvas Settings, + **Course Statistics**), Attendance, Grades & Performance (+ **Scholarship Adjustments**), **Faculty** | ACA/DIR |
| 3 | Students | Students, Enrollments & Holds, Student Applications, CRM Value Mappings | shared, 6 roles |
| 4 | Reports & Audits | Lifecycle & Decisions, Academic Performance | ACA/DIR (ADR-0007) |
| 5 | Finance | Today, Finance Reporting, Revenue*, Fee Generation, Collections & Settlement, Exceptions, **Discounts & Funding** (4 items), Lookup & Audit | HQ |
| 6 | Store & Clubs | Clubs, Merchandise (Store, Redemption Orders, Reports) | HQ (pure) |
| 7 | Campus | Rooms & Bookings, Events | shared; bookings ADM-only |
| 8 | Forms & Surveys | Forms Library, Runs, Surveys, Staff Inbox | ACA/DIR/HQ |
| 9 | Communications | Email, Notifications* | shared; ops SA-only |
| 10 | Administration | Identity & Access, Organization, Integrations, System Operations | SA, plus read-only oversight roles |

`*` marks groups containing Super-Admin-only entries that will not render for the
group's nominal owner.

### Moves, each justified by holder set

| Move | From | To | Holder evidence |
|------|------|----|-----------------|
| Tuition Plans, Scholarships, Student Scholarships, Vouchers | own top-level group | Finance subgroup | all `HQ\|SA`, identical to `finance_*` |
| **Scholarship Adjustments** | Discounts & Funding | Academic Operations > Grades & Performance | `view_scholarship_adjustment` = `ACA\|HQ\|SA`; ACA holds **no** `finance_*`. ADR-0026 makes it a Progression Action |
| Clubs, Merchandise, Redemption Orders | Campus Operations | Store & Clubs | all `HQ\|SA`, pure cluster |
| Events | Campus Operations | Campus | `view_event` = `ACA\|DIR\|HQ\|SA`, nearly identical to `view_room` |
| Faculty (3 items) | own top-level group | Academic Operations subgroup | `view_lecturer` = `ACA\|DIR\|SA`, a subset of the group's owners |
| Canvas Integrations | Administration > Integrations | Academic Operations > Course Delivery, as `Canvas Settings` | both Canvas entries already share `view_canvas_integration` |
| **Course Statistics** | Attendance | Academic Operations > Course Delivery | `CourseStatistics/Index.vue:24-25` reports `average_attendance` **and** `average_grade` per course offering — it is a per-offering summary, not a single-metric screen |

**Rejected from v1:** splitting Campus Operations into "Campus Life" vs
"Facilities". `view_room` (5 holders) and `view_event` (4 holders) have nearly
identical holder sets — the real boundary is the booking workflow (`ADM|SA`),
not facilities-vs-commerce. Rooms and Events stay together.

**Not moved:** `CRM Value Mappings` stays in `Students` (validation decision 7).
It maps CRM values for the Student Applications flow, so it sits beside the
feature it serves. Moving it to Administration would hand HQ a group containing
one item — the defect this plan exists to remove.

**Cross-surface contradiction, resolved:** `docs-site/astro.config.mjs:70-74`
classifies `Scholarship Adjustments` under `Finance Office`
(`slug: 'finance-office/scholarship-adjustments'`), following the item's old menu
placement. Validation decision 3: **the app placement wins** — the item moves to
Academic, and Phase 5 moves its docs page and adds a redirect.

## Red Team Review

### Session — 2026-08-16
**Findings:** 15 (15 accepted, 0 rejected)
**Severity breakdown:** 6 Critical, 5 High, 4 Medium
**Reviewers:** Security Adversary, Assumption Destroyer, Failure Mode Analyst
**Report:** `plans/reports/red-team-260816-2129-sidebar-menu-ia-restructure.md`

The review found the plan's evidence base used the wrong granularity (domain
prefix rollup instead of per-permission holder sets), invalidating 3 of 7 moves,
and that two downstream surfaces were entirely absent: three Pest tests that
assert on this file's literal contents, and the `docs-site` user guide with its
CI freshness gate. The plan was rewritten from 3 phases to 5 rather than patched.

Filed separately as a consequence: ungated Forms/Canvas admin routes,
[#135](https://github.com/swinburne-edu/asia-admin-portal/issues/135).

### Whole-Plan Consistency Sweep

Reconciled after the rewrite:

- `Finance Reporting` kept as the final title everywhere (the v1 IA table said
  `Reporting`, which `FinanceReportingShellTest:90` pins).
- `Scholarship Adjustments` destination is Academic > Grades & Performance in
  both `plan.md` and Phase 3; the conflicting `docs-site` classification is now
  recorded above and owned by Phase 5 step 2.
- Goal 1 restated as "one owner plus declared shared items"; the v1 "one persona
  per group" wording is removed from every file, and Super-Admin-only entries are
  marked in the IA table.
- All parity gates reference `baseline_commit`, never `HEAD`; all lint commands
  route through `./scripts/dev.sh` with `--max-warnings=0`.
- The v1 "Campus Life / Facilities" split is removed everywhere and replaced by
  `Campus` + `Store & Clubs`, with the rejection reason recorded in the Moves
  section.

No unresolved contradictions remain between `plan.md` and the phase files. The
open questions below are unanswered inputs, not internal contradictions.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Establish authoritative evidence and baseline](./phase-01-establish-authoritative-evidence-and-baseline.md) | Done |
| 2 | [Dead code, English naming, and test contract updates](./phase-02-dead-code-english-naming-and-test-contract-updates.md) | Done |
| 3 | [IA restructure from holder sets](./phase-03-ia-restructure-from-holder-sets.md) | Done |
| 4 | [Nesting, placement, and automated verification gates](./phase-04-nesting-placement-and-automated-verification-gates.md) | Done |
| 5 | [Docs-site label and content sync](./phase-05-docs-site-label-and-content-sync.md) | Done |

Strictly sequential. Phases 2-4 touch the same file and must not run in parallel.

**Delivery shape (validation decision 5): one PR, five commits.**
`scripts/check-docs-freshness.sh` diffs the whole `base...HEAD` range, so the
Phase 5 docs updates satisfy the gate for the menu changes made in Phases 2-4.
Splitting into separate PRs would red the gate on the code-only PR and force it
to be bypassed, destroying the protection. One commit per phase keeps each phase
revertable with `git revert`.

The route-parity gate always compares against the pinned `baseline_commit` in
this file's frontmatter, never against `HEAD`.

## Success Criteria

- [x] Authoritative role/permission source confirmed and recorded (Phase 1)
- [x] `menu-sidebar.ts` has no commented group and no unused export
- [x] Every `title`/`label` in the file is English
- [x] Group tree matches the target IA table
- [x] Multiset of `href` values identical to `baseline_commit`, and no duplicates
- [x] No duplicate sibling `title`, no duplicate group `label`
- [x] No nesting deeper than group > item > child
- [x] `./scripts/dev.sh artisan test tests/Feature/Finance/Cutover tests/Feature/Finance/Reporting tests/Feature/Finance/DetailRouteContractTest.php` green (62/62)
- [x] `./scripts/dev.sh npm run lint -- --max-warnings=0 resources/js/constants/menu-sidebar.ts` clean
- [x] `./scripts/check-docs-freshness.sh` exits 0 on the PR diff (simulated against uncommitted working tree; real gate needs the phase commits to land)
- [x] `docs-site` labels, slugs, and ko/zh translations match the shipped menu (ko/zh prose is best-effort, not native-reviewed — see Phase 5 execution note)

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Evidence base is dev-DB-only and may not match production | Critical — every placement decision | Phase 1 blocks the rest until confirmed |
| An `href` dropped or duplicated during block moves | High | Multiset parity gate against pinned baseline, run at the end of Phases 2, 3, and 4 |
| Duplicate sibling titles after renaming/promotion break Vue keys | High | Scripted uniqueness check, not visual review |
| `docs-site` freshness gate reds the PR | High | Phase 5 is in scope and sized; not discovered late |
| Reversing the 2026-06-18 business-naming decision without a record | Medium | ADR written in Phase 1 before the rename lands |
| Faculty fold costs the largest persona one extra click | Low | Accepted; recorded in Phase 3 |
| Staff muscle memory breaks | Medium | Six moves, each ownership-justified; cover in release notes |

## Validation Log

### Session 1 — 2026-08-16
**Questions asked:** 7 | **Verification pass:** skipped per guard (Red Team
section already carries codebase-verified evidence; no `[UNVERIFIED]` tags
remained)

| # | Decision | Effect |
|---|----------|--------|
| 1 | Local DB matches production (maintainer attestation) | Holder-set table is authoritative. Phase 1 records the attestation instead of querying production. Recorded as the plan's largest risk. |
| 2 | Document the seeder drift; do not seed the three roles | Phase 1 narrows to recording the environment dependency. Keeps this plan out of RBAC seeder work and away from the role-ID collision. |
| 3 | `Scholarship Adjustments`: the app placement wins | Stays moved to Academic in Phase 3; Phase 5 moves the docs page off `finance-office/` and adds a redirect. |
| 4 | `Course Statistics` -> Course Delivery | Resolved by reading `CourseStatistics/Index.vue:24-25`: it reports `average_attendance` **and** `average_grade` per course offering, so it is a per-offering summary, not an attendance screen. The `Attendance` subgroup drops to two items. |
| 5 | One PR, five commits | Docs freshness gate compares the whole range, so Phase 5 covers Phases 2-4. Splitting would force a gate bypass. |
| 6 | Keep the Faculty fold | `view_lecturer` (`ACA\|DIR\|SA`) is a strict subset of the group's owners. `isOpen` latches open for the session (`NavMenuItem.vue:56,67-69`), so the extra click is first-visit only. |
| 7 | `CRM Value Mappings` stays in `Students` | It serves the Student Applications flow. Moving it to Administration would give HQ a one-item group — the defect this plan removes. |

### Whole-Plan Consistency Sweep

Propagated and reconciled after the interview:

- `Course Statistics` now appears under Course Delivery in `plan.md`'s IA table,
  the Moves table, and Phase 3; the `Attendance` subgroup is described as two
  items everywhere.
- `CRM Value Mappings` no longer described as deferred in `plan.md`, and Phase 1
  and Phase 3 no longer carry steps to resolve or move it.
- Phase 1 step 1 changed from "query production" to "record the attestation";
  step 2 narrowed to documenting the drift; steps for open questions 3 and 4
  removed as resolved.
- Phase 5 step 2 changed from "decide the classification" to executing the
  decided move plus redirect.
- Commit/PR shape stated once, in `plan.md`, and referenced rather than restated
  in the phases.

No unresolved contradictions between `plan.md` and the phase files.

## Open Questions

1. Finance page bodies remain Vietnamese while the menu becomes English. Should a
   follow-up translate them, or should the app move to real i18n with a locale
   switch? The `docs-site` already carries `ko`/`zh` translations, which argues
   for i18n over per-surface translation. Out of scope here; needs its own plan.
2. Menu `requiredPermissions` diverge from route-enforced permissions in three
   places (recorded in Phase 4). Reconciling them belongs to
   [#135](https://github.com/swinburne-edu/asia-admin-portal/issues/135), not
   this plan — but someone must own that issue for the divergence to be closed.

<!-- slug: sidebar-menu-ia-restructure -->
