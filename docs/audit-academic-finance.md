---
title: Academic-Finance Integration Audit
status: audit
owner: Platform Team
last_verified: 2026-08-31
scope: audit
---

# Academic–Finance Integration Audit

What the system **actually does today** at every point where the Academic module
(courses, enrollment, withdrawals, exam resits, defer) touches the Finance
module (charges, receivables, the DNG payment gateway, scholarships).

Read-only audit. Every claim is tagged:

- **[confirmed]** — read directly in the code at the cited location.
- **[inferred]** — reasoned from related code; the cited location supports but
  does not directly show the statement.

**Coverage:** 4 clusters examined in full — (1) cancellation & defer handoffs,
(2) charge creation & pricing, (3) balance gates & scholarships, (4) payment
webhooks & background jobs. *Not yet read:* the internal math of
`DeferCaseService::processFeePolicy()` (the code applying PRESERVE/FORFEIT to
invoices), the Pricing Operations admin screens, and front-end (Vue) validation.
Unresolved questions are listed at the end.

---

## 1. Summary of integration points

| # | Event (Academic side) | Effect (Finance side) | Code location |
|---|---|---|---|
| 1 | Retake-course registration approved | Charge created from pricing catalog, never from Academic | `app/Modules/Academic/Delivery/Actions/CreateRetakeCourseRegistrationAction.php:198-229` |
| 2 | Exam-resit attempt created | Charge created from pricing catalog | `app/Modules/Academic/Delivery/Actions/CreateExamResitAttemptAction.php:80-113` |
| 3 | Retake fee paid (webhook) | Academic record marked paid → student auto-enrolled into retake class | `app/Modules/Finance/Dng/Services/DngWebhookService.php:436-439`, `AutoEnrollRetakeCourseAction.php:47-98` |
| 4 | Resit fee paid (webhook) | Academic record marked paid → resit can be scheduled | `DngWebhookService.php:455-458`, `ScheduleExamResitAttemptAction.php:154-179` |
| 5 | Retake/exam-resit cancelled | Durable handoff table → Finance cancels/voids charge or keeps it as revenue | `CancelRetakeCourseRegistrationAction.php:28-59`, `CancelExamResitAttemptAction.php:62-114`, `ProcessFinanceCancellationOperationAction.php` |
| 6 | Late payment arrives on a cancelled source | Finance re-opens the cancellation (outbox event back to Academic) | `ResumeFinanceCancellationOnPaidEvidenceAction.php:24-83` |
| 7 | Student defers (semester or per-course) | Charges voided; paid cash preserved to balance or forfeited into a new adjustment charge | `ApplyStudentLifecycleDeferAction.php:24-78`, `ApplyDeferFinancePolicyAction.php` |
| 8 | EGC block result = fail | Flat VND 7,500,000 retake discount on the next block's fee (conditions apply) | `ApplyEgcRetakeDiscountAction.php:34,43-170` |
| 9 | Student leaves EGC intake stage | Flat VND 15,000,000 credit against semester invoice | `ApplyEgcMajorEntryCreditAction.php:26,37-107` |
| 10 | Unused EGC level-fee charge in a semester | Charge voided; paid money released to balance | `BuildEgcCarryForwardPlanAction.php:17-164`, `ApplyEgcCarryForwardAction.php:19-65` |
| 11 | Scholarship semester adjustment approved | Amount reduced on semester invoice (maker/checker, timing guard) | `ApplyScholarshipSemesterAdjustmentAction.php:43-178` |
| 12 | Section move (same course, same semester) | **No financial effect at all** | `MoveStudentBetweenCourseOfferingSectionsAction.php:1-137` (no Finance code) |
| 13 | Student holds (financial/academic) | Block enrollment, grades, graduation, transcript, student self-service | `app/Models/AcademicHold.php:107-119`, `app/Http/Middleware/StudentApiAuthorization.php:83-118` |

---

## 2. Business rules (numbered)

