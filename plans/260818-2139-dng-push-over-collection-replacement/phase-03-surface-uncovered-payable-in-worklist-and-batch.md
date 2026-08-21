---
phase: 3
title: "Surface uncovered payable in worklist and batch"
status: done
priority: P2
effort: "0.5-1d"
dependencies: [1]
---

# Phase 3: Surface uncovered payable in worklist and batch

## Overview

A student whose payable exceeds their live collection is hidden from the batch
worklist rather than shown as needing a top-up. Phase 1 makes the replacement
possible; this phase makes the students who need one findable.

## Root cause

Nothing in Finance has a notion of "payable not covered by any live
collection". `grep -rn "uncovered\|coverage_gap\|shortfall\|not_covered"
app/Modules/Finance/` returns nothing.

`ListDngWorklistQuery` already carries both numbers on the same row — the
settlement `balance` and `active_dng['amount']` — but never subtracts one from
the other. `AssembleBatchDngPreviewQuery` reduces the question to a boolean:

```php
'diff' => $hasActiveDng ? 'skip' : 'create',
```

So a student with a live DNG is skipped whether their collection covers
3.000.000 of a 3.000.000 payable or 3.000.000 of a 6.000.000 one. The second
disappears.

The operator-facing message is fine — `BatchStudioController` throws a real
Vietnamese sentence. The bare code string `active_dng_resolution_required` lives
in the preview query's `display`/`warning_codes`, which is where the wording
work belongs.

## The scope trap (red team H13)

The two numbers are computed over **different sets**, so subtracting them is
wrong today and would stay wrong if copied naively:

| | Scope |
|---|---|
| `balance` | semester-scoped — but `semester_id` on the worklist request is **nullable and defaults to null** |
| `active_dng` lookup | `student_id` + `fee_type` + `holdingCollection()` — **no** semester, **no** campus, **no** `provider_rail`, winner picked by `orderByDesc('created_at')` |
| what `reserve()` would actually collect | lines whose charge is in `$details['semester_id']`, for one campus and rail |

Two concrete failures if the mismatch is not fixed first:

- Student has a live semester-3 collection of 3.000.000 and a fresh semester-4
  payable of 3.000.000. The semester-4 worklist computes `uncovered = 0` →
  `skip` → a legitimate new collection is blocked.
- Worklist opened with no semester filter (the default). Student has 3.000.000
  unpaid in each of semesters 3 and 4 with one live 3.000.000 collection.
  `uncovered = 3.000.000` → `replace`. The commit pushes for one semester only,
  replacing the other's collection. Coverage is unchanged, the row reappears as
  `replace`, and every commit burns another provider round trip. It never
  converges.

**So: scope the coverage arithmetic to the same `semester_id` and
`campus_code` the push would target — see the correction note directly below
for why the *contention* lookup itself must stay unscoped — and order by
`id desc` (not `created_at`, which is second-resolution and ties
non-deterministically).** This is a prerequisite, not a nice-to-have.

> **Implementation correction (code review, post-first-pass):** the
> prescription above is unsafe applied to the *contention* lookup (does a
> live request exist at all). `active_slot_key` has no semester component
> (red-team C5) and `DngReservationLifecycle::reserve()`'s own `$existing`
> lookup filters only by `billing_account_id` + `provider_rail` + `fee_type`
> — **no `semester_id`, no `campus_code`**. Scoping the worklist's contention
> query by `semester_id` therefore makes an other-semester live request
> invisible to the worklist while it still blocks `reserve()` — first-pass
> code shipped exactly the semester-4 "worklist computes `uncovered = 0` →
> `skip`" failure this section warns about, inverted into "computes `create`"
> and verified to degrade a healthy other-semester collection on commit.
> Correct split: **the contention query stays unscoped by semester** (student
> + `fee_type` + `provider_rail`, matching `reserve()` exactly); `semester_id`
> (and the resolved DNG `campus_code`) scope only the *coverage arithmetic* —
> when the found live request's own `semester_id`/`campus_code` don't match
> the semester being previewed, coverage is not computable and the row must
> render `blocked`, never `create` or `replace`.

## Requirements

- Functional: compute uncovered per student per fee type, over the exact line
  set `reserve()` would target.
- Functional: three preview states — nothing to do, replacement needed,
  blocked.
- Functional: fail closed when coverage cannot be computed.
- Functional: `semester_id` is mandatory before any `replace` is offered.
- Functional: batch commit records the acting user and requires explicit
  acknowledgement of the replacement count.
- Non-functional: no N+1.

## Architecture

```
uncovered = Σ remaining(payable lines for fee_type, semester, campus)
          − Σ captured_collectible(reservation targets of the live request)
