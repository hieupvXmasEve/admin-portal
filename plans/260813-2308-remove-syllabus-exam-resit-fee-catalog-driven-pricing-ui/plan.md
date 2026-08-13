---
title: "Remove Syllabus exam_resit_fee, catalog-driven pricing UI"
description: "Delete SyllabusTemplate.exam_resit_fee (dead field, real price already comes from FinancePricingCatalog); replace its validation gate with a catalog-price-existence check; replace the raw JSON facts_match textarea in Pricing Operations with a Unit picker."
status: completed
priority: P1
effort: "1-2d"
tags: [finance, academic, exam-resit, pricing-catalog]
created: 2026-08-13
---

# Remove Syllabus exam_resit_fee, catalog-driven pricing UI

## Overview

Investigation (2026-08-13, `plans/reports/`) traced student `AUS15054`'s error
`"Syllabus chưa cấu hình exam_resit_fee hợp lệ."` to `SyllabusTemplate.exam_resit_fee`
— a field that **only gates validation** and is **never used as the real charge
amount**. The real amount for `exam_resit_fee`/`retake_fee` always comes from
`FinancePricingCatalogItem` via `PricingStrategy::CatalogFixed`
(`RequestFinanceDebitAction` forbids Academic from passing an amount at all —
ADR-0026). Today only one catch-all catalog rule exists (750,000 VND,
`facts_match: null`), so all units are charged identically regardless of what
the syllabus field said.

User decision: stop maintaining the dead syllabus field entirely, and stop
forcing staff to hand-type JSON to price a unit differently — give them a Unit
picker on `/finance/pricing-operations`.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Delete `SyllabusTemplate.exam_resit_fee` (column, model, validation, forms) | P1 |
| 2 | Replace the dead-field validation gate in exam-resit creation with a catalog-price-existence check that fails with a clear message | P1 |
| 3 | Replace the raw `facts_match_json` textarea in Pricing Operations with a Unit combobox for `retake_fee`/`exam_resit_fee` rules | P1 |
| 4 | Keep `ExamResitAttempt.fee_amount` populated for audit, sourced from the Finance-resolved price instead of the syllabus snapshot | P2 |

## Non-Goals

- `retake_fee` syllabus-side field removal — none exists; `retake_fee` was
  already catalog-only. Out of scope.
- Campus/semester-based catalog differentiation — not requested; only
  per-unit pricing was asked for. `facts_match` still supports it later if
  needed (no backend change required to add).
- Any change to `FinanceCharge.TYPE_EXAM_RESIT_FEE` / `AcademicFinanceSourceKeys::EXAM_RESIT_FEE`
  constants, or the `exam_resit_fee` **charge_type/obligation_type** string
  itself. That string stays — only the SyllabusTemplate *column* of the same
  name goes away. Do not confuse the two during implementation.
- `CreateExamResitChargeSimpleAction` retirement — ADR-0026 §5.5 defers this
  decision; not part of this plan.

## Architecture Decision

Instead of pre-validating against a new cross-module "pricing preview"
contract (would require a new `app/Shared/Contracts/Finance/` interface —
none exists today, `FinancePricingCatalog` is Finance-internal), reuse the
eager Finance Intake call `CreateExamResitAttemptAction` already makes in the
same DB transaction. Catch the `RuntimeException` thrown by
`FinancePricingCatalog::price()` when no active catalog rule matches, and
translate it to a `ValidationException` with a clear message before the
transaction commits. This is smaller than adding a gateway (YAGNI) and keeps
the eager-cutover transaction boundary from ADR-0026 intact.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Backend — remove SyllabusTemplate.exam_resit_fee, catalog-driven validation](./phase-02-backend-remove-syllabustemplateexam-resit-fee-catalog-driven-validation.md) | Completed |
| 2 | [Phase 2: Frontend — strip exam_resit_fee from Syllabus Template pages](./phase-03-frontend-strip-exam-resit-fee-from-syllabus-template-pages.md) | Completed |
| 3 | [Phase 3: Pricing Operations — structured unit-picker UI replacing raw JSON](./phase-04-pricing-operations-structured-unit-picker-ui-replacing-raw-json.md) | Completed |
| 4 | [Phase 4: Test suite updates](./phase-05-test-suite-updates.md) | Completed |

## Success Criteria

- [x] `syllabus_templates.exam_resit_fee` column dropped; no code reads it
- [x] Creating an exam-resit attempt for a unit with NO matching catalog rule
      fails with a clear staff-facing message (not a raw 500/RuntimeException) —
      scoped by matching the specific "no active catalog item" RuntimeException
      message, so unrelated intake failures still propagate untranslated
