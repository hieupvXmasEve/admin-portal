---
phase: 1
title: "Establish authoritative evidence and baseline"
status: done
priority: P1
effort: "4h"
dependencies: []
---

# Phase 1: Establish authoritative evidence and baseline

## Overview

Write down the evidence basis, its limits, and the decision reversal, so the
reasoning behind every placement is reproducible six months from now. No product
code changes in this phase. It exists because the first version of this plan
shipped an IA built on unverified, unreproducible data — and because after
validation session 1 that data rests on an attestation, which must be recorded as
such rather than presented as a measurement.

## Requirements

Functional:
- The basis for treating the holder-set table as authoritative is recorded,
  including its limits.
- The seeder drift is documented as an accepted environment dependency.
- The reversal of the 2026-06-18 Vietnamese business-naming decision is recorded
  as an ADR.
- The parity baseline commit is confirmed resolvable.

`Course Statistics` placement and `CRM Value Mappings` ownership were resolved in
validation session 1 and are no longer this phase's work — see `plan.md`'s
Validation Log, decisions 4 and 7.

Non-functional:
- Every recorded fact carries the query or file:line that produced it.
- No edits to `menu-sidebar.ts`.

## Architecture

Two sources disagree about which roles exist and what they hold:

| Source | Roles | Status |
|--------|-------|--------|
| `database/seeders/InitialSetup/RoleAndPermissionSeeder.php:71-75` | 5, with pinned IDs | in version control, **does not match reality** |
| local/dev database | 9; `HQ`, `Cán Bộ Đào tạo`, `Hành chính` absent from any seeder; role IDs drifted | mutable and unversioned, but attested to match production, so authoritative for this plan |

The seeder is not the source of truth for role assignment and has not been for
some time. That is a real repo defect — it makes any permission-shaped work
unverifiable on a fresh environment — but validation decision 2 puts it outside
this plan's scope.

Effective permissions are additionally campus-scoped and cached: 
`EloquentCampusPermissionReader.php:24-26` joins `role_permissions` through
`campus_user_roles` filtered by `campus_id`, and `:18,82` cache per
`user_{id}_campus_{id}` for one day. A role-level rollup is therefore an upper
bound, not any specific user's sidebar.

## Related Code Files

- Read: `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`
- Read: `app/Modules/Identity/Support/EloquentCampusPermissionReader.php`
- Read: `resources/js/pages/**/CourseStatistics*` (locate via glob)
- Create: `docs/adr/00XX-admin-sidebar-titles-are-english.md`
- Modify: `plans/260816-2125-sidebar-menu-ia-restructure/plan.md` (record answers)

## Implementation Steps

<!-- Updated: Validation Session 1 — decisions 1, 2, 4, 7 -->

1. **Record the evidence attestation.** The maintainer attested on 2026-08-16
   that the local database matches production, so the holder-set table in
   `plan.md` is authoritative and no production query is run. Write that
   attestation into `plan.md` explicitly — who attested, when, and that it is an
   attestation rather than a diff — so a later reader can tell how much weight it
   carries. If anyone later observes a local/production divergence, every move in
   the Moves table must be re-derived before shipping.

2. **Document the seeder drift.** `HQ`, `Cán Bộ Đào tạo`, `Hành chính`, and
   `Check student application` exist in no seeder, so `migrate:fresh --seed` (or
   `./scripts/reset-local-asia-db.sh`) destroys them. Per validation decision 2,
   these roles are **environment-provisioned and stay that way** — this plan does
   not touch `RoleAndPermissionSeeder`, which also avoids the role-ID collision
   (the seeder pins ID 5 to `parent`; the database has `Check student
   application` there). Record in `plan.md` that Phase 4 verification depends on
   a non-reset database, and note the drift as a known repo defect owned
   elsewhere.

3. **Write the naming ADR.** `FinanceOfficeCutoverTest.php:118-125` encodes a
   deliberate decision from commit `fa6da56a7` (2026-06-18): the Finance menu
   uses Vietnamese business terminology and must not use the English equivalents.
   The all-English decision reverses it. Write
   `docs/adr/00XX-admin-sidebar-titles-are-english.md` covering:
   - the original decision and where it was encoded;
   - why it is being reversed;
   - that Finance *page bodies* remain Vietnamese for now, and the open i18n
     question;
   - that `FinanceOfficeCutoverTest` and `FinanceReportingShellTest` are updated
     as a consequence, not as incidental churn.
   Use the next free ADR number.

4. **Confirm the baseline.** Verify
   `git show 31bea61e8cd16715f99b1c896cdd31189d64838f:resources/js/constants/menu-sidebar.ts`
   resolves and that the working tree copy is unmodified. This SHA is the parity
   baseline for Phases 2-4 and is pinned in `plan.md` frontmatter.

## Success Criteria

- [ ] Attestation recorded in `plan.md` with attester, date, and its limits
- [ ] Seeder drift documented as an accepted environment dependency, with the
      note that Phase 4 verification requires a non-reset database
- [ ] ADR written and merged for the English-titles reversal
- [ ] Baseline SHA verified resolvable; target file unmodified

## Risk Assessment

The dominant risk is now concentrated in one place: the holder-set table rests on
an attestation that local matches production, not on a diff. If that attestation
is wrong, six of the plan's moves are wrong and nothing downstream catches it —
the structural test in Phase 4 verifies shape, not whether the shape suits real
users. This is accepted deliberately (validation decision 1) and is recorded as
the plan's largest risk rather than mitigated.

Secondary: because the three key roles are not seeded, any environment reset
during this work destroys the verification personas. Do not run
`migrate:fresh --seed` or `./scripts/reset-local-asia-db.sh` on the machine used
for Phase 4 verification.

The temptation is to skip this phase because it produces no visible change. Every
red-team finding that invalidated the previous IA traces to a fact assumed rather
than confirmed here.
