# Finance migration rehearsal — 2026-07-12

## Scope

Compare one immutable pre-migration production snapshot with an isolated migrated/backfilled copy before preparing any production command sequence.

Databases:

- `finance_before_test`: immutable baseline; never migrated.
- `finance_after_test`: migration and backfill rehearsal target.
- `db_test`: reserved for PHPUnit; not a production-data comparison baseline.

Local queue and scheduler were stopped before restore. The normal local `asia` database was not reset or used for this comparison.

## Backup evidence

- File: `backups/asia.sql`
- Modified: `2026-07-09 11:09:25 +07`
- Size: `167074243` bytes
- SHA-256: `8bcc137b8f4952cca35da3f1de2b1c93ddae99189bad8abd59caea7fc8d614e1`

Both databases were restored with `reset-local-asia-db.sh --database <name> --import-only --yes`.

Pre-migration identity proof:

- Tables: `154` in each database.
- Migration rows: `260` in each database.
- Schema dump SHA-256: `664e5c5b9b934a57cc1741755fde95ac240737be8a03eb66d7c1bf2c52ab54ce`.
- Logical data dump SHA-256: `5698d9653e89bee25abfd8d07123211e4c3c69ace96da5ace3e141e5274880ce`.
- `CHECKSUM TABLE` differed only for `application_guardians`; row-level ordered dumps were identical. This was an InnoDB/stored-generated-column physical checksum difference, not a logical data difference.

## Pre-migration Finance baseline

| Evidence | Rows | Signed/primary total |
|---|---:|---:|
| `finance_charges` | 997 | 17,083,211,300.00 |
| active `finance_charges` | — | 16,047,263,600.00 |
| void `finance_charges` | — | 1,035,947,700.00 |
| `payments` | 524 | 14,382,079,500.00 |
| `payment_applications` | 760 | 14,018,263,600.00 |
| `invoice_discounts` | 402 | 4,215,400,000.00 |
| `discount_allocations` | 414 | 4,184,500,000.00 |
| `student_invoices.cached_total_amount` | 553 invoices | 14,085,763,600.00 |
| `student_invoices.cached_paid_amount` | 553 invoices | 14,018,263,600.00 |
| `invoice_lines` | 986 | 16,918,211,300.00 |
| `dng_payment_requests` | 259 | 6,133,711,300.00 |
| `dng_payment_request_charges` | 234 | 5,429,211,300.00 |
| `finance_charge_installments` | 687 | 14,186,500,000.00 |
| `dng_webhook_events` | 253 | — |

Immutable ledger hashes were captured for payments, applications, invoices, lines, discounts, DNG requests/pivots/webhooks, and installments.

## Schema migration checkpoint

Only `finance_after_test` was migrated. Migration rows changed from `260` to `275`; `finance_before_test` remained at `260`.

Immediately after schema migration:

- All pre-existing Finance row counts and totals matched the baseline.
- Immutable hashes for `payments`, `payment_applications`, `student_invoices`, `invoice_lines`, `discount_allocations`, `dng_payment_request_charges`, `dng_webhook_events`, and `finance_charge_installments` remained identical.
- New architecture tables were empty before backfill, as expected.

## Backfill checkpoints

### Retake/resit obligation dry-run

Command: `finance:backfill-legacy-retake-resit-obligations --dry-run --report-mismatches`

Initial result:

- Checked: 42.
- Safe creates: 37.
- Mismatches: 0.
- Skipped: 5 (`1156`, `1173`, `1174`, `1176`, `1184`).

Investigation:

- Charge `1156`, student `AUS15054`: void duplicate (`Tạo trùng 2 khoản phí`). DNG request `234` retains a historical pivot to `1156`; its Payment was applied to the valid replacement charge `1170`.
- Charges `1173`, `1174`, `1176`, student `AUH113129`: void intermediate fix/recreate rows without an Academic source. They must remain historical exceptions; no source may be guessed.
- Charge `1184`, student `AUH113129`: active and fully paid by Payment `525`, but the course-retake registration `13` and DNG request `262` header still referenced void charge `1167`. DNG pivot and Payment Application already referenced `1184`.
- `academic:reconcile-legacy-retake-fees --dry-run --report-exceptions` classified `1184` as `duplicate_retake_charge` and intentionally did not guess a repair.
- Approved repair: `academic:repoint-retake-registration-charge AUH113129 1184`. This is expected to change linkage metadata only, not money evidence.

Repoint result:

- Registration `13` changed from void charge `1167` to active charge `1184`.
- Charge `1167` had its obsolete Academic source cleared.
- Charge `1184` now carries source `CourseRetakeRegistration:13`.
- DNG request `262` header changed from charge `1167` to `1184`; its existing pivot was already `1184`.
- Payment `525` and its `3,000,000.00` application to invoice line `1199` were unchanged.
- Hashes for payments, payment applications, invoices, invoice lines, discount allocations, DNG pivots/webhooks, and installments remained identical to the pre-migration baseline.

Retake/resit dry-run after repoint:

- Safe creates: 37.
- Mismatches: 0.
- Skipped historical void rows: `1156`, `1167`, `1173`, `1174`, `1176`.
- The skipped set is now correct: `1184` is safely source-linked, while superseded `1167` replaced it in the historical-orphan set. No fake source will be created for these void duplicates.

Retake/resit apply and parity result:

- Created 37 obligations totaling `177,000,000.00`.
- Linked charge count and distinct obligation count: 37/37.
- Charge versus obligation amount drift: 0.
- Charge versus obligation lifecycle-status drift: 0.
- Idempotency dry-run: created 0, already linked 37, skipped the same five historical void orphans.
- Remaining orphan IDs: `1156`, `1167`, `1173`, `1174`, `1176`.
- Payments, signed payment applications, discounts, invoices, DNG pivots/webhooks, and installment hashes remained unchanged.

### BHYT obligation backfill

- Dry-run: checked 7, safe creates 7, skipped 0, mismatches 0.
- Apply: created 7 obligations totaling `2,211,300.00`.
- Linked charges/distinct obligations: 7/7; orphan count 0; amount/status drift 0.
- Idempotency dry-run: created 0, already linked 7.
- Core money totals remained unchanged.

### Tuition-term obligation backfill

- Dry-run: checked 285, safe creates 285, skipped 0, mismatches 0.
- Apply: created 285 obligations totaling `12,692,000,000.00`.
- Linked charges/distinct obligations: 285/285; orphan count 0; amount/status drift 0.
- Idempotency dry-run: created 0, already linked 285.
- Core money totals remained unchanged.

### EGC level-fee obligation backfill

- Dry-run: checked 428, safe creates 428, skipped 0, mismatches 0.
- Apply: created 428 obligations totaling `6,420,000,000.00`.
- Linked charges/distinct obligations: 428/428; orphan count 0; amount/status drift 0.
- Idempotency dry-run: created 0, already linked 428.
- Core money totals remained unchanged.

### Billing Account backfill

- Dry-run/apply: 234 students already had accounts; 757 obligations were already linked; created/updated/skipped 0.
- Accounts/distinct students: 234/234; duplicate student accounts 0.
- Obligations without account 0; obligation-account versus charge-student drift 0.
- Settlement versions remained 0 before settlement mutations.

### Legacy defer-credit conversion

- Dry-run and apply: no active legacy `defer_credit` charges matched; no-op.

### Legacy voucher discount-entitlement conversion — blocked safely

- Dry-run: checked 111, would convert 111, skipped 0, mismatches 0.
- Apply stopped on the first conversion at invoice `717` with:
  - `settlement_position.payable_line_not_collectible`
  - `settlement_position.missing_currency`
  - `settlement_position.discount_exceeds_gross`
  - `settlement_position.negative_raw_remaining`
- The outer transaction rolled back completely:
  - discount entitlements: 0;
  - linked invoice discounts: 0;
  - active voucher-credit charges: 111 totaling `-555,000,000.00`;
  - no partial conversion remained.

Invoice `717` evidence:

- Two active positive EGC lines: `15,000,000.00` each.
- One active legacy voucher-credit line: `-5,000,000.00` (charge `3`).
- Existing voucher discount/carrier: `5,000,000.00`, allocated to positive line `1`.
- Cached invoice net/paid before conversion: `25,000,000.00` / `25,000,000.00`.

Root cause:

- The converter's pre/post reconciliation uses the legacy signed-ledger snapshot, which correctly treats the negative voucher line as legacy representation.
- During conversion, `VoidFinanceChargeAction` recalculates the invoice cache through the canonical `SettlementPositionReader`.
- The invoice-scope canonical reader currently includes every invoice line, including the just-voided/non-collectible negative legacy line. That line has no Finance Obligation/currency and is evaluated as gross `-5,000,000.00`, producing the blocking issues above.
- Therefore the command cannot complete even though its intended transformation is financially conservative. This is an implementation sequencing/scope defect, not corrupt source data. No further entitlement backfill should run until this path is corrected and covered by a regression test.

Implemented correction and regression evidence:

- Current invoice/billing-account/fee-type scopes now exclude void historical lines and charges; exact payable target sets remain fail-closed.
- The voucher converter voids the legacy negative charge before linking the discount entitlement.
- Voucher migration suppresses intermediate cache recalculation and saves the carrier link quietly because 21 production invoices contain both active voucher and scholarship legacy reductions. Cache rebuild remains a later explicit checkpoint after all reductions are converted.
- Added regression coverage for:
  - void history excluded from current business scopes while exact targets stay invalid;
  - a single legacy voucher carrier;
  - an invoice containing simultaneous voucher and scholarship legacy reductions;
  - paid-line void cache behavior.
- Focused verification: 4 tests / 39 assertions passed; broader focused slice: 8 tests / 87 assertions passed.

Voucher apply after correction:

- Converted: 111; skipped 0; mismatches 0.
- Entitlements: 111 totaling `555,000,000.00`, all fully allocated.
- Linked voucher discounts: 111 totaling `555,000,000.00`.
- Active voucher negative charges: 0; 111 historical rows are void.
- Credit applications introduced: 0; dual discount/credit carriers: 0.
- Idempotency dry-run: no active legacy voucher charges remained.
- Invoice-cache hash, payments, signed payment applications, discount allocations, and DNG totals stayed unchanged.