Money figures marked **seed** are the defaults the code ships with; the live
production amounts live in the `finance_pricing_catalog_items` database table
and can be changed by staff on the Pricing Operations screens.

**RULE-01 — Retake fee pricing** [confirmed]
WHEN staff approve a retake-course registration AND an active price for
`retake_fee` exists in the pricing catalog THEN Finance charges exactly that
amount (seed: **VND 1,500,000** flat, `BaselinePricingCatalog.php:32-39`);
Academic never supplies a price. WHEN no catalog rule is active THEN creation
is blocked with the error "Chưa cấu hình giá học lại cho môn này".
Evidence: `CreateRetakeCourseRegistrationAction.php:198-229`,
`FinancePricingCatalog.php:69-78,164-212`,
`ObligationTypeRegistry.php:177-192` (strategy `CatalogFixed`).

**RULE-02 — Exam-resit fee pricing** [confirmed]
Same pattern as RULE-01 for `exam_resit_fee` (seed: **VND 750,000** flat,
`BaselinePricingCatalog.php:40-47`). Academic records back the amount Finance
decided (`CreateExamResitAttemptAction.php:115`).

**RULE-03 — Section moves never change money** [confirmed]
WHEN a student moves between two sections of the same course/semester THEN
nothing is charged, refunded, or recalculated. Evidence: the action updates
only class assignment and attendance
(`MoveStudentBetweenCourseOfferingSectionsAction.php:34-113`); no Finance
import or call exists in the file.

**RULE-04 — Payment confirmed via webhook updates academic status** [confirmed]
WHEN the DNG payment gateway confirms cash for a retake or resit charge THEN
Finance's webhook handler synchronizes the Academic record to `paid` (via the
`RetakeRegistrationPaymentSyncer` / `ExamResitAttemptPaymentSyncer` contracts).
Evidence: `DngWebhookService.php:435-458`.

**RULE-05 — Retake auto-enrollment requires live settled state** [confirmed]
WHEN a retake payment is confirmed AND the live Finance ledger says the fee is
still `unpaid` or `partially_paid` THEN the student is NOT auto-enrolled into
the retake class. "Settled" = ledger state is anything other than those two.
Evidence: `AutoEnrollRetakeCourseAction.php:47-56`,
`AcademicObligationSettlement.php:46-57,108-117`.

**RULE-06 — Resit scheduling gate uses the cached paid flag** [confirmed]
WHEN staff schedule a resit sitting AND the attempt's stored `hq_fee_status` is
not `paid` AND the syllabus snapshot `allow_unpaid_sitting` is false THEN
scheduling is rejected ("Lệ phí thi lại chưa thanh toán…"). Evidence:
`ScheduleExamResitAttemptAction.php:154-179` (reads the cached column at line
156, not the live ledger).

**RULE-07 — Resit result recording gate** [confirmed]
WHEN staff record a resit result AND `hq_fee_status` is not `paid` AND
unpaid sitting is disallowed THEN recording is rejected; if unpaid sitting IS
allowed, a written reason is mandatory and stamped with staff user + timestamp.
Evidence: `CompleteExamResitAttemptAction.php:196-221`. Note: the class
docblock (line 37-38) claims "canonical-derived paid state" but the code reads
the cached flag — see P-03.

**RULE-08 — Unpaid-sitting escape hatch** [confirmed]
WHEN a syllabus sets `exam_resit_allow_unpaid_sitting = true` (default
`false`, `migrations/2026_06_17_100000_add_retake_resit_source_foundation.php:51`)
THEN a student may sit/resit unpaid with a recorded reason. The value is
snapshotted at attempt creation, so later syllabus changes are not
retroactive (`CreateExamResitAttemptAction.php:206`).

