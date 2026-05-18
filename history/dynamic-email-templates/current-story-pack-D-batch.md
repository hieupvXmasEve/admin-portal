# Current Story Pack: D-batch — P1 cleanup (D1 + D2 + D3)

**Epic:** D. P1 follow-ups carried into P2
**Stories:** D1 (wrong type_key fix) + D2 (cascadeOnDelete → restrictOnDelete) + D3 (test backfill + log hygiene)
**Mode:** `standard_feature` (small_change in aggregate but multi-file)
**Source:** [approach-p2.md](approach-p2.md) §6 + 6 of the 11 deferred_beads in [.khuym/state.json](../../.khuym/state.json)

## Outcome

P1 review queue cleared. Three independent cleanup actions executed in one
commit because they're all small, all in finance-action territory, and all
have zero dependency on each other or on Epic A/B/C.

Closes 7 deferred_beads: REV-P2-01 (D1), REV-P2-03 (D2), REV-P2-07, REV-P2-08,
REV-P2-09, REV-P2-10, REV-P2-11 (all D3).

## Entry State

- **D1 (wrong type_key):** [SendParentPaymentRemindersAction.php:35](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php) resolves `'payment_reminder'` (student-facing) instead of `'parent_payment_reminder'`. Pre-existing bug. Parents receive student-addressed template.
- **D2 (FK constraint):** [2026_05_16_170000_create_notification_email_templates_table.php:23-25](database/migrations/2026_05_16_170000_create_notification_email_templates_table.php) uses `cascadeOnDelete()`. Admin-edited rows are silently dropped if a campus is deleted.
- **D3 (tests + logs):** 5 sub-items:
  - DbEmailContentProvider's `InvalidArgumentException` (missing `campus_id`) + `RuntimeException` (no row) paths untested.
  - 4 finance Action tests don't assert provider was called with `campus_id` in `$data`.
  - `SendDueItemRemindersAction` and `SendDueItemParentRemindersAction` have zero test files.
  - [SendDueItemParentRemindersAction.php:140-151](app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php) catch block has no `Log::error`.
  - [SendDueItemParentRemindersAction.php:55, 87, 97](app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php) use fully-qualified `\Log::` instead of `use Log;`.

## Exit State

### D1
1. `SendParentPaymentRemindersAction:35` reads `->resolve('parent_payment_reminder')`.
2. `$contentData` array in the same Action includes `'parent_name' => $student->parent_name ?? $parent->full_name ?? 'Quý Phụ Huynh'` (use whatever relation surfaces a parent name; fall back to the generic salutation).
3. Existing `SendParentPaymentRemindersActionTest` updated/added to assert the resolved provider is `parent_payment_reminder` AND that the rendered output contains the parent-specific salutation (Dear Quý Phụ Huynh / Thân gửi Quý Phụ Huynh).

### D2
4. New migration `database/migrations/2026_05_*_change_notification_email_templates_campus_fk_to_restrict.php`:
   - `up()`: drop FK on `campus_id`, re-add with `restrictOnDelete()`.
   - `down()`: drop FK, re-add with `cascadeOnDelete()`.
5. `./scripts/dev.sh artisan migrate` runs clean forward; `migrate:rollback --step=1` then `migrate` runs clean.

### D3
6. New `tests/Feature/Notification/EmailContent/DbEmailContentProviderTest.php` cases (append to existing file):
   - **(e)** `expect(fn () => $provider->htmlBody([]))->toThrow(InvalidArgumentException::class)` — missing `campus_id`.
   - **(f)** With no seeded row, `expect(fn () => $provider->htmlBody(['campus_id' => 99999, ...]))->toThrow(RuntimeException::class)` — no row for (type, campus).
7. The 4 finance Action tests (`SendPaymentRemindersActionTest`, `SendParentPaymentRemindersActionTest`, plus the two new SendDueItem* tests below) each include one assertion that the resolved provider was called with `$contentData['campus_id']` matching the test campus. Use `config(['notifications.use_db_templates' => true])` + seed a `NotificationEmailTemplate` row for the test campus + assert the rendered subject/body contains the seeded text (proves campus_id reached the provider).
8. New `tests/Feature/Finance/SendDueItemRemindersActionTest.php` — at minimum 2 happy-path cases (DNG branch + invoice branch).
9. New `tests/Feature/Finance/SendDueItemParentRemindersActionTest.php` — 1 happy-path case.
10. `SendDueItemParentRemindersAction.php`:
    - Add `use Illuminate\Support\Facades\Log;` at top.
    - Replace all `\Log::info|error|warning` with `Log::info|error|warning`.
    - Add `Log::error('Failed to send invoice parent reminder', [...]);` inside the catch block at line 140-151 mirroring the DNG branch pattern at line 93-99.