```

| State | Condition | Operator sees |
|---|---|---|
| `create` | no live collection | as today |
| `replace` | live collection, `uncovered > 0`, coverage computable | "Còn X chưa nằm trong lệnh thu — sẽ hủy lệnh cũ và đẩy lệnh mới gồm toàn bộ" |
| `skip` | live collection, `uncovered == 0` | "Đã có lệnh thu đủ" |
| `blocked` | coverage **not** computable | "Không xác định được phần đã thu — cần kiểm tra thủ công" |

Only `create` and `replace` may commit.

### Fail closed on uncomputable coverage (H14)

Two classes of live request have **no reservation targets**, so the right-hand
sum is 0 and `uncovered` would equal the entire payable — driving a duplicate
push:

- requests pushed before 2026-07-11, when
  `dng_payment_request_reservation_targets` did not exist
- rows from `createCancellationReplacement`, which write charge links only

Both must render `blocked`, never `replace`. Use the reservation targets, not
the request's `amount` column — `amount` is a snapshot that does not track
partial settlement.

### Authorization and blast radius (H16)

Committing a batch of `replace` lines cancels N live provider records. Today
that needs only `create_finance_payments` — the same permission as a first-time
push — while cancelling a *single* request has a dedicated `cancel-impact`
preview and a separate `cancel-reviewed` endpoint. The smaller action has the
larger ceremony.

Minimum for this phase: record `actor_user_id` on each replacement, show the
replacement count in the preview, and require explicit acknowledgement of that
count at commit. The preview-token round trip already exists to hang it on.

## Related Code Files

- Modify: `app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php` — active-
  DNG lookup scoped to `provider_rail` (not semester/campus — see the
  correction note above); `orderByDesc('id')`; add `uncovered_amount` and a
  `coverage_known` flag to the row, computed with semester/campus scoping.
- Modify: `app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php` —
  four-way `diff`; carry `uncovered` in `hashPayload` so a coverage change
  invalidates a stale preview token.
- Modify: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php` —
  block `skip` and `blocked`; require `semester_id`; require the replacement
  acknowledgement.
- Modify: `app/Modules/Finance/Http/Requests/Batch/CommitBatchDngRequest.php` —
  validate the acknowledgement field.
- Modify: the Batch Studio preview component under `resources/js/pages/Finance/`
  — render the two new states.
- Modify: `tests/Feature/Finance/BatchDngInstallmentAwareTest.php`.
- Create: a query test for the uncovered computation.

## Implementation Steps