### Legacy scholarship entitlement conversion

- Production dry-run: checked 124; discount path 124; credit path 0; skipped/mismatches 0.
- The scholarship discount path had the same observer-order defect as voucher conversion. It now uses migration-deferred cache recalculation and quiet carrier linking; the grant-like void path also defers the intermediate recalc until credit applications exist.
- Test fixtures were corrected to materialize positive debit rows through Finance Intake and to load imported legacy discount carriers without firing runtime observers.
- Focused fee-specific regression: 1 test / 18 assertions passed.
- Apply: 124 discount entitlements totaling `1,668,000,000.00`, all fully allocated.
- Linked scholarship discounts: 124 totaling `1,668,000,000.00`.
- Active scholarship negative charges: 0; 124 historical rows are void.
- Credit entitlements/applications: 0/0 for this production snapshot.
- All active negative Finance charges after voucher + scholarship conversion: 0.
- Invoice-cache hash and core cash/DNG totals remained unchanged; idempotency dry-run found no active legacy scholarship charges.

### EGC entitlement tails

- `egc_exempt_credit`: no active legacy rows; dry-run/apply no-op.
- `egc_retake`: converted 8 existing discounts to discount entitlements totaling `60,000,000.00`; skipped/mismatches 0.
- Idempotency dry-run: already converted 8, converted 0.
- Core cash and DNG totals remained unchanged.

### Voided-charge installment cleanup

- Dry-run found one pending installment: `618`, amount `15,000,000.00`, on void EGC charge `1067`.
- The installment had no DNG request and no payment; the charge was voided for the student's Summer EGC module deferral.
- Apply changed installment `618` from `pending` to `cancelled`.
- Idempotency dry-run and live-on-void query both returned 0.

### Invoice cash-cache rebuild — business metadata blocker

Initial full dry-run after obligation/entitlement cleanup:

- Scanned 553; drifted 0; invalid 0; failed 0.

Rebuild and idempotency result:

- Rebuilt 553; invalid/failed 0.
- Post-rebuild dry-run: drifted 0; invalid 0; failed 0.
- All cached money columns remained byte-for-byte equal to the immutable baseline.
- Business-field hash changed because two invoice lifecycle/timestamp fields changed:
  - Invoice `1280`: status `partial → paid`; `cached_paid_at NULL → 2026-07-12 20:28:56`.
  - Invoice `1443`: status `draft → overdue`; `cached_paid_at` stayed null.

Investigation:

- Invoice `1280` has net/cash `36,000,000.00 / 36,000,000.00`, so correcting stale `partial` to `paid` is valid. However its Payment/Application evidence is `2026-04-23 02:42:14`; assigning migration time to `cached_paid_at` falsifies history.
- Invoice `1443` has no cash and remains an explicitly draft invoice. `invoiceStatusFromSettlement()` initializes `draft` but then overwrites it with `overdue` when the due date is past. A cache rebuild must not implicitly issue a draft invoice.

Required correction before production rehearsal continues:

- Preserve `draft` as a lifecycle state during cache rebuild.
- When a cash-settled historical invoice has no cached paid timestamp, reconstruct the timestamp from signed Payment Application business evidence rather than `now()`.
- Add focused regression tests, repair the two rehearsal rows by rerunning the corrected rebuild, and prove the business-field diff is limited to the valid `partial → paid` normalization plus evidence-backed paid timestamp.

Correction and rerun result:

- Added regression coverage proving a past-due draft remains `draft` and a missing historical cash-paid timestamp is reconstructed from Payment Application evidence.
- Corrected `SettlementService` so `draft` is preserved before payment/overdue derivation and `cached_paid_at` never falls back to migration wall time.
- Focused new tests: 2 passed / 3 assertions; complete `InvoiceCacheRebuildTest`: 10 passed / 36 assertions.
- Rehearsal-only rollback before retest: restored invoice `1280` (`status=partial`, `cached_paid_at=NULL`) and invoice `1443` (`status=draft`, `cached_paid_at=NULL`) from `finance_before_test`. No money or ledger field was changed.
- Corrected dry-run before apply: scanned 553; drifted 0; invalid 0; failed 0.
- Corrected apply: rebuilt 553; invalid 0; failed 0. Idempotency dry-run: drifted 0; invalid 0; failed 0.
- All four cached money columns match `finance_before_test` for every invoice (`money_cache_drift=0`).
- Invoice `1443` remains `draft` with no paid timestamp.
- The only status/timestamp difference from the immutable baseline is valid invoice `1280`: `partial → paid`, with `cached_paid_at=2026-04-23 02:42:14`, exactly matching the maximum active completed Payment/Application evidence (`2026-04-23 02:42:14`).

### Active DNG migration inventory — stopped on real provider/data conflicts

Initial dry-run (`finance:dng-cutover --dry-run`):

- Active inspected: 222; safe to cut over: no.
- `amount_mismatch`: 204; `unknown_link`: 18; `exact_link`: 2; `backfill_pending`: 10; unmatched receipt: 0.

Inventory implementation defect found and corrected:

- 203 of 204 amount mismatches were already `paid_invoiced` requests. The inventory compared their original DNG amount against current remaining collectible, which is correctly zero after payment.
- Paid requests now validate the DNG header amount against their explicitly bridged canonical Payment amount; current remaining continues to be the oracle only for unpaid active requests.
- Added a regression where the Payment Application fully settles the exact line. Before correction it was falsely `amount_mismatch`; after correction it is `exact_link`.
- `FinanceDngCutoverCommandTest`: 4 passed / 21 assertions. Combined post-format cache + DNG verification: 14 passed / 57 assertions. Pint completed successfully.

Corrected production-snapshot dry-run:

- Active inspected: 222; safe to cut over: no.
- `exact_link`: 205; `unknown_link`: 16; `backfill_pending`: 10; `amount_mismatch`: 1; unmatched receipt: 0.
- Safe exact backfills are only request IDs `160` and `233`. They remain unapplied because the rehearsal reached real unsafe provider/data rows in the same population.

Real unpaid-provider blocker:

- Request `174`, student DB id `588`, status `pushed_to_dng`, fee type `HP`, DNG amount `30,000,000.00`.
- Exact active targets are charges `1051`/`1052`, invoice lines `1065`/`1066`, gross `15,000,000.00` each.
- Line `1065` now has a valid scholarship discount `7,500,000.00`; canonical remaining is `7,500,000.00`. Line `1066` remains `15,000,000.00`.
- Canonical total collectible is therefore `22,500,000.00`, while DNG still holds `30,000,000.00`. An already-pushed request must not be silently edited; provider outcome/lookup and confirmed cancellation or reconciliation is required.

Unknown-link population requiring explicit resolution:

- Unpaid pushed requests with only void targets: `164`, `176`.
- Unpaid pushed requests with no charge/installment target evidence: `179`, `181`, `182`, `183`, `186`.
- Paid requests whose original targets were fully voided and Payment Applications fully reversed (cash is now unapplied/surplus): `65`, `159`, `169`, `230`, `255`.
- Paid requests with one active allocation and one void/reversed allocation (half the cash is now surplus): `171`, `172`.
- Paid request `196` has a stale void header charge `1185`; its Payment history was moved back to active line `995`/charge `981` after reversal/reapplication.
- Paid request `234` has conflicting exact evidence: header and Payment Application point to active replacement charge `1170`, while the legacy DNG pivot still points to void duplicate `1156`.

Stop condition:

- No DNG backfill was applied and no provider call was made.
- Continue only after confirming provider handling for request `174` and the seven unresolved pushed requests, plus the intended surplus/target-repair disposition for the nine paid historical requests.

#### Provider lookup attempt for the eight unsafe pushed requests

Local request evidence:

| DNG request | Student code | ItemId | Amount | Local target issue |
|---:|---|---|---:|---|
| 164 | AUH16172 | `AUH16172_1776834166323` | 30,000,000.00 | linked only to void charges 1086/1087 |
| 174 | AUH110281 | `AUH110281_1776834433807` | 30,000,000.00 | active targets now collectible for only 22,500,000.00 |
| 176 | AUH16610 | `AUH16610_1776834487862` | 15,000,000.00 | linked only to void charge 1092 |
| 179 | AUH15011 | `AUH15011_1776834566930` | 15,000,000.00 | no charge/installment target evidence |
| 181 | AUH111165 | `AUH111165_1776834610647` | 15,000,000.00 | no charge/installment target evidence |
| 182 | AUH110042 | `AUH110042_1776834629903` | 15,000,000.00 | no charge/installment target evidence |
| 183 | AUH10710 | `AUH10710_1776834649209` | 15,000,000.00 | no charge/installment target evidence |
| 186 | AUH120339 | `AUH120339_1776852154892` | 30,000,000.00 | no charge/installment target evidence |

- All eight were created/pushed on `2026-04-22`, remain `pushed_to_dng`, have a local DNG transaction ID, no `paid_at`, no canonical Payment, and zero local webhook events.
- Runtime DNG endpoint is `https://googleauthensite02.fpt.edu.vn:90`; credentials are configured but were not printed or stored in this note.
- The only available read API is `checkPaidOfDay(campus, date)`. The provider contract explicitly states this is daily positive-payment evidence, not an item-level terminal-status lookup.
- Read-only lookups were run for `FAUHN` from `2026-04-22` through `2026-05-02`, plus samples `2026-05-10` and `2026-07-12`. No response matched any of the eight exact ItemIds.
- Responses for sampled dates `2026-04-22` and `2026-04-30` returned the same unrelated `1,000,000` record fingerprint, with no match in the restored production DNG table; later sample dates returned no rows. This endpoint/result cannot establish that the eight production debts are unpaid, cancelled, or terminal.
- Per the provider contract, absence from daily payment results is not permission to release a hold, cancel locally, change amount, or create a replacement request. A dated staff-to-DNG confirmation or a provider item-level terminal lookup is required for each campus + ItemId.
- No cancellation, replacement push, reconciliation write, or local DNG metadata backfill was performed.

