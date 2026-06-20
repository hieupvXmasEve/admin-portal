# Validation

## Proof Strategy

Prove that defer Finance behavior preserves existing ledger truth:

- item-level defer evidence exists for old and new defer cases;
- deferred original registrations are non-billable in expected-fee logic;
- paid cash is either preserved or consumed according to policy;
- `FORFEIT` and `PARTIAL` never create debt beyond real paid cash;
- future re-enrollment creates a normal charge and uses available cash through
  the normal allocation path;
- DNG-linked cases do not bypass provider lifecycle safety.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Defer policy amount capping: preserve, forfeit, partial, unpaid, overpaid, partial amount greater than paid. |
| Unit | Charge mapping classifier: course-linked charge, full-semester charge, missing charge, voided charge, multi-course ambiguous charge, discount present, live DNG present. |
| Integration | Backfill creates missing `defer_case_items` for full-scope cases idempotently. |
| Integration | Runtime full-scope defer creates item-level records and marks affected registrations `defer`. |
| Integration | Billing exception and Fee Monitor expected-fee queries exclude `registration_status = defer`. |
| Integration | Preserve with paid charge voids/releases source obligation with auto-reallocation disabled and leaves cash available. |
| Integration | Preserve with unpaid charge does not create credit or debt. |
| Integration | Forfeit consumes only paid cash, capped by paid amount, and does not create unpaid debt. |
| Integration | Partial consumes only the paid portion selected by policy and leaves the remainder available. |
| Integration | Future re-enrollment generates a normal charge; existing available cash can allocate normally; preserve policy does not skip the charge. |
| Integration | Live DNG-linked charge blocks direct defer settlement and requires DNG cancellation flow first. |
| E2E | Optional admin smoke only if an implementation slice changes web pages. |
| Platform | Targeted Pint, PHP tests, any touched frontend checks, and `git diff --check`. |
| Performance | Backfill dry-run is bounded and does not load unbounded Finance history per row. |
| Logs/Audit | Finance mutation rows include actor/reason/source evidence; invariant audit shows no new money/DNG regressions. |

## Fixtures

- Student with full-scope defer and multiple course registrations.
- Student with course-scope defer and a charge linked to one registration.
- Student with full-semester tuition charge and full-scope preserve.
- Student with semester-level tuition charge and only some deferred courses
  requiring review.
- Paid preserve case.
- Unpaid preserve case.
- Paid forfeit case.
- Unpaid forfeit case.
- Partial case where paid amount is below, equal to, and above consumed amount.
- Charge with scholarship/discount allocation.
- Charge with active DNG pending/pushed request.
- Already-voided source charge.
- Future re-enrollment registration with `original_registration_id` or the
  current lineage mechanism used in the repo.

## Commands

Expected minimum commands for implementation evidence:

```text
./scripts/dev.sh test tests/Unit/Finance/Defer*
./scripts/dev.sh test tests/Feature/Finance/**/*Defer*
./scripts/dev.sh test tests/Feature/Finance/Reporting/FeeMonitorViewTest.php
./scripts/dev.sh test tests/Feature/Finance/Operations/*Billing*Exception*.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh composer exec pint -- <touched PHP files>
git diff --check
```

If frontend/admin display changes are added:

```text
./scripts/dev.sh npm exec eslint -- <touched Vue/TS files>
./scripts/dev.sh npm exec prettier --check <touched Vue/TS files>
./scripts/dev.sh npm run type-check
```

Record known repo-wide baseline failures separately from story regressions.

## Acceptance Evidence

### Slice 1 — backend-safe foundation (2026-06-21)

Implemented via TDD (RED→GREEN, regression-verified). No new tables/columns; no
money mutation.

**Phase 4 — runtime FULL-scope itemization**
- `DeferCaseService::itemizeFullScope()` (extracted, reused by runtime + backfill):
  a FULL-scope `ACADEMIC_DEFER` now creates `defer_case_items` for the student's
  active enrollments (`pending/registered/confirmed`) in the defer semester and
  marks them `registration_status='defer'`; completed/dropped untouched; idempotent.
- Test: `tests/Feature/Finance/Defer/DeferFullScopeItemizationTest.php` (5 assertions, green).
- Regression: `tests/Feature/Academic/StudentActionEgcDeferBlockTest.php` 7 passed.

**Phase 3 — deferred registrations non-billable (billing-exception layer)**
- `BillingExceptionCollector`: added `'defer'` to `enrolledWithoutActiveChargeBaseQuery`
  and `retakeNoChargeBaseQuery` exclusions. A `registration_status='defer'`
  registration no longer flagged as `missing_charge` / `zero_tuition_waived` /
  `deferred_enrolled` / `retake_no_charge`. Existing `deferred_enrolled` contract
  preserved (it still surfaces *not-yet-marked* `confirmed` regs of deferred students).
  BHYT path already excluded defer.
- Tests: 2 new in `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php`;
  full file 13 passed (46 assertions).
- Scope note: Fee-Monitor tuition expected-fee + charge-generation preview defer
  treatment is semester-level / defer-case-entangled (NOT a clean per-registration
  fix) and is intentionally folded into the gated money phase, not this slice.