1. Failing test: student with two `exam_resit_fee` charges of 3.000.000 in one
   semester and a live DNG covering 3.000.000 → `uncovered_amount` is
   3.000.000, `diff` is `replace`. (AUH15442's exact shape.)
2. Failing test: fully covered → `skip`, still blocks commit.
3. Failing test: live request with no reservation targets → `blocked`, never
   `replace`.
4. Failing test: live collection in a **different semester** does not reduce
   this semester's uncovered.
5. Scope the active-DNG lookup and switch the tiebreak to `id desc`.
6. Add `uncovered_amount` + `coverage_known`, reusing the already-loaded
   settlement position — no extra per-row query. Note the current lookup selects
   only `['id','student_id','status','amount','created_at']`, so the reservation
   targets it needs must be joined or eager-loaded deliberately.
7. Widen `diff` to four values; add `uncovered` to `hashPayload`.
8. Require `semester_id` before offering `replace`.
9. Add the actor id and the replacement-count acknowledgement.
10. Update the preview UI.
11. Assert query count on a realistic worklist page.

## Test / Validation Gate

Run individually, resetting `db_test` first (command in Phase 1):

- new uncovered-computation query test
- `tests/Feature/Finance/BatchDngInstallmentAwareTest.php`
- `tests/Feature/Finance/DngAdminPagesTest.php`
- `tests/Feature/Finance/DngPaymentRequestPagesTest.php`

Feature tests here enforce CSRF — pass a matching `_token` in both session and
body.

Frontend: `./scripts/dev.sh npm exec -- eslint <changed files>`. Do **not** run
whole-project `type-check`; it OOMs in the dev container.

## Success Criteria

- [x] A partially-covered student appears as `replace` with a correct
      `uncovered_amount` instead of vanishing. (`uncovered_amount` compares
      against the installment-capped `next_push_amount`, not the raw
      semester remaining — see code-review correction below.)
- [x] A fully-covered student stays `skip` and still blocks commit.
- [x] A live request with no reservation targets renders `blocked`.
- [x] A cross-semester live collection does not distort this semester's
      uncovered — renders `blocked`, not `create` (see "Implementation
      correction" note above).
- [x] `replace` is unavailable until `semester_id` is chosen. (Already true
      structurally: `PreviewBatchDngRequest` requires `semester_id`, so the
      batch-commit call path always supplies it.)
- [x] Committing a `replace` line performs Phase 1's cancel-then-push
      end-to-end, and only when `finance.dng.auto_replace_stale_collection`
      is actually on — otherwise renders `blocked`
      (`active_dng_replacement_disabled`), never a `replace` the commit
      can't honor.
- [x] Each replacement records its acting user — already true via Phase 1's
      `DngReservationLifecycle::reserve()`, which passes
      `actor_user_id => auth()->id()` into the replacement context; nothing
      new needed here. The commit shows and confirms the replacement count
      (`needsAck` widened to trigger on `replacementCount > 0`; server-side
      `acknowledged` check in `BatchStudioController::commitDng()`).
- [x] No N+1 introduced (single unscoped contention query + one batched
      `DngCampusMapping` lookup, not per-student).

### Code-review correction (post-first-pass)

A first implementation pass computed `uncovered_amount` against the raw
semester `remaining` balance and offered `replace` regardless of the
`auto_replace_stale_collection` config flag. Both were CRITICAL findings in
review, empirically reproduced against a split-installment plan (tranche 1
live/pushed reads as "still short by tranche 2") and the flag's shipped-off
default (a `replace` commit degrades a healthy live collection to
`needs_review` when the flag is off). Fixed by comparing against
`next_push_amount` (the same installment-capped total `reserve()` actually
targets) and by gating the `replace` vs `blocked` choice on the config flag.
See `ListDngWorklistQuery::coverage()` and
`AssembleBatchDngPreviewQuery::handle()` docblocks/comments for the corrected
logic. `needs_review`/`unknown_outcome` statuses also now always render
`blocked` (never `replace` or `skip`), matching this plan's own Non-goals.

A re-review pass on those fixes found two more, both closed before ship:
`coverage()` also now requires every existing reservation target's
`captured_collectible` to stay `<=` its own line's *current* remaining
(mirrors `DngReservationLifecycle::eligibleForAutomaticReplacement()`'s
per-line guard — a direct non-DNG payment on an already-claimed line was
passing the aggregate uncovered>0 check while `reserve()` itself would
refuse the replacement). And `acknowledged` was never reaching the server at
all: Inertia's `useForm()` only serializes keys present in its *initial*
data, and `wizard.form.acknowledged = v` assigns a key that was never
registered as a default — fixed in `useBatchStudio.ts` by adding
`acknowledged: false` to the form's initial state (this silently affected
the pre-existing >50-row ack for Charge Generation too, but nothing there
depended on it reaching the server until this phase added a server-side
check). Left documented-but-not-fixed: `next_push_amount` (pre-existing,
used elsewhere) sums only charges that have installment rows, so a
same-semester charge with no installment plan is invisible to the coverage
math even when a live tranche-1 request exists — conservative (can only
produce a false `skip`, never a false `replace`), pinned by a regression
test rather than fixed, since fixing it means changing `next_push_amount`'s
own long-standing aggregation, out of this phase's scope.

## Risk Assessment

| Risk | Mitigation |
|---|---|
| `replace` offered before Phase 1 lands → students pushed into `needs_review` | Declared dependency; do not ship Phase 3 alone |
| Uncovered computed from the `amount` snapshot drifts after partial settlement | Compute from `captured_collectible` on reservation targets |
| Legacy request without targets read as zero coverage → duplicate push | Fail closed as `blocked` |
| Cross-semester mixing makes `replace` non-convergent | Scope the lookup; require `semester_id` |
| Preview token staleness lets a changed coverage state commit | `uncovered` in `hashPayload`; `recomputeOrFail` already re-derives at commit |
| `per_page => 200` in the preview assembler silently truncates | Surface the truncation in the preview; out of scope to paginate, but it must not be invisible |
| Rounding between decimal columns and `Money` | Use `Money` comparisons, never float subtraction |
