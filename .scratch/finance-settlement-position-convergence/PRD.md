# Finance Settlement Position Convergence

**Status:** ready-for-human
**Predecessor:** [Finance Obligation V2 Migration](../finance-obligation-v2-migration/PRD.md)
**Portal impact:** student
**Primary ADRs:** [ADR-0028](../../docs/adr/0028-finance-charge-is-materialized-ledger-read-model.md), [ADR-0030](../../docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md), [ADR-0026](../../docs/adr/0026-academic-finance-boundary-and-retake-resit-candidate-cutover.md)
**Glossary:** [CONTEXT.md](../../CONTEXT.md)

> This is an umbrella migration PRD. Do not action it as one implementation issue. Create dependency-ordered wave/feature issue packets under this feature directory when a wave is approved.

## 1. Problem

Finance Obligation V2 has established the new write-side architecture, but collection consumers still derive balances independently from settlement-ledger tables such as `finance_charges`, `invoice_lines`, `discount_allocations`, and `payments`.

The problem is not that those tables are obsolete. They are the permanent settlement ledger/read model. The problem is that each consumer can interpret that ledger differently:

- DNG worklists and push amounts can omit applied credit and therefore over-collect.
- `cached_paid_amount` can conflate cash with credit, making “Đã thu” untruthful.
- invoice and staff screens can calculate `total - paid` locally and disagree with one another.
- student DNG endpoints can expand a selected fee-type payment into all pending fee types.
- collection cancellation can be confused with voiding the underlying obligation, charge, or Academic source workflow.
- unknown provider outcomes can be released or retried before the real DNG outcome is known.

This migration converges every authoritative settlement consumer on one Finance-owned Settlement Position contract while preserving historical settlement evidence.

## 2. Outcomes

When complete:

1. Finance has one canonical formula for gross, discount, cash, credit, and remaining collectible.
2. Every authoritative consumer uses that formula through a shared Finance-internal contract; controllers and queries do not calculate balances locally.
3. DNG can never request more than canonical collectible and fails closed when settlement integrity is invalid or provider outcome is unknown.
4. “Đã thu” means cash only. Applied credit is displayed separately and reduces “Còn phải thu”.
5. A student can pay one selected DNG fee type or all active fee types, with exact target breakdown preserved.
6. Cancelling collection is separate from cancelling the underlying obligation/source workflow.
7. Production cutover is atomic at the behavior boundary even though implementation is delivered through multiple small PRs.

## 3. Risk-first delivery order

The migration is ordered by the consequence of failure, not by UI surface. A lower-priority wave cannot activate while a higher-priority money-safety control is incomplete.

| Priority | Hidden risk | Required control | Owning wave |
|---|---|---|---|
| P0 | Existing settlement derivation clamps inconsistent signed evidence into plausible totals | Read raw signed sums first, validate them, and only then derive display amounts; the current `SettlementService` is characterization input, not the canonical implementation | 0 |
| P0 | A verified payment arrives during or after DNG cancellation | Attributable provider-confirmed cash always enters the canonical Payment ledger; cancellation moves to paid cash disposition instead of skipping the receipt | 1 |
| P0 | Provider receives an amount different from the requested amount | Separate provider receipt capture from target allocation; record attributable cash at its actual amount, allocate only the safe portion, and surface the difference | 1 |
| P1 | Payer-level `settlement_version` changes because of an unrelated target | Reservation also captures a target fingerprint; version drift triggers target revalidation but never discards verified cash | 1 |
| P1 | A current-only position is reused for historical reporting | Define current and as-of semantics; historical reports use an as-of Settlement Position or the canonical signed-ledger timeline | 0 contract, 5 consumers |
| P1 | Float/currency/rounding differences change collectible or DNG amount | Use an explicit Money contract, currency, scale, comparison tolerance, and provider-boundary rounding rule | 0 |
| P1 | Shadow comparison uses a known-wrong consumer formula or races concurrent writes | Compare raw ledger evidence and canonical output at the same settlement version/snapshot, with a stable issue catalog | 0 |
| P1 | DNG identity changes across campus/provider rail or a timeout retry creates a second record | Scope active slots and idempotency keys to provider rail/campus; reuse deterministic `ItemId` for the same reservation | 1 |
| P2 | New local formulas appear during a long migration | Land an architecture guard with an explicit legacy allowlist in Wave 0, then shrink the allowlist to zero | 0–6 |
| P2 | Canonical reads or payer locks are too expensive at production volume | Add query-count, index, latency, lock-wait, and batch-size gates before each consumer wave | 0–5 |