#### Semester and defer/dropout verification for the eight pushed requests

ID namespace clarification:

- `DNG request #174` belongs to `AUH110281` and is the `30,000,000.00 → 22,500,000.00` mismatch described below.
- `finance_charge #174` belongs to `AUH15041`; it is a FALL2025 `voucher_credit` row for `-5,000,000.00`. AUH15041's DNG request is `#185`, not `#174`.
- AUH15041 invoice `785` is `30,000,000.00` gross less one `5,000,000.00` voucher allocation = `25,000,000.00`, fully paid by `25,000,000.00` cash. Before and after migration its cached total/paid remain exactly `25,000,000.00`; conversion only changes the legacy negative charge/line from active to void while retaining the single canonical discount allocation. There is no `22,500,000.00` position or duplicate `2,500,000.00` reduction for AUH15041.

- All eight students are currently `deferred` or `dropout`, confirmed by `student_action_logs`.
- The premise that all eight requests/charges belong to `SUMMER2026` is false:
  - SUMMER2026 (`semester_id=3`): DNG requests `164`, `174`, `176`, `186`.
  - SPRING2026 (`semester_id=2`): DNG requests `179`, `181`, `182`, `183`.
- Request and charge semester/status/amount fields have zero differences between `finance_before_test` and `finance_after_test`; this is restored production state, not migration drift.

Charge state by request:

- `164` dropout in SUMMER2026: charges `1086`, `1087` are already void; no net Payment Application.
- `174` dropout in SUMMER2026: charges `1051`, `1052` remain active; no cash. Gross is `30,000,000.00`, but a `7,500,000.00` scholarship makes current collectible `22,500,000.00` while DNG remains pushed for `30,000,000.00`.
- `176` deferred from SUMMER2026 to FALL2026: charge `1092` is already void; no cash.
- `186` deferred from SUMMER2026 to FALL2026: charges `1064`, `1065` are already void; no cash. The DNG request has no exact charge link.
- `179`, `181`, `182`, `183` deferred from SPRING2026 to FALL2026: each has one `15,000,000.00` SPRING2026 EGC charge already void (`1014`, `1017`, `1011`, `1019` respectively), but its DNG request has no exact charge/installment link. Amount/semester similarity is not sufficient evidence to invent that link.

Deletion decision:

- Do not physically delete `finance_charges`, `invoice_lines`, `dng_payment_requests`, pivots, or provider identities. They are immutable audit/provider-correlation evidence and are required to capture a late receipt safely.
- Seven students' candidate semester charges are already void, so there is no collectible local charge left to delete.
- Request `174` needs local admin DNG cancellation before its two active charges can be voided. Hard deletion remains forbidden because provider identity/evidence is required to reconcile a late payment.

#### Approved local rehearsal: remove AUH110281 SUMMER2026 fees and DNG #174

Scope and safety boundary:

- Executed only against `finance_after_test`.
- The first simulation used `Http::fake()`/`Queue::fake()` to exercise the older provider workflow. The 2026-07-13 correction removed all artifacts from that simulation.
- The lifecycle decision is local admin cancellation; production does not call DNG for campus `FAUHN`, ItemId `AUH110281_1776834433807`. The ItemId remains preserved for any late receipt.

Execution result:

- DNG `174` final state is local `cancelled`; original `ItemId`, push response, and exact pivots `191/192` remain as provider-correlation evidence. No cancellation payload/response remains.
- Rehearsal-only Finance Cancellation Operations `1/2`, their outbox rows, replacement DNG `263`, and replacement pivot `235` were deleted because this lifecycle path does not use provider orchestration.
- Obligations `698`/`699`: `accepted → voided`.
- Charges `1051`/`1052` and invoice lines `1065`/`1066`: `active → void`.
- Invoice `1376`: `cancelled`; all cached money columns are `0.00`, `cached_paid_at=NULL`.
- Discount allocation on lines `1065`/`1066`: released to `0.00`; no cash existed on these lines.
- Global Payment Application count/signed sum remained `760 / 14,018,263,600.00`.
- AUH110281 Billing Account remains canonically valid and fully settled across older semesters: gross `60,000,000.00`, discount `5,000,000.00`, cash `55,000,000.00`, remaining `0.00`. SUMMER2026 contributes no current payable amount.
- DNG `174` is terminal and excluded from active cutover inventory; replacement `263` no longer exists.
- Finance invariants after simulation: every money/DNG invariant passed. The audit still showed 28 findings under the then-current, later-retired INV-6 definition that incorrectly treated multiple invoices per student/semester as invalid.

Cleanup is complete: no fake provider evidence, cancellation operation, completion outbox, or replacement request from this simulation remains. Production replay uses the local admin lifecycle-cancellation path and does not call DNG.

#### DNG exact metadata backfill and remaining paid-history blockers

Exact unpaid backfill:

- `finance:dng-cutover --backfill` wrote only safe request IDs `160` and `233`; no provider call was made.
- `160`: Billing Account `167`, exact lines `1107`/`1108`, captured `15,000,000.00` each, deterministic target fingerprint and active slot `dng:FAUHN:167:HP`.
- `233`: Billing Account `157`, exact line `1168`, captured `15,000,000.00`, deterministic target fingerprint and active slot `dng:FAUHN:157:HP`.
- Idempotency rerun backfilled 0 and retained exactly one target row per identity.

Approved defer/dropout cancellation rehearsal:

- Requests `164`, `176`, `179`, `181`, `182`, `183`, `186` all had valid Billing Accounts with canonical remaining `0.00`, no Payment, and zero webhook events.
- Each was moved `pushed_to_dng → cancel_pushed_to_dng` using `Http::fake()` with `data.rehearsal=true`; no provider HTTP request was made.
- One rehearsal `cancel_confirmed` receipt exception was recorded per request. Charges were already void and no money ledger row changed.
- Active cutover population fell from 221 to 214; remaining pushed requests are only exact `160` and `233`.

Paid historical-target inventory correction:

- Paid requests were still falsely `unknown_link` when their exact header/pivot target had later been voided. Current payable validation is correct for unpaid collection, but a paid migration inventory must retain immutable historical target evidence and validate the header amount against the bridged Payment.
- Added regressions proving a void historical paid target remains exact and a paid request whose header/pivot identify different charges remains fail-closed.
- Corrected inventory result: `exact_link=212`, `unknown_link=2`, `amount_mismatch=0`, `paid_unbridged=0`, unmatched receipt `0`.
- DNG cutover tests: 6 passed / 24 assertions. Combined DNG + invoice-cache suite after Pint: 16 passed / 60 assertions.

Two genuine data blockers remain:

- DNG `169`, AUH15022, paid `30,000,000.00`, canonical Payment `421`: no DNG header, pivot, installment, or reservation target exists. Payment Applications `610`/`611` were created by generic `PaymentService::autoAllocatePayment` at receipt time onto SUMMER2026 charges `1072`/`1073`, then fully reversed when those charges were voided. Auto-allocation is allocation truth, not proof of the provider collection target, so the inventory must not invent a DNG target from it.
- DNG `234`, AUS15054, paid `3,000,000.00`: header and the only net Payment Application point to active replacement charge `1170`/line `1185`; legacy pivot `223` still points to void duplicate charge `1156`/line `1171`. This is the only header-versus-pivot conflict among all 212 paid requests. Repairing pivot `223` from `1156` to `1170` is strongly evidenced but is a real data mutation and remains unapplied pending confirmation.

Confirmed historical-target cleanup:

- User confirmed that only correct historical links should be retained/backfilled and stale legacy links should be removed or corrected without changing money.
- DNG `234`: pivot `223` updated in place from stale void charge `1156` to correct active charge `1170`; amount stayed `3,000,000.00`.
- DNG `169`: added exact confirmed historical pivots to void charges `1072` and `1073`, `15,000,000.00` each. Payment `421` remains fully unapplied/Còn dư after the existing reversals; no cash was reapplied.
- Payments stayed `524 / 14,382,079,500.00`; Payment Applications stayed `760 / 14,018,263,600.00`; invoice cached totals stayed `14,063,263,600.00 / 14,018,263,600.00`.
- DNG pivot rows increased by the two required historical targets only (`235 → 237`); total pivot amount increased by their confirmed `30,000,000.00`. The stale `234` row was corrected, not duplicated.
- Final DNG cutover inventory: active `214`; `exact_link=214`; unmatched receipt `0`; all other classifications `0`; `safe_to_cutover=true`.
- INV-12, INV-14, and INV-15 all remain 0, proving Payment/DNG amount and pivot/installment consistency.

### Legacy INV-6 multi-invoice investigation — superseded

- Ran the legacy `finance:export-duplicate-finance-data` read-only against `finance_after_test`; it found 28 multi-invoice `(student_id, semester_id)` groups and INV-11 found 0 duplicate webhook hashes.
- The 28 groups contain 60 invoice rows (32 rows above a one-invoice-per-student/semester constraint).
- 22 groups contain multiple positive invoices; six include cancelled/zero historical invoices.
- Combined evidence in the groups: cached total `1,101,947,700.00`, cached paid `1,086,947,700.00`.
- Most positive pairs are not duplicate charges: they hold distinct obligations/fee types such as tuition plus retake, exam-resit, or BHYT, with separate Payment Applications and DNG references. Bulk deleting the “extra” invoice would delete or orphan valid paid evidence.
- No active charge is duplicated across active invoice lines. The only repeated charge id is `1149`, represented by historical void line `1163` on invoice `1439` and its single active replacement line `1167` on invoice `1443`; INV-3 remains 0.
- Product clarification: multiple invoices per student/semester are valid when fee streams issue separate statements. These 28 groups are not a consolidation backlog and require no data mutation merely because their semester matches.
- No multi-invoice data was mutated. The full temporary export was used only to expose the false assumption and was removed after analysis.

INV-6 groups by student code and business pattern:

- Tuition + exam-resit, SPRING2026: `AUH113020` (invoices 1182/1425), `AUH19784` (1184/1433), `AUH18290` (1185/1434), `AUS15066` (1141/1422), `AUS10743` (1206/1415), `AUS123298` (1145/1420).
- Tuition + retake, SUMMER2026: `AUH17998` (1275/1447), `AUH14980` (1307/1448), `AUH14977` (1309/1449), `AUH14972` (1311/1450), `AUH14968` (1312/1451), `AUH14965` (1313/1452), `AUH13625` (1320/1453), `AUS15054` (1232/1446), `AUH115102` (1332/1454), `AUH116717` (1340/1456), `AUH113129` (1334/1457/1458/1459/1467; three historical cancelled shells plus two paid invoices).
- Tuition + BHYT, SUMMER2026: `AUS10781` (1218/1463, BHYT cancelled), `AUS15014` (1242/1466), `AUS17733` (1256/1461, BHYT cancelled), `AUS12899` (1259/1464), `AUS11621` (1261/1462).
- Separate EGC + tuition invoices, FALL2025: `AUS10781` (831/1088), `AUH116583` (910/946), `AUH115952` (911/947).
- EGC lifecycle/history in the same semester: `AUH111841` SPRING2026 (1343/1441/1442), `AUH111841` SUMMER2026 (1439/1443), `AUH14956` SPRING2026 (1348/1414). These include zero/void historical shells and active replacement invoices.

This is 28 student+semester groups, not 28 students and not 28 proven overcharges. Twenty-six distinct student codes are involved because `AUH111841` and `AUS10781` each appear in two semesters.

Invoice-cardinality architecture correction:

- Stable code `INV-6` is retired/pass-only as of 2026-07-12; its historical `duplicate_invoice` issue mapping is retained so stored audit evidence is not reinterpreted.
- Multiple invoices for one student/semester now correctly produce 0 findings under the retired INV-6 rule.
- New `INV-17` is CRITICAL when an invoice line references a Finance charge belonging to another student or semester; it returns the affected invoice id for audit navigation and maps to `invoice_scope_mismatch`.
- The legacy-named export command now labels same-semester invoice groups as allowed informational inventory; only duplicate webhook hashes retain INV-11 blocker semantics.
- The current rule is recorded in `docs/system-architecture.md`; every historical file under `docs/stories/`, `docs/superpowers/plans/`, and the Finance module review that mentions the old INV-6/duplicate-invoice rule now carries a direct superseded notice while retaining its dated evidence. Key origins include:
  - `docs/features/finance/finance-module-review-2026-06-13.md`;
  - `docs/stories/E-finance-module-review-2026-06/S-003-data-guards-and-constraints/{overview,design,execplan,validation}.md`;
  - `docs/superpowers/plans/2026-06-15-finance-audit-workspace.md` and `2026-06-15-finance-office-cockpit.md`.

## Consolidated final read-only gates after INV-6 retirement

Database-targeting note:

- `DB_DATABASE=finance_after_test ./scripts/dev.sh artisan ...` does **not** pass the host override through `docker compose exec`; it accidentally audited the untouched local `asia` DB and reported its known invoice `1471` / installment `1067` findings. The command was read-only and changed nothing.
- Rehearsal commands must inject the database inside the app container, for example `docker compose ... exec -e DB_DATABASE=finance_after_test app php artisan ...`. `config:show` confirmed the effective database before continuing.

Final invariant and cutover gates on the correctly targeted rehearsal database:

- `finance:audit-invariants --sample`: `997` charges, `524` payments, `553` invoices; every invariant `INV-1..INV-17` returned `0`.
- `finance:dng-cutover`: active inspected `214`; `exact_link=214`; unmatched receipt `0`; `safe_to_cutover=yes`.
- Invoice snapshot dry-run: scanned `553`; drifted/rebuilt/invalid/failed all `0`.

Consolidated idempotency dry-runs:

- retake/resit obligations: created `0`, already linked `37`, mismatch `0`; the same five approved historical void orphans remain (`1156`, `1167`, `1173`, `1174`, `1176`);
- BHYT: `7/7` already linked; tuition term: `285/285`; EGC level fee: `428/428`; all with mismatch `0`;
- Billing Accounts: `234/234` students already provisioned and `757/757` obligations already linked;
- defer credit, voucher credit, scholarship credit, and EGC exempt credit: no active legacy carrier remains;
- EGC retake discounts: `8/8` already converted, mismatch `0`;
- voided-charge installments: `0` remaining to cancel.

Cross-schema money comparison (`finance_before_test` → `finance_after_test`):

- preserved exactly: charge row count and all-row amount (`997`, `17,083,211,300.00`); Payment count/amount (`524`, `14,382,079,500.00`); Payment Application count/signed amount (`760`, `14,018,263,600.00`); invoice-line count/all-row amount (`986`, `16,918,211,300.00`); invoice row count and cached cash (`553`, `14,018,263,600.00`);
- active-charge signed amount increased `2,193,000,000.00`, exactly explained by retiring `124` scholarship carriers (`1,668,000,000.00`) and `111` voucher carriers (`555,000,000.00`) from active negative charges, minus the approved AUH110281 SUMMER2026 void (`30,000,000.00`);
- invoice cached collectible decreased `22,500,000.00`, exactly the approved AUH110281 SUMMER2026 `30,000,000.00` gross less released `7,500,000.00` scholarship;
- discount allocation signed amount decreased `7,500,000.00`; the three new rows are AUH110281 line `1065` release `-7,500,000.00`, line `1066` temporary allocation `+7,500,000.00`, then line `1066` release `-7,500,000.00`;
- DNG request rows/amount increased by replacement `263` only (`+1`, `+15,000,000.00`), which is already cancelled;
- DNG pivot rows/amount increased by three exact audit links (`+3`, `+45,000,000.00`): replacement `263 → charge 1052` and confirmed DNG `169` historical targets `1072/1073` for AUH15022. The DNG `234` stale pivot was corrected in place, not duplicated.

Conclusion: there is no unexplained money delta. Cash receipt and application ledgers are byte-equivalent in count and signed totals; every non-zero delta is an approved canonicalization, AUH110281 cancellation, or DNG audit-link change recorded above.

### Deferred-case backfill stop condition

Read-only command:

- `finance:defer-backfill --cases` found 30 full-scope cases: one itemizable, two already itemized, and 27 `no_registration` cases requiring review. No rows or money were written.
- The classifier only treats `registered`/`confirmed` registrations as itemizable. Fourteen review cases have only `completed` registrations; thirteen have no current registration row in the defer semester. It therefore cannot safely invent course-level evidence from the EGC charge or semester alone.

Auto-safe non-money itemization still pending confirmation:

- Case `21`, `AUH115952`, `SUMMER2026`, `FORFEIT`: five confirmed registrations (`2250`, `2345`, `2380`, `2541`, `2603`), planned five defer-case items; charge `995` is already void. `--apply` would create the five item rows and mark those registrations defer without mutating money.

The 27 review cases split into these business groups:

- `PARTIAL`, paid and still active — unsupported automatic money policy: case `1`, `AUH14999`, `SPRING2026`, charge `1007`, invoice `1347`, gross/cash/remaining `15,000,000 / 15,000,000 / 0`; recorded preserve amount `15,000,000`.
- `PRESERVE`, paid and still active — applying the policy would void the charge and leave cash as Còn dư (`15,000,000` each, total `90,000,000`): cases `2 AUH14608`, `5 AUH16174`, `7 AUH116124`, `8 AUH115895`, `9 AUH117032` in `SPRING2026`, and case `30 AUH14944` in `SUMMER2026` (DNG `232`, paid).
- `FORFEIT`, paid and still active — applying the policy would void the original charge and create/allocate an equal adjustment, consuming the released cash with zero remaining (`15,000,000` each, total `90,000,000`): cases `3 AUH112917`, `4 AUH19035`, `6 AUH15442` in `SPRING2026`, and cases `27 AUH14998` (DNG `171`), `28 AUH18865` (DNG `175`), `29 AUH11254` (DNG `180`) in `SUMMER2026`.
- `FORFEIT`, unpaid and still represented by live DNG — close DNG locally in admin before voiding: case `17 AUH16667`, `SUMMER2026`, charges `1093/1094`, invoice `1399`, DNG `160`, `30,000,000`; case `22 AUH111841`, `SUMMER2026`, active charge `1153`, invoice `1443`, DNG `233`, `15,000,000` (historical void charge `1149`/invoice `1439` remains audit evidence).
- No active charge, so money settlement would be noop; retain as historical needs-review/no-registration cases: `10 AUH120339`, `11 AUH16610`, `12 AUS115249`, `13 AUS17847`, `14 AUS17214`, `15 AUS15092`, `16 AUS18578`, `18 AUH15022`, `19 AUH19118`, `20 AUS15437`, `23 AUS121787`, `24 AUS120583` (all `SUMMER2026`). Their charge/DNG histories include already-void charges, cancelled pushes, or paid receipts now represented as historical/surplus evidence.

Do not run `finance:defer-backfill --apply-money` as a bulk command until the PARTIAL policy, six PRESERVE cash dispositions, six FORFEIT consumptions, and two live-DNG cancellations are confirmed. The action is intentionally money-mutating and would produce materially different ledger histories across those groups.

### Architecture guard correction during final gates

- `SettlementBypassArchitectureTest` initially flagged `ReverseCreditApplicationAction` even though its `CreditApplication` reversal write is inside `SettlementMutationGuard::handle()`.
- Root cause: the scanner recognized only legacy allowlisted paths; it did not distinguish a write inside the mutation-guard callback from an unguarded write elsewhere in the same file.
- The scanner now tokenizes callback boundaries and exempts only direct money writes whose source offset is inside `settlementMutationGuard->handle(...)`. A red-proof verifies that a write after the callback still fails the guarded-range check. No new legacy allowlist entry was added.
- Verification after Pint: `SettlementBypassArchitectureTest` passed `3` tests / `75` assertions; `git diff --check` passed.