### Composite
11. `./scripts/dev.sh test tests/Feature/Notification tests/Unit/Notification tests/Feature/Finance` — green; no new regressions in pre-existing failure baseline.
12. `./scripts/dev.sh artisan about > /dev/null` exit 0.
13. `docker exec swinx-app-dev vendor/bin/pint --test <all touched files>` clean.

## Files Likely Touched

| File | Action | Story |
|---|---|---|
| `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php` | EDIT — change resolve key + add parent_name | D1 |
| `tests/Feature/Finance/SendParentPaymentRemindersActionTest.php` | EDIT — assert correct type_key + parent salutation | D1 |
| `database/migrations/2026_05_*_change_notification_email_templates_campus_fk_to_restrict.php` | CREATE | D2 |
| `tests/Feature/Notification/EmailContent/DbEmailContentProviderTest.php` | EDIT — append (e) + (f) | D3 |
| `tests/Feature/Finance/SendPaymentRemindersActionTest.php` | EDIT — add campus_id assertion | D3 |
| `tests/Feature/Finance/SendParentPaymentRemindersActionTest.php` (already edited by D1) | EDIT — add campus_id assertion (same edit) | D3 |
| `tests/Feature/Finance/SendDueItemRemindersActionTest.php` | CREATE — DNG + invoice happy paths | D3 |
| `tests/Feature/Finance/SendDueItemParentRemindersActionTest.php` | CREATE — happy path | D3 |
| `app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` | EDIT — Log import + replace + add Log::error in catch | D3 |

**Total: 5 EDIT + 4 CREATE = 9 file ops across the 3 stories.**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| `$student` has a queryable parent relation or `parent_name` field | MEDIUM | Worker must discover the project's parent model. Likely a `Parent` model belongsTo Student or a `ParentProfile`. Fall back to `'Quý Phụ Huynh'` salutation if neither exists. Acceptable for D1 since the legacy code already falls back to the generic salutation. |
| Migration drop+re-add FK works on MySQL | LOW | Standard pattern. `Schema::table` → `dropForeign(['campus_id'])` → `foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete()`. |
| Adding tests doesn't break the pre-existing 30 finance failures bisected in P1 review | LOW | These tests are NEW; they don't touch pre-existing ones. Pre-existing failures (NotificationOpsControllerTest CSRF + a few Finance) are unrelated. |

## Verification (Done-When)

1. `./scripts/dev.sh test tests/Feature/Notification/EmailContent/DbEmailContentProviderTest.php` → all cases (a-f) green.
2. `./scripts/dev.sh test tests/Feature/Finance/SendPaymentRemindersActionTest.php tests/Feature/Finance/SendParentPaymentRemindersActionTest.php tests/Feature/Finance/SendDueItemRemindersActionTest.php tests/Feature/Finance/SendDueItemParentRemindersActionTest.php` → green.
3. `./scripts/dev.sh artisan migrate:rollback --step=1 && ./scripts/dev.sh artisan migrate` → clean both ways.
4. `git diff HEAD~1 -- app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php` shows the `'parent_payment_reminder'` change.
5. `grep -nE '^\\\\Log::' app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` returns ZERO matches.
6. `grep -nE 'Log::error.*Failed to send invoice parent reminder' app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php` returns 1 match.
7. `./scripts/dev.sh artisan about > /dev/null` exit 0.
8. Pint clean on all touched files.

## Out of Scope

- REV-P2-04 (typed RenderContext) — multi-week interface refactor, out per approach-p2.md.
- Refactoring legacy `EmailContent\Types\*EmailContent.php` classes — they're deletion targets at sunset.
- Touching the `notification_email_templates.campus_id` cascade on the SEED migration — D2 is a follow-up migration, not an edit to the original.

## Bead Mapping

Pending validation. After validating clears the batch, one execution bead.
~60 min for the full batch (3 stories combined). Worker treats D1+D2+D3 as a
unified commit — no inter-dependencies that benefit from splitting.