### 3.1 Receipt truth precedes allocation truth

A verified provider receipt and its allocation are two different decisions:

1. **Receipt capture:** determine whether the provider evidence can be attributed to one Billing Account and record the actual cash received exactly once.
2. **Target allocation:** determine whether that cash can safely settle the reserved payable targets at their current Settlement Position.

Failure of target allocation never deletes or ignores a verified receipt. Attributable but unallocatable cash becomes **Còn dư** and opens an exception. Evidence that cannot safely identify a Billing Account enters an unmatched provider-receipt queue; Finance never guesses the payer from amount, fee type, or timing.

## 4. Non-goals

- Replacing or deleting `FinanceCharge` / `InvoiceLine` as the settlement ledger.
- Reworking the Finance Obligation V2 write architecture.
- Allowing multiple unpaid active DNG requests for the same provider rail/campus, Billing Account, and fee type.
- Adding an applied-credit cache before measurement proves it necessary.
- Inferring missing DNG links from amount, fee type, or timing.
- Automatically refunding or reallocating cash when a paid obligation is later voided.
- Automatically creating a new collection plan after credit reversal.
- Restoring an old local formula as a fallback or kill-switch behavior.

## 5. Canonical Settlement Position contract

Finance owns the full contract. Cross-context consumers receive only the narrow adapter they require, such as clearance state; they must not query Finance tables directly.

### 5.1 Input

The caller selects a business scope using exact identifiers, for example Billing Account, invoice, obligation, payable line, installment, or an exact set of payable lines. The reader owns validation and aggregation within that scope.

### 5.2 Output

The canonical response must expose enough information for collection and truthful UI without forcing consumers to recalculate:

- scope identity and Billing Account identity;
- position mode (`current` or `as_of`) and captured timestamp;
- current `settlement_version`;
- normalized currency and scale;
- validity state and stable integrity issue codes;
- raw signed components used for integrity validation;
- gross amount;
- discount applied;
- cash applied;
- credit applied;
- remaining collectible;
- settlement state;
- exact payable-line breakdown using the same components.

Money components retain their signed ledger evidence. An inconsistent position is returned as invalid with issue evidence; it is not silently clamped into a plausible amount.

### 5.3 Money semantics

- Every money component carries `amount`, `currency`, and scale through a shared Money DTO. Application code does not use binary floating point as the contract representation.
- VND is the only supported settlement currency for this migration. A scope containing mixed or missing currency is invalid.
- Ledger persistence remains decimal-compatible. Provider payload conversion happens once at the DNG boundary using a documented rounding rule verified against the provider contract.
- The `0.01` tolerance is for drift/integrity comparison only; it does not authorize changing, dropping, or treating two business amounts as equal when their normalized values differ.
- Aggregate and breakdown amounts must reconcile exactly after normalization; any rounding remainder is assigned by a deterministic rule and retained in the breakdown.

### 5.4 Current and historical semantics

- A **current Settlement Position** reads all committed ledger evidence effective at the captured settlement version.
- An **as-of Settlement Position** includes only evidence effective at or before the requested timestamp and returns that timestamp/version in its identity.
- As-of inclusion uses explicit business timestamps for charge effectiveness, payment receipt/application, discount allocation/release, credit application/reversal, void, and refund. `created_at` is not silently treated as business time when a more specific timestamp exists.
- Historical evidence without a reliable effective timestamp is returned as an as-of integrity issue or excluded under an explicit documented legacy rule; the reader never guesses chronology.
- Historical collection, aging, and period-close reports must use an as-of position or the canonical signed-ledger timeline. They must not recompute a prior period using the current position.
- Audit/history may expose raw evidence directly, but any reported balance must declare whether it is current or as-of.

### 5.5 Raw evidence, derivation, and invalidity

- `InvoiceLine` / Payable Line is the atomic settlement unit.
- The reader loads raw signed payment, discount, and credit application sums before applying any `max`, `min`, or display clamp.
- Validation runs on raw evidence. Display-safe derived values are produced only for a valid position; an invalid position retains the raw components and issue evidence.
- The contract, not the consumer, aggregates lines to invoice, fee type, account, or other business scope.
- If any requested target is invalid, the requested aggregate is invalid.
- A batch reader is a transport optimization only and must be parity-tested against single-scope reads.
- Audit/history screens may expose raw evidence, but any authoritative balance or total must come from Settlement Position.

