# 02 - Formally retire `course_fee`

Status: done
Depends on: 01
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0027-obligation-type-registry-is-code-owned.md, docs/adr/0028-finance-charge-is-materialized-ledger-read-model.md
Portal impact: none

## What to build

Wave 6 audit found **zero** `course_fee` rows on `asia`, **no** generator under `app/`, and the type is **not** on the manual charge create form. Classify as **formally retire** — remove the type from active product surfaces and close the HP DNG free-pass that still treats `course_fee` as ungated.

## Context (from 01 audit)

- `finance_charges` where `charge_type = course_fee`: 0 active, 0 void
- Registry entry exists with `legacy_course_fee` source kind and DNG `HP`
- `CreateBatchDngFromChargesAction` deliberately leaves `course_fee` out of HP obligation-link guard — remove that carve-out once retired
- Tests still seed `course_fee` fixtures (DNG worklist HP mapping, tuition DNG closure, enum parity)

## Acceptance criteria

- [x] Product surfaces no longer offer or expect new `course_fee` generation (registry marks retired / audit-closed; Fee Monitor does not track it; no free-pass comments remain as open work).
- [x] HP DNG push guards **only** obligation-linked cut-over types that still exist; `course_fee` carve-out removed or made dead-code-safe with a test proving zero active `course_fee` rows is the invariant (or unknown type is ignored).
- [x] UI/type labels: retire or mark historical in staff-facing charge type maps if still listed.
- [x] Tests updated: no new business path depends on creating `course_fee`; historical enum value may remain in DB enum/CHECK for safety until wave 7.
- [x] Do **not** invent an intake generator for `course_fee`.
- [x] Program PRD inventory row for `course_fee` updated to "formally retire (wave 6 audit)".

## Out of scope

- Dropping the MySQL ENUM value / money-sign CHECK membership (wave 7 hardening may still want the value for historical dumps).
- Migrating imaginary rows (none exist on sample).

## Blocked by

- `.scratch/finance-obligation-v2-wave-6-audit-tail/issues/01-admission-course-adjustment-dng-audit.md`

## Implementation notes (2026-07-10)

- Registry: `course_fee` → `retired: true`, `CancellationPolicy::Retired`, `dngCollectionCode: null`, label "Phí môn học (đã ngừng)".
- `chargeTypesForDngCollectionCode` excludes retired types → HP worklist/batch only `tuition_term` + `egc_level_fee`.
- HP DNG push guards all selected payables (no course_fee carve-out); free-pass comments removed.
- UI labels marked historical; enum value retained for DB parity.
