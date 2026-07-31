---
phase: 2
title: "Phase 2: Finance Core Adjustment Model"
status: todo
priority: P1
effort: "6d"
dependencies: [1]
---

# Phase 2: Finance Core Adjustment Model

## Overview

Finance-owned storage + application of a per-semester scholarship adjustment: new table, semester-aware discount resolution across ALL scholarship money surfaces, `GIẢM TRỪ HỌC BỔNG` breakdown as a separate payload key, and timing guards keyed on real invoice/DNG state.

<!-- red-team 2026-08-01: campus_id added; fixed_amount vocab; zero-amount ledger op; DNG-based timing guards; reconciliation exception handling; Shared contract; batch fixes; breakdown as separate key -->

## Requirements

- Functional:
  - Store an approved adjustment scoped to (student, target semester) with maker/checker + campus snapshot.
  - EVERY scholarship money surface (single charge, batch, major preview, fee summary) resolves through the adjustment; preview == execute.
  - Full suspension (`adjusted_amount = 0`) is an explicit ledger operation that zeroes/deactivates any existing scholarship `InvoiceDiscount` — never an absent write.
  - Fee summary exposes breakdown via a NEW payload key `scholarship_breakdown` (original / deduction / effective); the settlement line collection is untouched.
  - Timing guards keyed on active DNG requests + paid amounts (see table), default-deny for unlisted states.
- Non-functional: no DB enums; decimal(12,2); applied rows immutable (corrections = reversal rows); `original_type` values are `percentage|fixed_amount` (matching `scholarship_definitions.type` enum — NOT `fixed`).

## Architecture

**New table `scholarship_semester_adjustments`** (Finance module):

```
id
student_id                FK students
campus_id                 FK campuses   // snapshot from students.campus_id — required for record-level campus checks
student_scholarship_award_id FK student_scholarship_awards
scholarship_code          string(50) snapshot
source_semester_id        FK semesters
target_semester_id        FK semesters
original_type             string  // percentage|fixed_amount (snapshot)
original_amount           decimal(12,2) snapshot
award_fingerprint         string  // hash(code|type|amount) at approval — re-validated at apply time
adjusted_amount           decimal(12,2)  // 0 = full suspension
status                    string(40)  // pending_apply|applied|finance_review_required|reversed
reason                    text
academic_dossier_id       unsignedBigInteger  // required; read back through the Academic contract, no FK
created_by_user_id, approved_by_user_id  FK users
applied_at, reversed_at   timestamps nullable
timestamps
Index (student_id, target_semester_id). Invariant (service-level, lockForUpdate inside transaction):
  max one row with status IN (pending_apply, applied, finance_review_required) per (student_id, target_semester_id).
  // student_scholarship_awards has unique(student_id) → one award per student; scholarship_code stays as snapshot only, not an invariant dimension.
NOTE: status is NEVER overloaded for restoration. An adjustment stays `applied` for its target semester forever;
  restoration lives in Phase 5's proposals table. Lookup excludes only `reversed`.
```

**Resolver (KISS, stays pure):**

```php
public function resolveAdjusted(ScholarshipDefinition $def, float $baseAmount, ?ScholarshipSemesterAdjustment $adj): float
// $adj null → resolve(). Else compute using adjusted_amount with original_type semantics
// ('percentage' branch; everything else = flat, matching resolver's existing === 'percentage' logic),
// clamp [0, baseAmount] AND ≤ resolve($def, $baseAmount). May return 0.0 — callers MUST NOT treat 0 as no-op (see below).
```

**Lookup:** `GetActiveScholarshipAdjustmentQuery::handle(int $studentId, int $semesterId): ?ScholarshipSemesterAdjustment` — status NOT IN (`reversed`). Do NOT resolve original terms via `InvoiceDiscount::scholarship()` — that relation joins `scholarship_definitions.id = reference_id` while writers store the AWARD id (`InvoiceDiscount.php:44-47` vs `CreateFinanceChargeAction.php:113-119`); always use the adjustment's snapshot columns. Add a code note on the miswired relation.

**Money-surface inventory (anchor discovery on `discount_type === 'scholarship'` + `applyInvoiceDiscount` call sites, NOT the resolver class):**

