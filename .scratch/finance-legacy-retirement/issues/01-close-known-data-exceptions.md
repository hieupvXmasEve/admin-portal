# 01 — Close known Finance data exceptions

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Close the two known data exceptions before schema and migration tooling are retired.

Preserve the legitimate active retake charge `#1184` for student **AUH113129** and attach it to an accepted canonical obligation if that link is still missing. Preserve void charge `#1176` exactly as historical evidence.

Treat **STU69150356** as a generated test fixture. Remove its complete Finance fixture graph transactionally, including its applied test payment, rather than deleting only charge `#1188`. Verify the exact graph again in the target database before mutation so no similarly shaped real record is affected.

## Acceptance criteria

- [x] AUH113129 charge `#1184` remains active for `3,000,000 VND` and has one accepted canonical obligation with matching source identity, amount, currency, and pricing evidence.
- [x] AUH113129 charge `#1176` remains void and its historical evidence is unchanged.
- [x] Re-running the canonical materialization/backfill behavior does not duplicate the obligation or charge for `#1184`.
- [x] STU69150356 is proven to be the approved fixture by exact identity and linked-record checks before deletion.
- [x] The full fixture graph—including payment application, payment, invoice line, invoice, charge, billing account, and student—is removed without leaving an orphan.
- [x] Before/after Finance totals reconcile after excluding the approved fixture amounts; no real student balance changes.
- [x] Finance invariant audit reports no active charge missing a canonical obligation.

## Blocked by

- None — can start immediately.

## Comments

- 2026-07-14: Completed through `finance:close-known-legacy-data-exceptions` on the local target DB. It validated the exact approved records before one transaction: `#1184` is now linked to accepted obligation `#761` (`finance` / `legacy_course_registration` / `legacy:retake_fee:charge:1184`, `3,000,000 VND`, `retake_fee:legacy_backfill`); void charge `#1176` retained status, amount, void timestamp, and reason. The fixture graph `675/236/1188/1471/1203/526/774` is absent after commit. Re-run is idempotent.
- 2026-07-14: Reconciliation evidence: removed only fixture gross `300,000 VND`, invoice total `300,000 VND`, and cash/payment application `100,000 VND`; no legitimate-student row or balance was changed. `finance:audit-invariants --sample` reports `INV-19 = 0`. Existing out-of-scope baseline findings remain `INV-13 #1067` and `INV-18 #1443`.
- Verification: focused Finance exception/invariant tests, full test suite, type-check, and Pint run in Docker.