**RULE-09 — Cancellation of a paid retake always keeps money for later** [confirmed]
WHEN a paid retake fee is cancelled THEN the money is always released to the
student's unapplied balance ("keep for later") — hardcoded, staff cannot choose
forfeit. Evidence: `CancelRetakeCourseRegistrationAction.php:51-53` ("Owner
rule #4").

**RULE-10 — Cancellation of a paid resit: staff choose forfeit or keep** [confirmed]
WHEN a paid exam-resit fee is cancelled THEN the staff-selected `fee_outcome`
decides: `forfeit` (money kept as revenue, **default**) or `keep_for_later`
(money released to balance). Evidence:
`CancelExamResitAttemptAction.php:29-35,116-134`.

**RULE-11 — Cancellation confirmations** [confirmed]
WHEN the source has paid evidence THEN staff must acknowledge "no refund"
before cancelling; WHEN it is unpaid with an outstanding charge THEN staff must
type the literal confirmation string `CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE`.
Evidence: `CancelExamResitAttemptAction.php:143-164`.

**RULE-12 — Money disposition in Finance is parsed from reason text** [confirmed]
WHEN Finance processes a cancellation THEN if `paid_void_reason` contains the
literal substring `keep_for_later` the disposition is `PaidReleaseToBalance`;
otherwise a paid charge becomes `KeptPaidNoRefund` (forfeit). There is no
typed field — only text matching. Evidence:
`ProcessFinanceCancellationOperationAction.php:566-581`.

**RULE-13 — Late paid evidence reopens a completed cancellation** [confirmed]
WHEN a payment arrives for a charge that has a completed cancellation
operation AND the operation's disposition was not already terminal THEN the
operation is re-dispatched: forfeit → nothing changes; keep_for_later → the
charge is voided and cash released, with an outbox event to Academic.
Evidence: `ResumeFinanceCancellationOnPaidEvidenceAction.php:24-83`,
`ProcessFinanceCancellationOperationAction.php:218-303`.

**RULE-14 — Defer, semester scope (PRESERVE)** [confirmed]
WHEN a full-semester defer uses fee policy `PRESERVE` THEN every active
positive charge for that student+semester is voided and paid cash is released
to the unapplied balance; no new charge is created. Evidence:
`ApplyDeferFinancePolicyAction.php:278-292`.

**RULE-15 — Defer, semester scope (FORFEIT)** [confirmed]
WHEN policy is `FORFEIT` THEN the same charges are voided but the released
paid cash is placed onto a single new adjustment charge
("Defer forfeit settlement (case #N)"); WHEN nothing was paid THEN no
adjustment charge is created — the system never creates unpaid forfeit debt.
Evidence: `ApplyDeferFinancePolicyAction.php:294-352`.

**RULE-16 — Defer with discounted charges requires review** [confirmed]
WHEN any affected charge line carries a discount/scholarship THEN the defer
settlement is skipped unless the policy is `FORFEIT` **and** a staff-reviewed
reason text is supplied. Evidence:
`ApplyDeferFinancePolicyAction.php:147-150`.

**RULE-17 — Defer validation on the Academic side** [confirmed]
WHEN staff record an `ACADEMIC_DEFER` student action THEN the fee policy must
be `PRESERVE`, `FORFEIT`, or `PARTIAL` (with a non-negative preserve amount if
partial); courses already deferred this semester are rejected; EGC selections
are rejected under `FORFEIT`. Evidence:
`StoreStudentActionRequest.php:100-158`.

**RULE-18 — Defer is atomic across modules** [confirmed]
WHEN a defer is recorded THEN Academic calls Finance **in the same database
transaction** as the lifecycle change — a failure rolls back both sides. This
is architecturally different from cancellation (which is asynchronous).
Evidence: `RecordStudentActionAction.php:128-152`.

**RULE-19 — EGC retake discount** [confirmed]
WHEN an EGC block result = fail AND no prior discount for that block AND the
student is still in stage `intake_pre_uni_gc` AND block attendance ≥ 80% AND
the target is a later retake block's `egc_level_fee` for the same level THEN a
discount of exactly **VND 7,500,000** is applied (once per block). Despite the
code comments calling it "a 50% discount", it is a fixed amount, never
computed as a percentage. Evidence: `ApplyEgcRetakeDiscountAction.php:27,34,43-170`.

**RULE-20 — EGC major-entry credit** [confirmed]
WHEN a student transitions out of `intake_pre_uni_gc` THEN a one-time credit of
exactly **VND 15,000,000** is applied against that semester's invoice.
Evidence: `ApplyEgcMajorEntryCreditAction.php:26,37-107`.

**RULE-21 — EGC carry-forward** [confirmed]
WHEN a student paid for an EGC level-fee charge in a semester AND the charge
was never consumed by a block attempt THEN the whole charge is voided (not
partially reduced) and the paid cash is released to the balance.
Evidence: `BuildEgcCarryForwardPlanAction.php:17-164`,
`ApplyEgcCarryForwardAction.php:44-55`.

**RULE-22 — Scholarship semester adjustment** [confirmed]
WHEN a scholarship adjustment is applied THEN a maker and a checker are
required (checker must hold `approve_scholarship_adjustment` at the student's
campus); the adjusted amount must stay within `[0, original]`; and a timing
guard routes it: applied now / finance-review-required (invoice already has
payments or live collection) / not applicable (term charges nothing) /
wait (invoice not generated yet). Evidence:
`ApplyScholarshipSemesterAdjustmentAction.php:43-276`.

**RULE-23 — Scholarships never act as blockers** [confirmed]
Credit/discount records reduce what is owed but never appear as unpaid
blockers (stated in `docs/adr/0026-...md:60-61` and consistent with the
actions' code).

**RULE-24 — Academic holds block actions, but are not created automatically** [confirmed]
WHEN an active hold exists THEN: category `registration` blocks semester
enrollment (`EloquentSemesterEnrollmentEligibilityReader.php:61-62`);
category `academic` blocks grade-viewing routes (`StudentApiAuthorization.php:94-99`);
type `financial` or category `all` blocks the student self-service API
except profile and scholarship-adjustment-confirmation routes
(`StudentApiAuthorization.php:102-143`); category `graduation`/`all` blocks
graduation, `transcript`/`all` blocks transcripts (`AcademicHold.php:107-119`);
category `all` blocks student login (`StudentLoginAction.php:37-40`).
**However:** no code was found that automatically creates a `financial` hold
from an outstanding balance — holds appear to be manual staff actions.
[confirmed absence; see Q-01]

**RULE-25 — The balance figure is a report, not a gate** [confirmed]
The general student balance (`GetStudentBalanceQuery.php:22-40`) is used only
for dashboards, AI profiles, and charge creation (unapplied-cash offset).
No Academic action is gated on it. [confirmed via caller search]

**RULE-26 — Charge voiding safety rules** [confirmed]
WHEN a charge is voided THEN payments/discounts on it are released back to
unapplied balance, live installments are cancelled, and voiding is blocked
outright while a live DNG payment request is outstanding. Voiding never
recomputes a new price. Evidence: `VoidFinanceChargeAction.php:38-173`.

**RULE-27 — Installments never change the total** [confirmed]
WHEN a charge is split into installments THEN the installment rows must sum
exactly to the net charge (amount − discount); splitting is refused if any
installment is already paid; if a later discount lowers the net, already-pushed
installments are never rewritten — the request is flagged `NEEDS_REVIEW` for a
human. Evidence: `SplitChargeIntoInstallmentsAction.php:55-128`,
`ReconcileChargeInstallmentsAction.php:42-153`.

---

## 3. Problems (ranked)

### WRONG MONEY

**P-01 — The DNG reconciliation fallback never updates Academic payment status.** [confirmed]
The webhook is the only path that syncs Academic payment state after money
arrives (`DngWebhookService.php:435-458`). But the 15-minute reconciliation job
that exists **specifically to catch lost webhooks**
(`DngReconciliationService`) settles Finance installments and calls neither
`RetakeRegistrationPaymentSyncer` nor `ExamResitAttemptPaymentSyncer` (verified
by full read of its constructor and body — zero matches for "PaymentSyncer" in
the file). Same gap in `ResolveDngReceiptExceptionAction`,
`BridgePaidDngRequestsForChargeAction`, `CaptureDngProviderReceiptAction`.
**Consequence:** if a webhook is lost, Finance records the payment but the
student stays `unpaid` in Academic — resit cannot be scheduled, retake class
not granted — until a staff member manually runs payment allocation. Real
money is received, real academic access is wrongly withheld.

**P-02 — "50%" EGC retake discount is actually a fixed VND 7,500,000.** [confirmed]
`ApplyEgcRetakeDiscountAction.php:34` defines `DISCOUNT_AMOUNT = 7_500_000`
while comments (lines 27, 41) call it a 50% discount. It coincidentally equals
50% of the 15,000,000 fallback level fee, but the code never computes the
relationship — if a unit's `base_fee` differs, the discount is no longer 50%.
**Consequence:** discounts may not match the business policy the comments
describe, and nothing recalculates if the level fee changes.

**P-03 — Resit gates read a cached paid-flag, contradicting the system's own rule.** [confirmed]
`ScheduleExamResitAttemptAction.php:156` and
`CompleteExamResitAttemptAction.php:~196-221` read `hq_fee_status` (a stored
flag), not the live Finance ledger, although ADR-0026
(`docs/adr/0026-...md:69`) states "a projection is never the sole condition of
a hard gate", and the completion docblock itself claims canonical-derived
state. The flag is only refreshed by webhook/allocate paths (RULE-04), never
re-checked at gate time. Combined with P-01, a lost webhook means a legitimately
paid student is blocked from scheduling/record-keeping.

**P-04 — Money disposition decided by string matching.** [confirmed]
Whether paid cancellation money is forfeited or released depends on the
substring `keep_for_later` appearing in a free-text reason
(`ProcessFinanceCancellationOperationAction.php:571-573,301-303`). A typo or a
future reason-string change silently flips forfeit ↔ release-to-balance.
**Consequence:** wrong money outcome with no error raised.

**P-05 — Installment eligibility flag is never enforced.** [confirmed]
The fee-type registry marks retake/resit fees as not supporting installments
(`supportsInstallments = false`,
`ObligationTypeRegistry.php:184,199`), but neither the split action's own
eligibility check, the controller, nor the policy checks that flag
(`SplitChargeIntoInstallmentsAction.php:86-102`,
`FinanceChargeController.php:178-184`, `FinanceChargePolicy.php:20-28`).
**Consequence:** staff can split a retake/resit fee into installments, which
the configuration says should be impossible.

### WRONG STUDENT STATUS

**P-06 — No automatic financial hold from balance.** [confirmed absence]
The hold system blocks enrollment/grades/graduation/transcripts (RULE-24), but
nothing in the code creates a `financial` hold when a student owes money —
only manual/test creation was found. `academic_holds.amount` is stored but
never compared to anything. **Consequence:** students with unpaid balances are
blocked only if a staff member remembers to create a hold manually; otherwise
nothing stops them from enrolling, graduating, or receiving transcripts.

### INCONVENIENCE

**P-07 — Silent failures needing manual discovery.** [confirmed]
No alert is fired when a DNG receipt exception is recorded or when an
installment-push job exhausts its 3 retries — both become silent database rows
findable only on admin screens. Same for cancellations stuck in
`requires_review` (never auto-recovered by the 1-minute sweeper
`RecoverFinanceCancellationWorkAction.php:20`).
**Consequence:** students stuck in wrong states until someone checks the
worklist.

### CODE SMELL

**P-08 — Dead code superseded by the async design.** [confirmed]
- `CancelFinanceObligationAction` (full synchronous cancel logic, ~177 lines)
  is DI-registered (`FinanceServiceProvider.php:96`) but never called
  anywhere; the live path is the async handoff trio.
- `CreateRetakeCourseChargeSimpleAction` and `CreateExamResitChargeSimpleAction`
  have zero production callers (tests only); ADR-0026 already plans their
  retirement (`docs/adr/0026-...md:94`).
- `GenerateEgcChargesAction::CHARGE_AMOUNT` (deprecated flat fee) has zero
  usages.
- Finance events `ChargeFullySettled`, `InstallmentPushed`,
  `InstallmentPushFailed` are dispatched by live money code but have **no
  listeners anywhere**; the `ChargeFullySettled` docblock admits deliberate
  deferral. **Consequence:** no code can react when a charge is fully paid
  outside the webhook path — see P-01.

**P-09 — Captured-but-unused grace deadline.** [confirmed]
`exam_resit_late_payment_grace_days` (default 14) is snapshotted onto every
resit attempt (`CreateExamResitAttemptAction.php:73,100`) but no code compares
it to a date to change any behavior. **Consequence:** a configured 14-day
late-payment deadline exists in data but is not enforced anywhere.

**P-10 — Duplicated gate logic.** [confirmed]
The resit scheduling gate and result-recording gate are two independent copies
of the same rule (cached flag + allow-unpaid-sitting + reason), with no shared
helper (`ScheduleExamResitAttemptAction.php:154-179` vs
`CompleteExamResitAttemptAction.php:196-221`). They currently agree; they can
silently diverge.

**P-11 — Stale code comment on retake charge creation.** [confirmed]
`CreateRetakeCourseRegistrationAction.php:27-28` says Finance creates the
charge "later through the DNG/fee worklist", but the code creates it
synchronously (lines 198-229). Misleads readers about timing.
*(Historical note: an earlier EGC duplication — dedicated flow hardcoding a
flat fee vs batch flow reading `base_fee` — was already fixed; both flows now
share `EgcLevelFeeResolver`.)* [confirmed, `EgcLevelFeeResolver.php:13-17`]

### WRONG MONEY (added after business-owner review)

**P-12 — Flat 15,000,000 "major entry credit" overlaps with the exact-amount carry-forward mechanism.** [confirmed]
Two mechanisms coexist for the same business need ("paid EGC money not yet
consumed → use it for major tuition"):
- EGC carry-forward (RULE-21): voids each unconsumed `egc_level_fee` charge and
  releases the **actual paid amount** per charge
  (`BuildEgcCarryForwardPlanAction.php:17-164`, `ApplyEgcCarryForwardAction.php:44-55`).
- Major-entry credit (RULE-20): a staff button
  (`EgcRetakeAdjustmentsController.php:46-61`) applying a **hardcoded
  15,000,000 VND** credit (`ApplyEgcMajorEntryCreditAction.php:26`, type
  `EgcExemptCredit`), regardless of what the student actually paid or has left
  unconsumed. Staff cannot enter an amount.
**Consequence:** a student who paid two EGC levels (30M) but consumed one
receives only 15M via the flat credit — or more than they are owed if they paid
less; the two mechanisms can also be applied to the same student. Intended
behavior is an open business-policy question (Q-12).

---


## 4. Questions for the business owner

**Q-01 — Financial holds are manual?** No code creates a financial hold when a
student owes money. Is blocking-by-balance done by staff on the admin screens,
or is this a missing automation? If manual, who is responsible and how often?

**Q-02 — Resit late-payment grace (14 days)** is stored on every attempt but
never enforced. Was a deadline supposed to trigger a block/cancellation, or is
it deliberately informational for now?

**Q-03 — Resit gates use the cached paid flag** rather than a live Finance
check, contradicting ADR-0026's stated rule. Is staff re-checking payment
before scheduling accepted practice (making the cache acceptable), or should
the gate query Finance live?

**Q-04 — Retake cancellation always releases money to balance** (no forfeit
option, RULE-09) while resit cancellation defaults to forfeit (RULE-10). Is
that asymmetry intended policy?

**Q-05 — EGC discount "50%" vs fixed VND 7,500,000** (P-02): which is the real
policy — a true 50% of the block fee, or a flat amount that happens to equal
50% today?

**Q-06 — Installments on retake/resit fees** (P-05): the registry forbids
splitting them, but nothing enforces it. Should staff be able to split these,
or is the flag correct and the enforcement missing?

**Q-07 — Forfeit-vs-keep via free-text reason** (P-04): is a typed/structured
choice acceptable to require, given the current text-matching fragility?

**Q-08 — Defer PARTIAL policy and COURSES-scope settlement** are explicitly
unimplemented in `ApplyDeferFinancePolicyAction` (lines 169-171, "later
slices"). The validation (RULE-17) accepts them. What should happen today when
staff choose them — and what does the internal math of
`DeferCaseService::processFeePolicy()` actually apply? (Not traced in this
audit.)

**Q-09 — Who checks the silent worklists** (DNG receipt exceptions, failed
installment pushes, cancellations requiring review — P-07)? Is there an
operational routine, or do these need alerts?

**Q-10 — Live pricing amounts:** the confirmed figures (retake VND 1,500,000;
resit VND 750,000; EGC level fee fallback VND 15,000,000) are code-level
seeds. The production amounts live in the `finance_pricing_catalog_items`
table. Please verify them against the Pricing Operations screens.

**Q-11 — Scholarship auto-trigger:** scholarship semester adjustments appear
staff-initiated only (Finance side confirmed; no automatic Academic-side
trigger found). [inferred] Correct?

**Q-12 — Which mechanism is the real policy for unused EGC prepaid fees when a
student enters their major?** (P-12) The carry-forward releases the exact paid
amount per unconsumed charge; the major-entry credit is a flat 15M applied via
a staff button. Should the flat credit exist at all, should its amount be
computed from what the student actually paid and has not consumed, or should it
be removed in favor of carry-forward only?

### Decisions — answered by the business owner (2026-09-01)

| # | Decision |
|---|---|
| Q-01 | Financial holds are created **manually by staff**. No automation missing by design. |
| Q-02 | The 14-day resit late-payment grace is **informational only**. Not a bug; document as reference data. |
| Q-03 | Resit scheduling/result gates **must switch to live Finance settlement checks** (fixes P-03; aligned with ADR-0026). |
| Q-04 | Cancellation of retake/resit must offer staff **two explicit actions: keep fee / forfeit fee** — the retake side must no longer hardcode keep-for-later (RULE-09 revision). |
| Q-05 | EGC retake discount is a **true 50%, computed dynamically** from the target block fee (fixes P-02; replaces the fixed 7.5M constant). |
| Q-06 | Installments on retake/resit fees are **forbidden — enforcement is missing** (fixes P-05; enforce `supportsInstallments` in the split path). |
| Q-07 | Paid-cancellation money outcome must use a **structured keep/forfeit choice**, not text matching (fixes P-04). |
| Q-08 | Defer PARTIAL and COURSES-scope settlement must be **implemented now** (closes the gap in `ApplyDeferFinancePolicyAction.php:169-171`). |
| Q-09 | Stuck-work discovery is a **periodic ops routine**; no new alerts required (P-07 accepted as-is). |
| Q-10 | Live pricing **differs from the code seeds**; owner will supply actual figures from Pricing Operations. |
| Q-11 | Scholarship adjustments are **staff-only by design**; no automatic trigger wanted. |
| Q-12 | **Carry-forward only** (exact paid, unconsumed amounts); the flat 15M major-entry credit should be removed (fixes P-12). |

---

*Unresolved from this audit (not read): `DeferCaseService::processFeePolicy()`
internal math; Pricing Operations screens; Vue front-end validation rules
(potential UI-vs-backend contradictions, see Q-06/P-05 precedent).*