### 5.6 Initial integrity catalog

Wave 0 maps Settlement Position issue codes to the existing Finance invariant registry instead of inventing a disconnected error vocabulary. At minimum it covers over-allocation, cache drift, missing/duplicate active payable lines, settlement evidence on void lines, reversed discount/credit residue, negative raw remaining, DNG header/breakdown drift, paid-but-unbridged DNG, currency mismatch, and unmatched provider receipts.

Each code declares `blocking` or `warning` severity. Any blocking issue on a target invalidates its requested aggregate. Credit applications are included in the balance invariants; existing INV definitions that predate the credit ledger are updated before shadow-read evidence is accepted.

### 5.7 Consumer behavior on invalid data

- Money-moving actions are blocked.
- Staff sees “Cần kiểm tra” plus stable repair evidence.
- Students see a neutral unavailable message without internal details or an untrusted amount.
- No consumer falls back silently to `total - paid` or another legacy formula.

## 6. Settlement mutation protocol

All commands that can change collectible, allocations, payments, credit application, installment state, or DNG collection state pass through a short payer-level Settlement Mutation Guard. Direct model/query writes to these money-moving tables are migration inventory items and remain on an explicit, shrinking architecture allowlist until rerouted.

- The guard serializes by Billing Account.
- Every guarded mutation increments monotonic `settlement_version`.
- External calls never hold a database lock.
- Provider-facing collection uses: reserve locally in a short transaction, call DNG outside the transaction, then finalize in another short transaction.
- A reservation records its provider rail/campus, deterministic `ItemId`, target lines/installments, captured payer settlement version, and a target fingerprint derived from exact target identities and canonical components.
- One logical reservation always reuses the same provider idempotency key. A new `ItemId` is forbidden until the prior attempt has a confirmed terminal outcome.
- Finalization verifies the reservation, version, and target fingerprint. If only unrelated payer state changed, it revalidates the exact targets rather than failing solely on the payer version.
- A verified provider receipt is captured exactly once even when target revalidation fails. Safe allocation is attempted separately; otherwise attributable cash becomes **Còn dư** and the target remains under review.
- Timeout or ambiguous provider response becomes Unknown Collection Outcome; it is held for reconciliation and is not blindly released or retried.

Persistent collection holds are scoped to linked line/installment targets, not the whole payer. General automatic allocation skips held targets. A webhook with exact provider correlation may settle its held target.

## 7. DNG behavior

### 7.1 Active request model

- At most one unpaid active DNG request exists per provider rail/campus, Billing Account, and DNG fee type.
- A campus transfer or provider-rail change cannot create a new active slot while an old-rail request remains unresolved. Staff must first obtain a confirmed terminal outcome or complete an explicit provider-supported migration.
- A request for one fee type may aggregate multiple exact eligible due targets of that type.
- The request retains an exact target breakdown; its total is derived from that breakdown.
- An arbitrary `amount_override` is not supported. Partial collection is modeled through an Installment Plan.

### 7.2 Student payment choices

- “Thanh toán khoản này” sends only the selected fee type.
- “Thanh toán tất cả” sends all active fee types.
- If any selected target is invalid or held, pay-all fails closed as a whole.
- Valid fee types remain independently payable one at a time.
- Pay-all preserves each fee type's request identity and breakdown. The DNG contract must prove whether one access request yields one or several payment transactions and how each transaction correlates back to exact local requests; Finance never splits a single provider receipt across fee types by guess.

### 7.3 Provider receipts, mismatches, and reconciliation

- Checksum/authenticity validation, payer correlation, and amount/target validation are separate steps.
- When provider evidence identifies one Billing Account and one logical request but the actual amount differs, Finance records a canonical Payment using the actual amount exactly once.
- Allocation is capped by the revalidated target collectible. Surplus remains **Còn dư**; a shortfall leaves the unpaid remainder collectible only after the provider request is confirmed terminal or replaced through the normal collection flow.
- A payer, campus, `ItemId`, or fee-type ambiguity is never assigned by amount similarity. It enters the unmatched provider-receipt queue with raw immutable evidence.
- A mismatched or late receipt marks the affected request/targets “Cần kiểm tra” and blocks recollection until reconciliation completes.
- Provider reconciliation is idempotent across webhook, daily check, manual lookup, and staff-confirmed evidence. All routes converge on the same receipt-capture command.

### 7.4 Credit and installments