1. `CreateFinanceChargeAction.php:86-122` — per-charge apply. Change: adjustment lookup; and the `if ($discountAmount <= 0) return;` guard (line 109) must be bypassed when an adjustment exists — zero must flow to the ledger operation.
2. `GenerateBatchChargesAction.php:433-441` — existence short-circuit (`$existingScholarshipDiscount` → return null) blocks refresh when an adjustment arrives after a first run. Change: compare stored amount vs expected adjusted amount, refresh on mismatch.
3. `PreviewMajorChargeGenerationQuery.php:293` — preview path, same lookup (parity).
4. `GetStudentFeeSummaryQuery.php` — BOTH renderers: `mapInvoiceLines` (:274-289) AND the second discount mapper `mapInvoiceDiscounts` (:687-720, exposed as `discounts` key).
5. `SettlementService::createOrRefreshInvoiceDiscount` (:445-458) — `firstOrNew` + overwrite semantics; see multi-charge risk below.

**Zero/suspension ledger operation:** when adjusted resolution = 0 (or reversal shrinks a discount), explicitly zero or deactivate the existing scholarship `InvoiceDiscount` row (amount=0 or status transition) through the settlement service inside the same guarded transaction — every generation path currently early-returns on ≤0 and would leave the original −30M row live. Test: apply full suspension to an invoice already carrying a scholarship discount → `total_amount` changes.

**Contract entry point (consumed by Phase 3) — Shared contract, not a Module action import:**

- Create interface `App\Shared\Contracts\Finance\ScholarshipAdjustmentContract` + DTO `App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData` (readonly), mirroring `FinanceIntakeContract` / `AcademicFinanceChargeSourceGateway` pattern. Bind implementation (`ApplyScholarshipSemesterAdjustmentAction`) in the Finance service provider.
- Server-side re-authorization inside the action (caller trust = zero):
  - load maker and checker users; both active; maker ≠ checker;
  - checker holds `approve_scholarship_adjustment` AT the student's campus via `CampusPermissionReader::permissionCodesForUserId($checkerId, $campusId)` with NON-NULL campus (null = all-campus union — never pass null);
  - award exists AND `award_fingerprint` matches current award (guards against `StudentFinancialImportService.php:282-312` mutating `scholarship_code` in place);
  - `adjusted ∈ [0, original]`; no existing active adjustment for (student, target semester);
  - `academic_dossier_id` present (Academic passes its id; Finance stores it opaque).
- Award-mutation guard: `StudentFinancialImportService` update path HARD-REFUSES the row (import error, no write) when an active adjustment exists for the student — adjustment must be reversed/closed first (validation session 1). Fingerprint therefore never drifts.

**Timing guards (replaces invoice-status table — `student_invoices.status` enum is `draft|pending|paid|partial|overdue|cancelled`; there is NO `issued` (FIN-25), and DNG reservations key on invoice LINES + settlement position, not invoice status — `DngReservationLifecycle.php:100-163`):**

| Condition (evaluate in order) | Behavior |
|---|---|
| No tuition invoice for target semester yet | store `pending_apply`; resolver applies at generation |
| Active `DngPaymentRequest` (`pending`/`pushed_to_dng`/`unknown_outcome`) touching the invoice's lines, OR `cached_paid_amount > 0`, OR status `paid`/`partial`/`overdue` | `finance_review_required`; NO auto change (protects captured_settlement_version/fingerprint) |
| Status `cancelled` | `finance_review_required` (manual routing) |
| Else (`draft`/`pending`, no active DNG, unpaid) | recompute discount in transaction, mark `applied` |
| Any other/unknown state | default-deny → `finance_review_required` |

**Reconciliation failure branch:** `applyInvoiceDiscount` wraps discount + installment reconcile in ONE transaction (`InvoiceGenerationService.php:208-247`) and `ReconcileChargeInstallmentsAction` throws `InstallmentReconciliationException` (`no_pending_to_reconcile` :95-103, `committed_exceeds_net_due` :81-93) — a deduction raises net due and can hit both. The contract action MUST catch it and persist the adjustment as `finance_review_required` in a separate committed transaction, returning that outcome. Ordering rule: Finance call FIRST, Academic writes its dossier status from the returned outcome — the approval must never be rolled back by a ledger refusal.

**Batch path rules:** `GenerateBatchChargesAction` runs one transaction for the whole loop, catches per-student exceptions into `$stats['errors']`, then commits (:87, :379-384). Do NOT transition adjustment status inside the loop. Wrap each student in a savepoint; transition `pending_apply → applied` post-commit, keyed on the discount row actually matching the expected adjusted amount.