### Confirmed defer corrections: AUH115952 and AUH14999

Read-only ledger proof before mutation:

- `AUH115952`, defer case `21`, `SUMMER2026`: the stored policy was incorrectly `FORFEIT`. Tuition charge `995` / line `1009` was already void. Payment `439` is `40,500,000.00`; its application `633 +40,500,000.00` was exactly reversed by row `737 -40,500,000.00`, leaving the full payment unapplied/Còn dư. Scholarship allocation `393 +4,500,000.00` was exactly released by row `414 -4,500,000.00`. DNG `65` remains immutable paid evidence for `40,500,000.00`.
- `AUH14999`, defer case `1`, `SPRING2026`: `PARTIAL` with `preserve_amount=15,000,000.00` is correct. Payment `297` is `30,000,000.00`; net applications are `15,000,000.00` to active charge `1007`/invoice `1347` for the completed level, leaving exactly `15,000,000.00` unapplied. No money or case field was changed.

Approved AUH115952 apply:

- `DeferCaseService::updateDeferCase()` changed case `21` from `FORFEIT` to `PRESERVE` and recorded the exact preserved cash `40,500,000.00`.
- `finance:defer-backfill --apply --semester=3` created five defer-case items and changed registrations `2250`, `2345`, `2380`, `2541`, `2603` from `confirmed` to `defer`; no money row changed.
- Idempotency dry-run: itemizable `0`, already itemized `3`, planned items `0` for SUMMER2026.
- Charge `995` and line `1009` remain void. Payment `439` remains `40,500,000.00` fully unapplied. Invoice `1337` cache remains `0.00 / 0.00`.

Invoice lifecycle defect discovered:

- Invoice `1337` had all lines void and cache `0.00 / 0.00` but retained stale status `paid`. This conflicts with the confirmed cancelled SUMMER2026 fee and with `VoidFinanceChargeAction`, which sets an invoice with no active lines to `cancelled`.
- Invoice `1337` was corrected to `cancelled` with `cached_paid_at=NULL`; no money field changed.
- Post-change SUMMER snapshot dry-run: scanned `213`, drifted/invalid/failed `0`. Finance invariants `INV-1..INV-17` remain all `0`.
- Global read-only inventory found 22 additional invoices with the same stale lifecycle pattern (all lines void, zero cache, status `paid`):
  - SPRING2026: `1348 AUH14956`, `1350 AUH110042`, `1351 AUH15011`, `1354 AUH111165`, `1356 AUH10710`, `1441 AUH111841`, `1468 AUH12910`;
  - SUMMER2026: `1357 AUS10430`, `1364 AUS120583`, `1368 AUS15092`, `1370 AUS15437`, `1371 AUS17214`, `1374 AUS18578`, `1379 AUH114359`, `1383 AUH120339`, `1386 AUH15017`, `1387 AUH15022`, `1400 AUH18676`, `1403 AUH19317`, `1404 AUH19551`, `1439 AUH111841`, `1465 AUS17847`.
- Current `finance:audit-invariants` does not include invoice lifecycle-versus-line-state integrity, and snapshot dry-run compares only cached money columns, so both commands can report clean while this metadata is stale. Do not bulk update these 22 rows until their void history is confirmed; before production, add a read-only lifecycle inventory/repair gate or extend the existing rebuild tooling with explicit evidence and idempotency.

Lifecycle verification and approved repair:

- Joined each stale invoice to `students.status`, the latest student action, every invoice line, and each charge `void_reason`.
- Fifteen invoices belong to students whose current lifecycle is `deferred`; every invoice has zero active lines and zero cached total/cash, and the charge reasons are defer/bảo lưu or an already-confirmed adjustment. They were corrected from `paid` to `cancelled`, with `cached_paid_at=NULL` and no money-field change: `1348 AUH14956`, `1350 AUH110042`, `1351 AUH15011`, `1354 AUH111165`, `1356 AUH10710`, `1364 AUS120583`, `1368 AUS15092`, `1370 AUS15437`, `1371 AUS17214`, `1374 AUS18578`, `1383 AUH120339`, `1387 AUH15022`, `1439/1441 AUH111841`, `1465 AUS17847`.
- Idempotency query after apply: remaining stale all-void/zero-cache invoices for `deferred` or `dropout` students = `0`.
- Seven invoices were deliberately not changed because their current lifecycle is not defer/dropout:
  - `pending_course_opening`: `1357 AUS10430`, `1379 AUH114359`, `1386 AUH15017`, `1400 AUH18676`, `1403 AUH19317`, `1404 AUH19551`;
  - `intake_course`: `1468 AUH12910`, whose only charge reason is `exam_resit_cancelled_paid_no_refund`.
- Post-repair full snapshot dry-run: scanned `553`, drifted/rebuilt/invalid/failed all `0`. All `INV-1..INV-17` remain `0`; DNG inventory remains `214/214 exact_link`, unmatched receipt `0`, `safe_to_cutover=yes`.

Approved waiting-course and exam-resit lifecycle repair:

- The six `pending_course_opening` invoices were confirmed cancelled because their fees were voided while the student waits for a class. Invoices `1357 AUS10430`, `1379 AUH114359`, `1386 AUH15017`, `1400 AUH18676`, `1403 AUH19317`, and `1404 AUH19551` changed `paid → cancelled`; cache stayed `0.00 / 0.00` and no money row changed.
- `AUH12910` invoice `1468`, charge `1185`, `SPRING2026` exam resit was confirmed cancelled with paid cash preserved. Read-only inspection found the total payer balance was correct but receipt identity was wrong:
  - DNG `196` / Payment `374` is the `3,000,000.00` exam-resit receipt, but historical auto-allocation row `756` had moved it onto tuition line `995`;
  - DNG `76` / Payment `405` is the `36,000,000.00` tuition receipt, but only `33,000,000.00` was applied to tuition and `3,000,000.00` remained unapplied.
- A single transaction preserved ledger history without changing totals:
  - appended Payment Application `774`, reversal `-3,000,000.00`, moving Payment `374` out of tuition with source `manual_preserve_exam_resit`, charge ref `1185`;
  - appended Payment Application `775`, allocation `+3,000,000.00`, moving the remaining Payment `405` onto tuition charge `981`/line `995` through `AllocatePaymentAction`;
  - changed invoice `1468` from `paid` to `cancelled`, cache staying `0.00 / 0.00`.
- Result: Payment `374` is fully unapplied/Còn dư `3,000,000.00` as the preserved exam-resit receipt; Payment `405` is fully applied `36,000,000.00` to tuition. Payment/Application signed total is unchanged; only two compensating append-only ledger rows were added.
- Global lifecycle idempotency: remaining invoices with at least one line, no active lines, and status `paid` = `0`.
- Final verification after this repair: snapshot scanned `553`, drifted/rebuilt/invalid/failed `0`; all `INV-1..INV-17 = 0`; DNG inventory `214/214 exact_link`, unmatched receipt `0`, `safe_to_cutover=yes`; Payment Application rows `762` with unchanged signed sum `14,018,263,600.00`.

### Deferred-student live DNG cancellation: AUH16667 and AUH111841

Confirmed rule: when a student is deferred/dropout and still has an unpaid live DNG request, cancel collection before voiding charges. Legacy behavior that voided local charge state without cancelling DNG is invalid.

Preconditions:

- `AUH16667`, defer case `17`, `FORFEIT`: DNG `160` pushed `30,000,000.00`, exact pivots to active charges `1093/1094` (`15,000,000.00` each), installments `636/637` pending, no Payment/discount evidence.
- `AUH111841`, defer case `22`, `FORFEIT`: DNG `233` pushed `15,000,000.00`, exact pivot to active charge `1153`, installment `670` pending, no Payment/discount evidence.
- Both students had current lifecycle `deferred`. The installments did not carry `dng_payment_request_id`; linkage existed through DNG pivots/reservation targets, exposing the old guard's installment-only blind spot.

Approved rehearsal workflow:

- The initial rehearsal used `Http::fake()` provider-cancellation evidence. The 2026-07-13 business correction superseded and removed that fake evidence.
- DNG `160/233`: final rehearsal state is local `pushed_to_dng → cancelled`; no provider payload/response and no `cancel_confirmed` exception remains.
- Only after terminal collection cancellation, defer policy applied:
  - case `17`: charges `1093/1094` and installments `636/637` became void/cancelled; invoice `1399` cancelled; released/consumed cash `0/0`; no adjustment;
  - case `22`: charge `1153` and installment `670` became void/cancelled. Stale line `1167` was still active on already-void charge `1149`; it carried no cash/discount/credit, so it was voided and invoice `1443` cancelled.
- Global inventory after cleanup: no active invoice line points to a void charge.

Code hardening:

- `ApplyDeferFinancePolicyAction` now detects live DNG through installment, direct request header, charge pivot, and reservation target, closes each request locally, then voids linked charges atomically. Focused tests cover every linkage shape and prove no DNG client call.
- DNG cutover adds `student_lifecycle_conflict` for unpaid live requests owned by `deferred`, `dropout`, or `dropout_transfer` students; action is `cancel_collection` and cutover is unsafe until resolved. This ensures a fresh production dry-run surfaces records like `160/233` even when linkage/amount are exact.
- Added stable `INV-18`, CRITICAL: active invoice line on void charge. It samples invoice ids and maps to `settlement_position.finance_invariant.active_line_on_void_charge`.
- Verification after local lifecycle cancellation, cutover regression, and Academic lifecycle-reader boundary were added: focused defer/DNG/receipt/lifecycle/integrity/architecture suite `101 passed / 575 assertions`; Pint and `git diff --check` passed. Full `./scripts/dev.sh test --compact` still exits `255` without diagnostics in the local container. Rehearsal audit: all `INV-1..INV-18 = 0`; DNG active population `212`, `exact_link=212`, unmatched receipt `0`, `safe_to_cutover=yes`. Payment rows/totals remain `524 / 14,382,079,500.00`; Payment Application rows/signed total remain `762 / 14,018,263,600.00`.
- Finance obtains lifecycle statuses in batches through `App\Shared\Contracts\Academic\StudentLifecycleStatusReader`; it no longer reads Academic-owned `Student.status` directly while building the DNG cutover inventory.