- Applying or reversing credit calls the canonical installment reconciliation behavior.
- Pending installments may be reconciled to canonical collectible.
- Awaiting-provider and paid installments are not rewritten.
- If committed collection exceeds collectible, collection is blocked and marked “Cần kiểm tra”; already-pushed DNG is not silently edited.
- Credit reversal with insufficient pending capacity requires a staff-confirmed new collection plan and due date.
- Approved credit entitlement can coexist with a live unpaid DNG request, but application is blocked until staff resolves the collection conflict.
- Credit approved after a fee has been paid does not rewrite history automatically; release, reallocation, or refund is an explicit workflow.

### 7.5 Cancellation is not void

Default DNG cancellation cancels only the collection request and releases its hold after a confirmed outcome. It never voids the linked obligation, charge, or source workflow.

When a source/obligation is genuinely voided:

| DNG state | Required behavior before Finance void completes |
|---|---|
| No request | Void normally. |
| Reserved, not attempted | Release reservation, then void. |
| Pushed, confirmed unpaid | Provider-confirm cancellation, then void. |
| Unknown outcome | Reconcile and block terminal cancellation. |
| Paid | Bridge the actual verified cash into canonical `Payment`, preserve history, then follow an explicit cash release/reallocation/refund policy. |
| Aggregate request has other obligations | Cancel the aggregate request, void the target obligation, and explicitly create a replacement for remaining eligible targets. |

Lifecycle-admin exception: when an authorized Academic action moves a student to
`deferred`, `dropout`, or `dropout_transfer` and the linked unpaid charges are
being voided, Swinx closes the unpaid DNG request locally as `cancelled`. This
path does not call the DNG cancellation API or wait for provider confirmation,
because the provider-side row is not authoritative for payment. Provider
identifiers and original push evidence remain intact, and any later verified
receipt is still captured into canonical `Payment` without reviving collection.

Cancellation confirmation is not allowed to erase later verified payment evidence. If cash is confirmed during or after cancellation, Finance captures and bridges it, changes the cancellation operation to the paid-disposition path, and preserves both cancellation and payment evidence. A local cancelled status must never cause a valid provider receipt to be skipped.

For cancellation workflows that require provider coordination, the source context enters Finance-Pending Cancellation. Finance persists a durable, idempotent Finance Cancellation Operation and emits an outbox completion event. Academic or another source context reaches its terminal state only after consuming that completion event. Polling may support presentation but is not the correctness mechanism. The lifecycle-admin exception above is local and atomic with defer settlement, so it does not enter this provider-coordination workflow.

### 7.6 Paid obligation void and cash disposition

Voiding a paid obligation releases its payment applications from the voided payable lines and leaves the real cash as student **Còn dư** by default. It does not silently reallocate, refund, or forfeit that cash.

Staff then starts one explicit, independently authorized workflow:

- allocate or reallocate the surplus to another eligible obligation after preview and confirmation;
- record a real refund with its external evidence; or
- forfeit/retain the cash under an applicable non-refundable policy, with an explicit reason and approval permission.

A source-side `acknowledge_no_refund` checkbox is not sufficient to choose or execute cash disposition. DNG, Payment, and application/release history remain immutable evidence of what occurred.

## 8. Delivery waves

Implementation is split for reviewability and bisectability. Production activation remains gated as described in section 9.

| Wave | Scope | Exit outcome |
|---|---|---|
| 0 | Money DTO/currency/rounding contract; raw signed Settlement Position reader; current/as-of semantics; stable issue catalog mapped to Finance invariants; single/batch APIs; same-version shadow runner; architecture guard with legacy formula/write allowlists; representative correctness and performance fixtures | One non-clamping formula and one integrity vocabulary exist; new local formulas/writers are blocked immediately; no UI cutover required yet. |
| 1 | Settlement Mutation Guard/version and target fingerprint; reservations/holds/idempotency; canonical provider receipt capture; mismatch/unmatched queues; DNG worklist/push amount; selected-fee/pay-all contract; cancellation-payment race handling; obligation-void coordination; provider rail/campus slot identity; active-request migration | Verified cash cannot be lost, DNG cannot over-collect or duplicate a logical request, and all live DNG behavior is cut over atomically. |
| 2 | Make `cached_paid_amount` cash-only; rebuild affected invoice caches; align writer/readers with INV-2; keep credit live-derived; prove zero cache drift before activating cache consumers | Paid semantics are truthful and cache migration cannot expose mixed old/new meaning. |
| 3 | Staff invoice detail and breakdown | UI shows gross, discount, cash, credit, and remaining separately. |
| 4 | Staff lists, Student 360, operations worklists, and student API summaries | Remaining operational consumers stop using local arithmetic. |
| 5 | Current reports, Fee Monitor, exports, and historical/as-of reports; production-volume benchmarks and indexes for each consumer class | Every report declares current/as-of semantics and reconciles to canonical evidence without violating latency/lock budgets. |
| 6 | Shrink architecture allowlists to zero; delete scattered helpers/SQL formulas and temporary shadow compatibility code | No consumer or writer bypass remains and CI prevents reintroduction. |