**Multi-charge invoices:** `applyScholarship` computes per-charge but `createOrRefreshInvoiceDiscount` upserts ONE row per (invoice, type, reference, source) and overwrites. Mandatory test: two `tuition_term` charges on one invoice → effective discount equals adjusted resolution over TOTAL tuition, not the last charge; only then transition `applied`.

**Display:** add `scholarship_breakdown` payload key to `GetStudentFeeSummaryQuery` output (built from adjustment snapshot columns): `{original_amount, deduction_amount, effective_amount, label: 'GIẢM TRỪ HỌC BỔNG'}`. Do NOT inject synthetic rows into the invoice line collection — `FeeTab.vue:329` sums `Math.abs(line.amount)` for `affects_payable` lines and would double-count. Update `mapInvoiceDiscounts` (:687-720) so the `discounts` panel shows the adjusted amount consistently. FE renders the breakdown from the new key.

**Reversal:** `status → reversed` + `reversed_at`; then same timing-guard table (incl. zero-op and reconciliation catch). Never UPDATE amounts on an applied row.

## Related Code Files

- Create: migration `create_scholarship_semester_adjustments_table`
- Create: `app/Modules/Finance/Models/ScholarshipSemesterAdjustment.php` (auditable, status allow-list constants)
- Create: `app/Modules/Finance/Queries/GetActiveScholarshipAdjustmentQuery.php`
- Create: `app/Shared/Contracts/Finance/ScholarshipAdjustmentContract.php` + `app/Shared/Contracts/Finance/DTO/ScholarshipAdjustmentData.php`
- Create: `app/Modules/Finance/Actions/ApplyScholarshipSemesterAdjustmentAction.php` (implements contract; bound in Finance provider)
- Modify: `app/Modules/Finance/Support/ScholarshipDiscountResolver.php` — `resolveAdjusted()`
- Modify: `app/Modules/Finance/Actions/CreateFinanceChargeAction.php` — lookup + zero-op path
- Modify: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php:433` — amount-compare refresh + savepoints
- Modify: `app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php:293`
- Modify: `app/Modules/Finance/Queries/GetStudentFeeSummaryQuery.php` — `scholarship_breakdown` key + `mapInvoiceDiscounts`
- Modify: `app/Services/StudentFinancialImportService.php:282-312` — active-adjustment guard
- Modify: FE Financial Plan component consuming `scholarship_breakdown` (FeeTab.vue or dedicated block; settlement lines untouched)

## Implementation Steps

1. Migration + model + factory (campus_id, fingerprint, `percentage|fixed_amount`).
2. `resolveAdjusted()` unit tests: percentage, fixed_amount, zero, clamp vs original, null passthrough.
3. Zero/suspension ledger operation + test (existing discount actually cleared, totals change).
4. Wire all 5 money surfaces; parity test (preview == execute) + batch amount-compare refresh + multi-charge test.
5. Contract interface + DTO + action: re-authorization (campus permission, active users, fingerprint), invariant lock, timing guards, `InstallmentReconciliationException` catch → separate-transaction `finance_review_required`.
6. Import-service guard.
7. `scholarship_breakdown` payload + FE render + regression test on FeeTab summary total (no double count).
8. Reversal path + tests.

## Todo

- [ ] Migration/model/factory
- [ ] resolveAdjusted + tests
- [ ] Zero-op ledger operation
- [ ] 5 surfaces wired, parity + multi-charge + batch-refresh tests
- [ ] Contract + action + guards + reconciliation catch
- [ ] Import guard
- [ ] Breakdown payload + FE
- [ ] Reversal

## Success Criteria

- [ ] Preview == execute for same data across single, batch, major paths.
- [ ] One active adjustment per (student, target semester); duplicate rejected.
- [ ] adjusted ∈ [0, original]; fingerprint mismatch rejected; checker re-authorized at student's campus server-side.
- [ ] Full suspension zeroes an existing discount row (invoice total changes) — proven by test.
- [ ] Invoices with active DNG or payments untouched → `finance_review_required`; reconciliation exception never destroys an approval.
- [ ] `scholarship_breakdown` renders; FeeTab settlement summary total unchanged (regression test).
- [ ] Batch failure of one student leaves that student's adjustment consistent (savepoint test).

## Risk Assessment

- Money-surface drift (FIN-04/07 history) → discovery anchored on discount writes, inventory of 5 surfaces above, parity tests.
- MySQL partial-unique impossible → lockForUpdate invariant in one transaction.
- Award mutated post-approval (import) → fingerprint + import guard.
- DNG state model evolves → guard reads `DngPaymentRequest` statuses via one dedicated query object, single place to update.
