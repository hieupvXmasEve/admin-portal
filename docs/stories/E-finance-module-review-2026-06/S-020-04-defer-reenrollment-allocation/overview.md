# FIN-REV-020-04 - Re-enrollment Charge + Preserved-Cash Allocation (M4)

## Status

implemented

## Lane

high-risk

## Parent

`FIN-REV-020-defer-finance-settlement` (money slice). Child M4 (closing increment).

## Depends On

- `FIN-REV-020-01`, `FIN-REV-020-02`, `FIN-REV-020-03`.

## Current Behavior

Legacy "preserve = skip future charge" made a later re-enrollment look free. After
M1–M3 the original obligation is voided at defer time and the preserved cash sits
unapplied/available, but the re-enrollment billing path is not yet proven.

## Target Behavior

When a previously-deferred student studies the course/term again:

- The re-enrollment is a NEW `course_registration` (linked to the original via
  `original_registration_id` where present).
- Charge generation creates a normal new charge for that registration/term — the
  preserve policy must NOT skip it.
- Existing allocation (`AutoAllocatePaymentsAction`) applies the student's
  available preserved cash to the new charge through the normal flow.

## Acceptance Criteria

- After a PRESERVE defer (cash preserved/available), a re-enrollment registration
  generates a normal charge (no skip, no free ride).
- The preserved available cash is auto-allocated to the new charge via the normal
  allocation path; remaining balance is correct.
- Lineage uses `course_registrations.original_registration_id` where present.
- `finance:audit-invariants` clean; no synthetic credit/debt introduced.

## Non-Goals

- No new allocation engine; reuse `AutoAllocatePaymentsAction`.
- No portal API change.

## Test Plan

| Layer | Cases |
| --- | --- |
| Feature | PRESERVE defer (paid, preserved) → re-enrollment generates normal charge. |
| Feature | Available preserved cash auto-allocates to the new charge. |
| Feature | Preserve policy no longer skips the re-enrollment charge. |
| Platform | `finance:audit-invariants`; Pint; `git diff --check`. |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Defer/DeferReEnrollmentTest.php
./scripts/dev.sh artisan finance:audit-invariants
```

## Implementation Evidence (2026-06-21)

Closing increment landed as a **proof slice** — the re-enrollment behavior was
already correct on the M1–M3 ledger code, so M4 adds **no new production code**;
it locks the composition with tests and records the evidence. Full evidence in
the parent `validation.md` ("M4" subsection).

- New: `tests/Feature/Finance/Defer/DeferReEnrollmentTest.php` (4 feature tests,
  23 assertions, green). Drives the real pipeline cross-semester: pay obligation
  in defer term S1 → FULL PRESERVE defer (M1) → re-enroll in return term S2 with
  `original_registration_id` lineage → `GenerateBatchChargesAction` (S2 bills a
  normal term-2 charge; M2 guard is semester-scoped, does not skip) →
  `AutoAllocatePaymentsAction` (preserved cash settles the new charge).
- Invariant-safe by construction: cross-semester so one invoice per
  `(student, semester)` — no INV-6. Each test asserts `0` offending rows.
- Full Defer suite green: 26 passed (138 assertions). Pint clean;
  `git diff --check` clean. `finance:audit-invariants` dev baseline unchanged
  (INV-6=28, INV-13=1 pre-existing; M4 adds none).