2026-07-13 correction and cleanup:

- Lifecycle defer/dropout DNG cancellation is admin-local and does not use the provider cancellation operation. Ten rehearsal-mutated requests `160, 164, 174, 176, 179, 181, 182, 183, 186, 233` were normalized from `cancel_pushed_to_dng` to `cancelled`; all retained original push/provider identity and had no Payment. Ten rehearsal-only `cancel_confirmed` exception rows plus fake cancel payload/response were deleted.

### Defer money replay stop: cases 25 and 26

Fresh `finance:defer-backfill --cases` dry-run after lifecycle cleanup found `30` full-scope cases, `0` itemizable, `3` already itemized, and `27` no-registration reviews. No rows or money were written. Bulk `--apply-money` remains blocked by two already-itemized active-charge cases that were absent from the earlier paid-case grouping:

- Case `25`, `AUH120849`, `SUMMER2026`, stored `FULL/FORFEIT`, reason “SV đã có IELTS và bảo lưu chờ vào học chuyên ngành từ kỳ FALL2026”. Payment `485` is `30,000,000.00`. Charge `1066` / line `1080` is active and paid `15,000,000.00`; its only item is completed registration `3332`, EGC4, final grade `F`. Charge `1067` / line `1081` is already void with reason “SV bảo lưu module 2 GC kỳ Summer vì có IELTS”; its net Payment application is `0`, leaving `15,000,000.00` cash unapplied. Applying stored FULL/FORFEIT would incorrectly void the already-consumed EGC4 fee and convert that paid `15,000,000.00` into forfeit. Recommended correction pending business confirmation: keep charge `1066` active/paid, keep `1067` void, treat the unused `15,000,000.00` as preserved surplus, and do not money-apply the full-scope case.
- Case `26`, `AUH116717`, `SUMMER2026`, stored `FULL/FORFEIT`, reason “Lý do cá nhân liên quan đến vấn đề tài chính”. Six registrations `2265, 2310, 2415, 2511, 2597, 3382` are all defer; two are retakes (`AU001`, `AU015`). Active tuition charge `998` is gross `45,000,000.00`, discount `9,000,000.00`, cash `36,000,000.00` from Payments `364/443`. Active retake charges `1168/1169` are `9,000,000.00 + 3,000,000.00`, fully paid by Payment `511` / DNG `252`. Applying stored FULL/FORFEIT would void all three charges and consume the full released cash `48,000,000.00`; whether the retake cash is forfeited or preserved needs explicit confirmation.

Approved resolution and apply:

- `AUH120849`, case `25`: changed `FULL/FORFEIT → FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the approved non-refund/preserve note. Charge `1066` remains active/paid for completed EGC4; charge `1067` remains void for the unused next block; Payment `485` remains exactly `15,000,000.00` unapplied/Còn dư. No money row changed.
- `AUH116717`, case `26`: explicit reviewed disposition approved FULL FORFEIT of tuition plus both retake fees. Preview transaction proved and rolled back `released=consumed=48,000,000.00` before the real apply. Real apply voided tuition `998` and retake charges `1168/1169`, released Payment Applications `783..786` (`-3M, -33M, -9M, -3M`), released discount allocation `439` (`-9M`), created obligation `759` / adjustment charge `1187` / line `1202` for exactly `48,000,000.00`, and allocated Payments `364/443/511` through rows `787..789` (`+3M, +33M, +12M`). Adjustment balance is `0`; every source Payment has surplus `0`.
- Review found adjustment invoice `1340` remained stale `draft` despite being fully paid. `ApplyDeferFinancePolicyAction` now requires a non-empty reviewed-discount reason, records it in each source charge/line `void_reason`, closes the adjustment invoice, and recomputes its canonical lifecycle. Rehearsal invoice `1340` was corrected to `paid` (`48M/48M`); retake invoice `1456` is `cancelled` (`0/0`).
- Idempotency: re-running case `26` returns `noop/no_charge`. Global Payment count/amount remains `524 / 14,382,079,500.00`; Payment Application count increased from `762` to `769` but signed total remains exactly `14,018,263,600.00` because the seven rows are compensating release/re-allocation entries.
- Post-apply gates: focused defer/integrity/snapshot regression `49 passed / 303 assertions`; Pint and `git diff --check` passed; `INV-1..INV-18=0`; invoice snapshot dry-run scanned `553` with drift/invalid/failed `0`; DNG inventory remains `212/212 exact_link`, unmatched receipt `0`, safe to cut over.

Bulk `finance:defer-backfill --apply-money` is still not authorized: remaining no-registration PRESERVE/FORFEIT cases require their own disposition confirmation even though cases `25/26` are now resolved.

### Next active-case stop: AUH15442 and AUH14998

Read-only inspection of the 12 remaining active-charge cases found ten exact `15M` paid/no-discount rows matching their stored PRESERVE/FORFEIT policy, plus two conflicts:

- Case `6`, `AUH15442`, `SPRING2026`: current lifecycle is `intake_course`, not defer/dropout. Registration `1083` completed EGC3 with grade `B` on 2026-03-25; charge `1006` / line `1020` is active and validly paid `15M` by Payment `301`. It must not be voided from the historical `FULL/FORFEIT` case. `ApplyDeferFinancePolicyAction` now reads lifecycle through `StudentLifecycleStatusReader` and returns `skipped/student_lifecycle_active`; live rehearsal proof left charge `1006` active.
- Case `27`, `AUH14998`, `SUMMER2026`: DNG `171` / Payment `375` is `30M`. Registration `3336` completed EGC4 with grade `B` on 2026-06-29. Charge `1068` is active/paid `15M`; charge `1069` is already void with reason “SV bảo lưu M2 kỳ SUMMER 2026”, leaving exactly `15M` unapplied. Stored `FULL/FORFEIT` would consume only the active `15M` and leave the other `15M` surplus. Evidence matches the AUH120849 partial-preserve shape; business confirmation is required before changing case `27` or money.

Lifecycle-guard regression: full defer test directory `31 passed / 168 assertions`; Pint and `git diff --check` passed. No money was changed for cases `6/27`.

Approved resolution on 2026-07-13:

- `AUH15442`, case `6`: keep the historical `FULL/FORFEIT` policy. The student fully paid and completed all required EGC levels, so charge `1006` / line `1020` remains active and Payment `301` remains fully applied `15M`. The case note now records the reviewed decision. A live action replay returned `skipped/student_lifecycle_active` with `released=0`, `consumed=0`, no voided charge, and no cancelled DNG request.
- `AUH14998`, case `27`: changed `FULL/FORFEIT → FULL/PARTIAL`, `preserve_amount=15,000,000.00`. Charge `1068` remains active/paid for completed EGC4; charge `1069` remains void for unused SUMMER2026 module 2. Payment `375` remains exactly `30M`, with `15M` applied and `15M` unapplied/Còn dư. A live action replay returned `skipped/out_of_scope`, so no settlement ledger row changed.

Post-resolution gates remained clean: `INV-1..INV-18=0`; invoice snapshot dry-run scanned `553` with drift/invalid/failed `0`; DNG inventory remained `212/212 exact_link`, unmatched receipt `0`, safe to cut over; full defer regression remained `31 passed / 168 assertions`.

### Next active-case stop: AUH14608

- Case `2`, `AUH14608`, `SPRING2026`, is stored as `FULL/PRESERVE` with `preserve_amount=0.00`, but the original action note says “được bảo lưu phí half 2”. Payment `296` is `30M`; `15M` is applied to charge `1010` / line `1024` / invoice `1349`, and `15M` is already unapplied. Registration `1111`, EGC2 M1, is completed with grade `B`. Applying the stored PRESERVE policy would void the already-consumed `15M` charge and increase surplus from `15M` to `30M`, contradicting the half-2 note. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `2` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1010` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `2` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed half-2 decision. Charge `1010` remains active/paid; Payment `296` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope` with no money mutation.

### Next active-case stop: AUH112917

- Case `3`, `AUH112917`, `SPRING2026`, is stored as `FULL/FORFEIT`, but the original action note says “được bảo lưu phí half 2”. Payment `284` is `30M`; `15M` is applied to charge `1000` / line `1014` / invoice `1342`, while `15M` is already unapplied. Registration `1107`, EGC2 M1, is completed with grade `B`. Applying FORFEIT would void the consumed M1 charge and consume its released `15M` as forfeited cash, while the unused half-2 `15M` remains surplus. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `3` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1000` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `3` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed half-2 decision. Charge `1000` remains active/paid; Payment `284` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH19035

- Case `4`, `AUH19035`, `SPRING2026`, is stored as `FULL/FORFEIT`, but the original action note says “đc bảo lưu phí halff 2”. Payment `269` is `30M`; `15M` is applied to charge `1003` / line `1017` / invoice `1344`, while `15M` is already unapplied. Registration `1115`, EGC2 M1, is completed with grade `C`. Applying FORFEIT would void the consumed M1 charge and consume its released `15M` as forfeited cash, contradicting the half-2 preservation evidence. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `4` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1003` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `4` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed half-2 decision. Charge `1003` remains active/paid; Payment `269` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH16174

- Case `5`, `AUH16174`, `SPRING2026`, is `FULL/PRESERVE` but has stale `preserve_amount=0.00`. The original action note says “SV được bảo lưu học phí của M2 kỳ Sring2026”. Registration `1104`, EGC2 M1, is completed with grade `C-`. Payment `302` is exactly `15M`, fully applied to charge `1005` / line `1019` / invoice `1345`; there is currently no surplus. A rollback-only preview returned `applied/preserve_settled`, released `15M`, voided charge `1005`, cancelled invoice `1345`, and left Payment `302` fully unapplied `15M`; no DNG request was involved. The preview was rolled back and the database remains unchanged. Recommended resolution pending business confirmation: set `preserve_amount=15M`, then apply PRESERVE so M2 becomes `15M` surplus.

Business correction and approved resolution: the student paid for and completed all three EGC levels (`EGCF`, `EGC1`, `EGC2`), so there is no preserved cash. The three active charges `102/103/1005` total `45M` and reconcile to `40M` cash plus `5M` discount, remaining `0`; Payments `88/302` both have surplus `0`. Case `5` was changed to `FULL/PARTIAL`, `preserve_amount=0.00`, with an explicit no-settlement/fully-consumed note. The action now returns `skipped/out_of_scope`; no charge, invoice, Payment, or application row changed, and `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH116124