Wave 1 may be delivered through multiple PRs, but none may activate only part of the new DNG correctness model in production.

## 9. Production cutover gates

The DNG production switch remains off until all gates pass:

1. Every active DNG request has exact payer, fee-type, payable-line/installment, and provider correlation.
2. Every active slot is unique within provider rail/campus + Billing Account + fee type, including students whose campus/provider rail changed.
3. There are zero unresolved active requests. Unknown outcomes are reconciled rather than guessed.
4. Every logical reservation has one deterministic provider idempotency key, and retries are proven to reuse it without creating a second DNG record.
5. There are zero paid-but-unbridged attributable receipts. If an exact payable target cannot be proven, verified cash is still bridged to canonical `Payment` as “Còn dư”, and recollection remains blocked for review. Receipts without safe payer identity remain visible in the unmatched provider-receipt queue and block the correlated provider scope.
6. Shadow reads cover 100% of active DNG requests plus every Billing Account with a settlement mutation in the preceding 90 days. Comparisons use raw ledger evidence and canonical output at the same settlement version/snapshot, and show zero unexplained difference for seven consecutive days; no legacy consumer formula is used as the oracle.
7. All blocking Settlement Position/integrity codes in the migration population are zero or attached to an explicit unresolved exception that prevents activation for its scope. Balance invariants include credit applications.
8. Single-reader and batch-reader parity tests pass for both current and as-of modes.
9. Receipt-capture, reservation/version/fingerprint race, late webhook, cancellation/payment race, retry/idempotency, and cancellation-operation tests pass.
10. Student portal proves selected fee type versus pay-all behavior against the backend contract, including provider transaction-to-request correlation.
11. Operational dashboards expose invalid positions, unmatched/mismatched receipts, unknown outcomes, stale reservations, bridge failures, and cancellation operations awaiting completion.
12. DNG provides a reliable lookup or confirmation procedure correlated by `ItemId` for ambiguous push and cancellation outcomes. If no provider API is available, a documented staff-to-DNG reconciliation procedure must produce equivalent confirmed evidence; unresolved outcomes remain held and block recollection.
13. Production-volume benchmarks meet issue-defined query-count, batch-size, p95 latency, queue duration, and payer-lock wait budgets. Required indexes are present and verified with representative query plans.
14. Schema, workers, webhooks, scheduled reconciliation, and the currently deployed app version remain backward/forward compatible throughout deployment. Rollback disables new collection creation but keeps receipt capture and reconciliation operational.

The kill switch stops only new reservation, push, replacement, and student payment-access creation. Webhooks, paid bridging, reconciliation, and cancellation completion remain active. It never routes traffic back to a legacy balance formula.

### 9.1 Staff exception workspace and permissions

`Finance → Operations → Exceptions` is the common staff inbox for invalid Settlement Positions, Unknown Collection Outcomes, stale reservations, paid-but-unbridged DNG requests, and incomplete Finance Cancellation Operations. Each row drills into the relevant DNG request or settlement detail with evidence and permitted resolution actions.

Permissions are intentionally separated:

- `view_finance_operations_exceptions` can inspect evidence only;
- `resolve_finance_settlement_exceptions` can reconcile/confirm DNG outcomes and create a staff-confirmed replacement collection plan;
- `void_finance_charges` remains independently required for a real obligation/charge void;
- payment allocation, reallocation, and refund permissions remain independent from exception resolution.

`create_finance_payments` must not remain the catch-all permission for DNG cancellation, reconciliation, or exception repair.

## 10. Acceptance scenarios

At minimum, automated fixtures and integration tests cover:

- gross with no settlement;
- discount only;
- partial and full cash;
- partial and full credit;
- mixed discount, cash, and credit;
- cash or credit reversal;
- inconsistent signed evidence returning invalid rather than clamped;
- raw overpayment, over-discount, and over-credit evidence remaining visible with blocking issue codes;
- VND normalization and provider-boundary rounding, including a deterministic aggregate remainder;
- aggregation containing one invalid line;
- current and as-of positions diverging correctly after a later payment/reversal/refund;
- concurrent credit application and DNG reservation;
- unrelated payer mutation changing settlement version while reserved targets remain unchanged;
- target mutation changing both settlement version and target fingerprint before finalize;
- provider timeout followed by a late success webhook;
- payment arriving concurrently with cancellation and after local/provider cancellation state changes;
- verified underpayment and overpayment recording actual cash before safe allocation;
- attributable but unallocatable cash becoming “Còn dư”;
- ambiguous payer/campus/`ItemId` entering unmatched provider receipts without a guessed Payment;
- duplicate webhook and duplicate command retry;
- repeated provider attempt reusing the same deterministic `ItemId`;
- campus/provider-rail change while an old active slot exists;
- one fee type selected while another is also pending;
- pay-all with one invalid or held fee type;
- pay-all provider response correlating every transaction back to exact fee-type requests;
- aggregate DNG cancellation and replacement for remaining targets;
- source cancellation in every DNG state from section 7.5;
- paid DNG bridge with and without an exact payable target;
- paid-obligation void releasing cash to “Còn dư” without implicit reallocation, refund, or forfeiture;
- each explicit surplus disposition preserving the original Payment/DNG history;
- cash-only `cached_paid_amount` parity;
- cache rebuild and mixed-version deployment leaving no consumer with the old cash-plus-credit meaning;
- architecture guard rejecting a newly introduced local formula or direct money-table writer outside the allowlist;
- representative single/batch read performance and payer-lock contention staying within issue-defined budgets;
- staff and student invalid-state presentation.

## 11. Migration inventory and issue creation

Before each wave is marked ready-for-agent:

1. Inventory every controller, query, action, report, export, and portal consumer in that wave.
2. Record its current formula and exact target scope.
3. Map it to the canonical contract or an approved narrow adapter.
4. Add characterization tests before removing local arithmetic.
5. Identify every direct writer to settlement, application, installment, cache, DNG, and Billing Account version state; add it to the temporary architecture allowlist or reroute it through the guard.
6. Record whether the consumer is current, as-of, or raw-audit and its currency/rounding requirements.
7. Define representative data volume plus query-count, latency, batch-size, and lock-wait budgets.
8. Create dependency-clean issue packets small enough for one implementation agent.
9. Record rollout, cache/data backfill, observability, worker compatibility, and rollback/kill-switch checks in the issue packet.

The umbrella PRD is complete only when the inventory is empty and the architecture guard prevents reintroduction.

## 12. Known implementation evidence to verify in wave issues

The first issue packets must re-verify these current-code findings before editing:

- current settlement derivation reads cash, discounts, and credit applications but clamps raw evidence with `max`/`min`; it is a characterization source and must not be adopted unchanged as the canonical reader;
- existing Finance invariant codes predate some credit-ledger and provider-receipt cases and require an explicit Wave 0 mapping/update;
- DNG worklist/push paths currently contain local arithmetic that omits credit;
- the current paid cache writer includes credit despite the cash-only target meaning;
- the per-request student DNG endpoints currently resolve all pending fee types rather than only the selected request type;
- collection cancellation and obligation/source cancellation are currently conflated in some actions;
- lifecycle defer/dropout cancellation intentionally closes unpaid DNG locally; other obligation cancellation paths still require their issue-defined provider coordination;
- current DNG webhook/reconciliation paths can treat cancelled requests as terminal and skip a later paid callback;
- current callback validation can stop before Payment bridging when the provider amount differs;
- Billing Account is student-keyed while DNG correlation also includes provider campus, so active-slot migration must handle campus/rail changes explicitly.

These observations explain migration priority; the wave issue packet must cite exact current files and tests because code may change before that wave begins.

## 13. Decision status

The architecture and risk controls required to decompose Wave 0 are resolved in this PRD. A wave issue is not `ready-for-agent` until its provider-specific behavior, Money normalization, integrity-code mapping, performance budget, deployment compatibility, and operational permissions are concrete and remain within these invariants. Any discovery that would permit dropping verified cash, clamping inconsistent evidence, guessing payer/target identity, or restoring local settlement arithmetic returns for explicit review.