**Phase 2 — backfill dry-run + classification report (read-only by default)**
- New: `finance:defer-backfill {--apply} {--semester=} {--cases}` command,
  `ClassifyDeferBackfillCandidatesQuery` (read-only), `BackfillDeferCaseItemsAction`
  (dry-run safe; `--apply` creates items + marks regs defer; idempotent),
  `DeferBackfillClassification` (buckets).
- Itemization buckets: `itemizable` / `already_itemized` / `no_registration`.
  Finance flags: `no_charge` / `charge_voided` / `has_active_charge` /
  `ambiguous_partial`. (Live-DNG + discount detection deferred to money phase,
  where those fixtures exist.)
- Test: `tests/Feature/Finance/Defer/DeferBackfillTest.php` (6 tests, 29 assertions, green).
- Real dev-data dry-run: 24 full-scope cases classified — 2 `itemizable` (6 planned
  items), remainder `no_registration` (needs-review).

**Commands run**
```text
./scripts/dev.sh test tests/Feature/Finance/Defer tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php  # 20 passed (80 assertions)
./scripts/dev.sh test tests/Unit/Finance tests/Feature/Finance/Operations tests/Feature/Finance/Reporting          # 142 passed, 1 failed
./scripts/dev.sh composer exec pint -- <touched files>   # clean
git diff --check                                          # clean
./scripts/dev.sh artisan finance:defer-backfill --cases   # read-only dry-run report
```

Baseline note: the 1 failing test (`BillingExceptionsPaginationTest` — N+1 query-count
assertion) was proven PRE-EXISTING (fails identically on clean `main` via `git stash`),
not a regression from this work.

### Slice 2 — money settlement (FULL-scope PRESERVE/FORFEIT) — M1–M4 done

Decomposed into ordered child stories. M1–M4 implement the FULL-scope
PRESERVE/FORFEIT path via TDD; PARTIAL/COURSE-scope and live-DNG/discount cases
stay report-only (needs-review) until a later increment defines those rules.

- **M1** `FIN-REV-020-01` — `ApplyDeferFinancePolicyAction` (isolated settlement).
- **M2** `FIN-REV-020-02` — defer-aware charge generation; preserve-skip removed.
- **M3** `FIN-REV-020-03` — runtime auto-apply + `--apply-money` backfill.
- **M4** `FIN-REV-020-04` — re-enrollment charge + preserved-cash allocation.

#### M4 — re-enrollment charge + preserved-cash allocation (2026-06-21)

Closing increment. The re-enrollment behavior is already correct on the M1–M3
ledger code, so M4 is a **proof slice**: no new production code, the composition
is locked with feature tests and the evidence recorded.

- New: `tests/Feature/Finance/Defer/DeferReEnrollmentTest.php` — 4 feature tests,
  23 assertions, green. Drives the real pipeline cross-semester:
  1. Pay a full tuition obligation in the defer term S1.
  2. FULL-scope PRESERVE defer (M1 `ApplyDeferFinancePolicyAction`) → obligation
     voided, paid cash preserved/unapplied, S1 reg `defer`, S1 invoice cancelled.
  3. Re-enroll in the return term S2: a fresh `registered` `course_registration`
     with `original_registration_id` → the original (lineage).
  4. `GenerateBatchChargesAction` for S2 → a normal term-2 tuition charge
     (45M). The M2 guard `DeferChargeResolver::isSemesterEnrollmentDeferred` is
     semester-scoped: S1 stays non-billable, S2 bills — preserve no longer skips.
  5. `AutoAllocatePaymentsAction::runForStudents` → the preserved 45M settles the
     new charge through the normal allocation path; remaining balance correct.
- No-free-ride / no synthetic money: exactly one payment ever exists (the
  original); no PRESERVE adjustment charge; preserved cash — not new money —
  funds the re-enrollment.
- Invariant-safe by construction: cross-semester (defer term ≠ return term) so
  one invoice per `(student, semester)` — avoids INV-6 (which counts cancelled
  invoices too, the documented M1 gotcha). Every test asserts `0` offending rows
  across all 15 invariants on clean fixtures.

**Commands run**
```text
./scripts/dev.sh test tests/Feature/Finance/Defer/DeferReEnrollmentTest.php  # 4 passed (23 assertions)
./scripts/dev.sh test tests/Feature/Finance/Defer                            # 26 passed (138 assertions)
./scripts/dev.sh composer exec pint -- --dirty                               # passed
git diff --check                                                             # clean
./scripts/dev.sh artisan finance:audit-invariants                            # dev baseline unchanged: INV-6=28, INV-13=1 (pre-existing; M4 adds none)
```

Test-DB note: `db_test` was in a half-migrated state at session start (missing
`migrations` table); reset via `DROP/CREATE db_test` so `RefreshDatabase` could
migrate fresh. Not a code change.

#### Still gated (later increment)

PARTIAL on a semester-level tuition charge (no accepted split rule), live-DNG
routing through `CancelDngPaymentRequestAction` before void, and
discount/scholarship resolution during settlement.