- Case `7`, `AUH116124`, `SPRING2026`, is stored as `FULL/PRESERVE`, `preserve_amount=0.00`, while the original action note says “được bảo lưu học phí M2 Spring2026”. Payment `273` is `30M`; `15M` is applied to charge `1015` / line `1029` / invoice `1352`, and `15M` is already unapplied. Registration `1108`, EGC2 M1, is completed with grade `B`. Applying PRESERVE would void the consumed M1 charge and increase surplus from `15M` to `30M`. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `7` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1015` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `7` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed M2 decision. Charge `1015` remains active/paid; Payment `273` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH115895

- Case `8`, `AUH115895`, `SPRING2026`, is stored as `FULL/PRESERVE`, `preserve_amount=0.00`, while the original action note says “được bảo lưu học phí M2 Spring2026”. Payment `295` is `30M`; `15M` is applied to charge `1016` / line `1030` / invoice `1353`, and `15M` is already unapplied. Registration `1103`, EGC2 M1, is completed with grade `C`. Applying PRESERVE would void the consumed M1 charge and increase surplus from `15M` to `30M`. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `8` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1016` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `8` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed M2 decision. Charge `1016` remains active/paid; Payment `295` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH117032

- Case `9`, `AUH117032`, `SPRING2026`, is stored as `FULL/PRESERVE`, `preserve_amount=0.00`, while the original action note says “Được bảo lưu phí M2 Spring2026”. Payment `282` is `30M`; `15M` is applied to charge `1018` / line `1032` / invoice `1355`, and `15M` is already unapplied. Registration `1080`, EGC2 M1, is completed with grade `C+`. Applying PRESERVE would void the consumed M1 charge and increase surplus from `15M` to `30M`. No action replay or database mutation was performed. Recommended correction pending business confirmation: change case `9` to `FULL/PARTIAL`, `preserve_amount=15M`, keep charge `1018` active/paid, and retain the existing `15M` surplus.

Approved resolution: changed case `9` to `FULL/PARTIAL`, `preserve_amount=15,000,000.00`, and recorded the reviewed M2 decision. Charge `1018` remains active/paid; Payment `282` remains `30M` with `15M` applied and `15M` unapplied. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Applied exact FORFEIT case: AUH18865

- Case `28`, `AUH18865`, `SUMMER2026`: registration `3340` completed EGC4 M1 with grade `C`; active charge `1097` was the unused EGC Level 5 fee `15M`. Payment `425` / DNG `175` was exactly `15M`, fully applied, with no discount or surplus. The stored `FULL/FORFEIT` policy and “thi IELTS” reason matched the evidence.
- Rollback-only preview proved `released=consumed=15M`, voided charge `1097`, and kept Payment `425` surplus `0`. Real apply created obligation `761` / adjustment charge `1189` / line `1204`, reallocated the same Payment with compensating release/application entries, retained DNG `175` as `paid_invoiced`, and re-running case `28` returned `noop/no_charge`.
- Post-apply inspection found invoice `1401.cached_paid_at` had incorrectly changed from the original Payment evidence (`2026-04-29 03:52:31` local DB time) to migration time (`2026-07-13 17:18:10`). Root cause: voiding the only source line temporarily cleared the paid timestamp; reopening the same statement for the FORFEIT adjustment then derived the new application time.
- Added a regression with a previously paid invoice and Payment evidence ten days before settlement. `ApplyDeferFinancePolicyAction` now captures the original timestamp only when the reused invoice was already `paid`, restores it after the temporary void/reopen sequence, and lets genuinely new/unpaid invoices keep their normal evidence behavior. Invoice `1401` was repaired to `2026-04-29 03:52:31` from Payment `425` evidence.
- Verification: focused timestamp test `1 passed / 16 assertions`; complete `DeferFinancePolicyTest` `15 passed / 87 assertions`; full defer directory `31 passed / 169 assertions`; Pint and `git diff --check` passed. Database gates: `INV-1..INV-18=0`; invoice snapshot dry-run scanned `553` with drift/invalid/failed `0`; DNG inventory `212/212 exact_link`, unmatched receipt `0`, safe to cut over.

Review follow-up tightened this fix: the captured timestamp and invoice ID now come from the exact statement containing one of the obligations being voided, rather than two separate semester-level queries. `SettlementService` remains the single writer for `cached_paid_at`. The regression additionally proves the same invoice ID is reused, final status remains `paid`, signed Payment Applications remain unchanged, and paid DNG status/payment link/ItemId are untouched even when receipt time differs from the original invoice paid timestamp. Combined defer plus invoice-cache regression: `41 passed / 212 assertions`; Pint and `git diff --check` passed.

### Next active-case stop: AUH11254

- Case `29`, `AUH11254`, `SUMMER2026`, is stored as `FULL/FORFEIT`, reason “thi IELTS”. Registration `3342` completed EGC5 with grade `C+`; active charge `1053` / line `1067` / invoice `1377` is also “EGC Level 5 Fee” `15M`. Payment `426` / DNG `180` is exactly `15M`, fully applied, surplus `0`; there is no next-level charge in this defer semester. Applying FORFEIT would void the fee matching the completed level and re-consume the same cash as a forfeit adjustment. No preview or database mutation was performed. Pending business confirmation: if the `15M` was fully consumed by completed EGC5, change case `29` to `FULL/PARTIAL`, `preserve_amount=0`, and keep charge `1053`, Payment `426`, and DNG `180` unchanged.

Approved resolution: confirmed no surplus; the `15M` was fully consumed by completed EGC5. Case `29` was changed to `FULL/PARTIAL`, `preserve_amount=0.00`, with an explicit no-settlement note. Charge `1053` remains active/paid; Payment `426` remains fully applied with surplus `0`; DNG `180` remains `paid_invoiced` with its original ItemId and Payment link. A live action replay returned `skipped/out_of_scope`; `INV-1..INV-18` remained `0`.

### Next active-case stop: AUH14944

- Case `30`, `AUH14944`, `SUMMER2026`, is stored as `FULL/PRESERVE`, `preserve_amount=15,000,000.00`, reason “thi IELTS”. The student failed EGC5 in SPRING2026 (registration `2180`) and then completed the paid EGC5 retake in SUMMER2026 with grade `C+` (registration `3343`). Active charge `1150` / line `1164` / invoice `1440` is exactly “EGC Level 5 Fee (Retake)” `15M`. Payment `489` / DNG `232` is exactly `15M`, fully applied, surplus `0`; DNG remains `paid_invoiced` with ItemId `87d96b13-b853-4bdd-bfdd-2561d7f586ba`.
- A rollback-only preview returned `applied/preserve_settled`, voided charge `1150`, cancelled invoice `1440`, and changed Payment `489` from surplus `0` to `15M`. DNG `232` remained `paid_invoiced`. The transaction was rolled back: charge `1150` is still active, invoice `1440` is still paid, Payment `489` surplus is still `0`, and DNG `232` is unchanged. Pending business confirmation: if the retake fee was fully consumed by completed EGC5, change case `30` to `FULL/PARTIAL`, `preserve_amount=0`, and keep the charge/payment/invoice/DNG untouched.

Approved resolution: confirmed the completed EGC5 retake fully consumed the `15M`; AUH14944 has no surplus. Case `30` was changed to `FULL/PARTIAL`, `preserve_amount=0.00`, with an explicit no-settlement note. The action returned `skipped/out_of_scope`: charge `1150` remains active, invoice `1440` remains paid `15M/15M`, Payment `489` remains fully applied with surplus `0`, and DNG `232` remains `paid_invoiced` with its original Payment link and ItemId. Post-change `INV-1..INV-18` all remained `0`.

## Final defer replay and post-decision reconciliation

- There is no case after case `30`. A fresh classifier dry-run found the same `30` full-scope cases, `0` itemizable, `3` already itemized, `27` historical no-registration reviews, and `0` planned items.
- A rollback-only preview executed the authoritative policy action for every case: `12 skipped/out_of_scope`, `1 skipped/student_lifecycle_active`, `17 noop/no_charge`, and zero mutating result. The official `finance:defer-backfill --apply-money --no-interaction` replay then returned `0 settled`, `13 skipped`, `17 noop`, released `0`, consumed `0`; no ledger row changed.
- Final integrity gates after the replay: `INV-1..INV-18=0`; invoice snapshot dry-run scanned `553` with drifted/rebuilt/invalid/failed all `0`; DNG cutover inspected `212`, all `212 exact_link`, unmatched receipt `0`, safe to cut over.
- Correct raw Payment Application comparison: `finance_before_test` has `760` rows and `finance_after_test` has `771`, but `SUM(payment_applications.amount)` is exactly `14,018,263,600.00` in both schemas. Reversal amounts are already stored negative; applying a second sign inversion would be an invalid comparison.
- Payment receipt truth is unchanged: both schemas have `524` Payments totaling `14,382,079,500.00`. The final invoice collectible delta is limited to exactly three approved SUMMER2026 cancellations: `AUH111841 -15,000,000`, `AUH16667 -30,000,000`, and `AUH110281 -22,500,000`; total `-67,500,000`. No other student/semester has a non-zero invoice collectible delta.
- Final idempotency matrix (all exit `0`): retake/resit `0 created / 37 already linked / 5 approved historical void skips / 0 mismatch`; BHYT `0/7/0`; tuition `0/285/0`; EGC level fee `0/428/0`; Billing Accounts `234/234` students and `759/759` obligations already linked; no active legacy defer/voucher/scholarship/EGC-exempt carrier; EGC-retake discounts `0 converted / 8 already converted / 0 mismatch`; voided-charge installments `0` pending cleanup.
- Final focused code gate passed `61 tests / 420 assertions`, covering the defer suite, invoice snapshot rebuild, DNG cutover, finance invariants, and settlement bypass architecture guard. `git diff --check` passed. The required full-suite attempt `./scripts/dev.sh test --compact` still exits `255` with no output in this local container, matching the previously recorded environment-level limitation; it produced no actionable test failure diagnostic.