- [x] Creating an exam-resit attempt for a unit WITH a matching catalog rule
      succeeds and `ExamResitAttempt.fee_amount` reflects the catalog price
- [x] `/finance/pricing-operations` lets staff pick a Unit from a searchable
      combobox instead of typing `{"unit_id": 123}` JSON by hand
- [x] Full existing pricing-operations flows (catch-all rules, campus/semester
      facts if any exist) keep working — Unit picker is additive, not a
      narrowing of `facts_match`'s JSON shape: the combobox is the default
      entry for `retake_fee`/`exam_resit_fee`, with a "Nhập facts_match thủ
      công (JSON)" toggle that reveals the raw textarea for non-`unit_id`
      facts_match shapes (campus/semester scoping etc.)
- [x] `./scripts/dev.sh artisan test` green for touched test files (118/118);
      eslint --fix clean on touched Vue files; `pnpm type-check` (whole-project
      vue-tsc) still skipped per known OOM gotcha in the dev container

## Open Questions

None — architecture confirmed via source read (`RequestFinanceDebitAction:78-86`,
`FinancePricingCatalog:27-212`, `ObligationTypeRegistry:182,197`), and via the
`/ak:plan validate` verification pass + user interview below.

## Validation Log

### Verification Results (Standard tier — Fact Checker + Contract Verifier, 2026-08-13)

- Claims checked: 15 (file:line citations across Phase 1 and Phase 3)
- Verified: 13 | Failed: 2 | Unverified: 0
- Tier: Standard (4 phases)

#### Failures found and resolved via interview

1. **[Contract Verifier]** `ExamResitAttempt::markFinanceObligationCreated(int $userId): void`
   (`app/Models/ExamResitAttempt.php:222`) does NOT accept a fee amount
   parameter. Phase 1 had assumed it would be used to set `fee_amount` —
   wrong. **Resolved:** extend the signature (see Decision 2 below).
2. **[Fact Checker]** Phase 3 proposed `pricing-unit-combobox.vue`
   (kebab-case) but this repo's existing combobox components are PascalCase
   (`StudentCombobox.vue`, `LectureCombobox.vue`,
   `resources/js/components/finance/FinanceCommandPalette.vue`). **Resolved:**
   see Decision 3 below.

#### Additional evidence gathered (not a plan error, a real data finding)

- DB check: `syllabus_templates` has 146 rows; only **1** has a non-null
  `exam_resit_fee` — `id=149, unit_id=42, fee=3,000,001₫, is_active=Y`
  (baseline catch-all catalog rule is 750,000₫). Surfaced to user as a
  data-loss risk before dropping the column (Decision 1).
- `units/search` route (`app/Modules/Academic/Catalog/routes/web.php:89`)
  is gated by `can:view_unit` (Academic permission) — unconfirmed whether
  Finance pricing-operations staff hold it (Decision 4).

### Interview Decisions (2026-08-13)

1. **Unit 42's custom fee (3,000,001₫):** User chose **no migration** —
   "không cần di trú, tạo lại từ đầu" (don't migrate; recreate from scratch
   later if that unit actually needs a custom price). Phase 1 does NOT
   include a data-migration step. This is an explicit, accepted decision —
   not an oversight. If unit 42 needs a non-baseline exam-resit price, staff
   must create that catalog rule manually via the new Phase 3 UI after this
   ships.
2. **`fee_amount` setter:** Extend
   `markFinanceObligationCreated(int $userId, float $feeAmount): void` to
   accept and persist the Finance-resolved amount in one call (atomic with
   the `hq_fee_status` transition).
3. **Component path:** `resources/js/components/finance/PricingUnitCombobox.vue`
   (PascalCase, under the existing `finance/` subfolder alongside
   `FinanceCommandPalette.vue`).
4. **`units/search` permission:** Not confirmed Finance staff hold
   `view_unit` — add a Finance-side proxy route in
   `app/Modules/Finance/routes/web.php` with its own permission gate,
   delegating to the same Academic search logic, rather than depending on
   Academic's route/guard directly.

### Whole-Plan Consistency Sweep

- Files reread: `plan.md`, `phase-02-...md`, `phase-03-...md`,
  `phase-04-...md`, `phase-05-...md`
- Decision deltas checked: 4 (fee_amount setter signature, component path,
  proxy route, no-migration for unit 42)
- Reconciled stale references: 4 (Phase 1 `markFinanceObligationCreated`
  usage note + name-collision warning against `CourseRetakeRegistration`'s
  unrelated same-named method, Phase 3 component path + route reuse
  assumption, Phase 4 test expectations for the setter signature)
- Unresolved contradictions: 0

<!-- slug: remove-syllabus-exam-resit-fee-catalog-driven-pricing-ui -->