### Human UI gate — stopped before browser mutation

- The current web runtime still reports `app.url=http://127.0.0.1:8000` and effective database `asia`. Opening the UI now would inspect the untouched local Asia database, not the completed `finance_after_test` rehearsal. Per the operator stop rule, no UI acceptance was claimed and no runtime database switch was made without confirmation.
- Concrete rehearsal records for the browser gate after switching only the local web runtime to `finance_after_test`: AUH14944 student `496`, invoice `1440`, Payment `489`, DNG `232` (paid retake, no surplus); AUH116717 student `645`, adjustment invoice `1340` and cancelled retake invoice `1456` (FORFEIT `48M`); AUH14998 student `475`, Payment `375` (`15M` applied + `15M` surplus); AUH120849 student `672`, Payment `485` (`15M` surplus); AUH11254 student `416`, invoice `1377`, Payment `426`, DNG `180` (fully consumed, no surplus).

Browser acceptance evidence after an approved temporary app-only switch to `finance_after_test`:

- AUH14944 invoice `1440`: canonical invoice and line breakdown both show gross `15M`, discount `0`, cash `15M`, credit `0`, remaining `0`, exact reconcile and zero rounding remainder. Payment `489` shows amount/applied `15M`, unallocated `0`; DNG `232` is `paid_invoiced`, bridge complete to Payment `489`, with the original ItemId. Student 360 shows total paid/cash `70M`, surplus `0`, current DNG work `0`.
- AUH14998 student `475`: Student 360 shows Payment `375` as `30M` total, `15M` collected and `15M` surplus; current remaining is `0` and no DNG needs processing. AUH120849 student `672` likewise shows Payment `485` as `30M`, `15M` collected and `15M` surplus.
- AUH11254 student `416`: current remaining `0`, total paid/cash `70M`, surplus `0`; Payment `426` is rendered `15M/15M`, surplus `0`. Reporting renders invoice `1377` as `15M/15M`, remaining `0`.
- AUH116717: adjustment invoice `1340` renders gross/cash `48M`, remaining `0`, with the exact reversal/reallocation/discount-release audit trail. Retake invoice `1456` renders cancelled (`Đã giảm trừ`) with canonical totals all `0` and retained historical reversal evidence. Student 360 shows total paid/cash `82M`, remaining `0`, surplus `0`. Current reporting renders invoice `1340` as `48M/48M`, remaining `0`.
- Cockpit, Settlement Worklist, DNG Batch Studio, current Collection Progress, and Historical/As-of tabs all rendered without browser console warnings/errors. Current reporting shows SUMMER2026 total/cash `3,000,500,000`, remaining `0`; Historical/As-of has a separate snapshot and timestamp input.

Human UI blocker found (money remains correct): AUH116717 Student 360 still displays `DNG đã thu nhưng phí đã hủy — Có 3 DNG...` even though all three Payments were fully reallocated to the approved FORFEIT adjustment and surplus is `0`. Deterministic browser repro confirmed `Còn dư 0`, `Không có DNG cần xử lý`, but the warning remains. Root cause is `GetStudentFinanceReviewSignalsQuery::paidDngHasVoidedFee()`: it flags any historical DNG header/pivot/application touching a void charge and does not recognize compensating reversals plus a completed disposition/forfeit allocation. Existing test coverage expects the warning for a voided fee that leaves surplus, but has no resolved-disposition/surplus-zero case. Treat this as a Student 360 false-positive/actionability acceptance blocker before production; no code or finance data was changed during diagnosis.

After browser QA, the temporary compose override was removed, the app service was recreated from the original configuration, and effective runtime database was verified back at `asia`.

### Student 360 paid-DNG/voided-fee signal fix

- The AUH116717 blocker was a UI-query false positive only; no finance ledger, invoice, Payment, DNG, or rehearsal database row was changed by this fix.
- Root cause: the old signal treated any historical application touching any void line as actionable forever. It did not evaluate signed application net, completed reallocation/disposition, or whether reversal evidence belonged to the exact DNG target charge.
- New decision rule:
  - keep the warning when the DNG lacks a canonical Payment bridge;
  - keep it when any exact target charge lacks its own void-line application/reversal evidence;
  - keep it when any relevant void-line group has a non-zero signed net;
  - keep it while undisposed surplus remains;
  - clear it only when every exact target group nets to zero and the released cash is fully reallocated or explicitly refunded/retained as FORFEIT.
- Regression coverage exercises positive net before reversal, successful FORFEIT reallocation, explicit retain-forfeit disposition, missing Payment bridge, and a cross-charge mismatch where reversal evidence belongs to a different void charge. Result: `4 passed / 40 assertions`.
- Adjacent canonical Student 360/API checks: `8 passed / 85 assertions`. Pint and scoped `git diff --check` passed.
- Pre-repair full `StudentOverviewShellTest`: `14 passed / 5 failed`; all five failures were the `settlement_position.missing_currency` fixture gap and occurred before assertions. The focused signal cases passed.
- Required full-suite attempt `./scripts/dev.sh test --compact` exited `255` with no output, matching the existing local environment limitation.
- A fresh app-only switch to `finance_after_test` was attempted for final AUH116717 browser verification, but the controllable browser tab no longer had the authenticated session and stopped at the Google sign-in page. No form was submitted. The temporary override was deleted, the app was recreated, config cache was cleared, and effective runtime database was verified back at `asia`.
- After the operator restored the authenticated browser session, the final app-only `finance_after_test` UI gate passed for AUH116717 / student `645`: the page showed `Còn dư 0 ₫`, `Không có DNG.`, and `Không có ca cần thận trọng.`; the false `DNG đã thu nhưng phí đã hủy` signal count was exactly `0`. Browser console warning/error count was `0`.
- Follow-up diagnosis proved the five `StudentOverviewShellTest` failures were stale test fixtures: they invoked the canonical snapshot writer with legacy charges lacking FinanceObligation currency. The fixtures now create BillingAccount-owned FinanceObligations with `currency=VND`; the production `settlement_position.missing_currency` fail-closed guard remains unchanged. Verification improved from `14 passed / 5 failed` to `19 passed / 347 assertions`, and the complete Student 360 directory passes `37 tests / 460 assertions`.

### Post-UI test discovery and legacy paid-PTL repair

- Pest discovery originally exited `255` because two Academic files declared the same global helper `failedGradeRecord()`. The legacy backfill helper was renamed to `legacyBackfillFailedGradeRecord()`; `pest --list-tests` now succeeds and lists `1,979` tests.
- The first complete `ExamResitLegacyBackfillTest` run then exposed `settlement_position.missing_currency`. Its legacy charge fixture was migrated to a BillingAccount-owned `FinanceObligation` with `currency=VND`; the fail-closed production reader was not weakened.
- Three remaining failures were a real production repair-path gap: `CreateLegacyExamResitChargeFromPaidPtlAction` materialized a charge for a historical paid PTL DNG request without first creating its canonical debit aggregate. The repair path now creates an accepted VND obligation with honest temporary identity `legacy / paid_ptl_dng / dng-payment-request:{id}`, then materializes the charge. When reconciliation creates the real `ExamResitAttempt`, the same transaction repoints the obligation identity to `academic / exam_resit_attempt / exam-resit:{id}`. DNG remains historical payment evidence and is not used by the normal intake path to mint new debt.
- Legacy exam-resit verification: `22 passed / 104 assertions`; adjacent backfill plus materializer architecture checks: `7 passed / 46 assertions`.
- Additional stale tests were aligned with the current architecture instead of changing production behavior:
  - reporting recognizes the fail-closed `invalid` balance state;
  - Academic fee-summary and AI student-profile fixtures now own canonical VND obligations/payable lines;
  - exam-resit controller tests now assert the durable `finance_pending_cancellation` handoff before the Finance worker completes, rather than expecting synchronous cancellation;
  - Notification registry default-provider expectation now matches the configured DB-backed provider.
- Combined scoped gate after Pint: `89 passed / 942 assertions`. Student 360 remains `37 passed / 460 assertions`; legacy exam-resit remains `22 passed / 104 assertions`.
- Standard full-suite command still reaches PHP's container limit: `./scripts/dev.sh artisan test --compact` ends with `Allowed memory size of 268435456 bytes exhausted`. A diagnostic rerun with `php -d memory_limit=1G vendor/bin/pest --compact` completed in `338.37s`: `1,763 passed`, `204 failed`, `13,322 assertions`.
- The `204` failures are not accepted as a clean release gate and are not evidence of rehearsal-data drift. Representative independent groups are: one reproducible AI retry-SSE failure; legacy retake/resit and many finance fixtures that still create raw charges without obligation currency; stale durable-cancellation expectations; missing command registration; leaked active-semester/config/auth state when the entire suite shares one process; factory collisions/time constraints. Many affected files pass when run in the focused isolated gate above. Full-suite isolation/fixture cleanup remains separate required work before claiming a globally green suite.
- No rehearsal or app database was mutated by these test runs. The web runtime was rechecked after completion: default connection `mariadb`, configured and effective database both `asia`.
- Fresh post-test read-only verification explicitly targeted `finance_after_test`: `INV-1..INV-18=0`; invoice snapshot dry-run scanned `553` with drifted/rebuilt/invalid/failed all `0`; DNG cutover dry-run inspected `212`, all `212 exact_link`, unmatched receipt `0`, backfilled `0`, `safe_to_cutover=true`, `inventory_complete=true`. This confirms the test-suite work did not alter rehearsal money or cutover evidence.
