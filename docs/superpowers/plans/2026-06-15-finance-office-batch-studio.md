# Finance Office — Batch Studio — Implementation Plan (Milestone 4)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build **Batch Studio** — one shared 4-step wizard (`① Thiết lập → ② Xem trước → ③ Xác nhận → ④ Kết quả`) for every bulk finance job (sinh phí / đẩy DNG / nhắc nợ) on top of a **preview-token safety contract** that makes the preview numbers provably equal to the committed numbers. It reuses 100% of the existing money logic (generation Actions, DNG batch Action, reminder Actions, Preview Queries, and the shared resolvers); it adds **no new money math**.

**Architecture:** Read-then-write with a drift-proof handshake. Step ② calls a **JSON preview endpoint** (`useApi` → `ApiResponse::success`) that delegates to the existing `Preview*Query` classes, normalizes each row into a **canonical preview line** (`key` + `hash_payload` + `display`), and **issues a one-time preview token** (cache, 30-min TTL) holding a **per-line SHA-256** of the resolved payload. Step ④ is an **Inertia write** (`useForm` → `FormRequest` → existing `Action::run()` → `Inertia::flash()->back()`) that sends the token + the exact subset of line keys the operator confirmed; the controller **re-resolves that subset and recompute-compares per line** — any drift blocks the commit. The three jobs share the wizard shell and the token service but keep **job-specific result models**, because their backends differ: charge-gen is a *single-transaction envelope* (per-student catch+skip, one commit), DNG push is *per-student transactions with real partial success* (max 100/request → UI chunks), reminders are *per-recipient with a `last_reminder_at` double-send guard*.

**Tech Stack:** Laravel 13, Inertia v3 (`useForm`, `Inertia::flash`, `Inertia::render`), Vue 3 `<script setup lang="ts">`, Tailwind v4, Ziggy, Pest, `useApi`/`useApiRequest` (JSON reads — never `axios`), `ApiResponse` (JSON writes — never `response()->json()`), `maatwebsite/excel` (`GenerateChargesPreviewExport`), shadcn-vue (`@/components/ui/*` incl. `card`, `badge`, `button`, `dialog`, `checkbox`, `progress`, `table`), `lucide-vue-next`.

**Depends on:** Milestone 1 (shell, `financeRoutes` + `FINANCE_ROUTE_NAMES`, shared `semester` prop, `view_finance_all_campus`), Milestone 2 (reused destructive-action patterns + `Sheet` drawers + Student 360 deep links), Milestone 3 (Cockpit — the "chọn nhiều dòng → thao tác hàng loạt" hand-off into Batch Studio; M3 should be merged first so the queue-to-batch link has a target). M1+M2 are merged; M3 precedes this milestone.

**Story:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (`FIN-REV-010`). Design source: `docs/features/finance/finance-office-ux-redesign-design.md` §6 (Batch Studio), §7.1 pattern 3 (Preview→Confirm→Result), §8 (frontend contracts), §10 milestone 4. **Portal impact: none** — Finance Office web console only; does not touch `/api/v1/student/*` or `/api/v1/lecturer/*`.

**Risk lane:** **high** (`authz`, money-write orchestration, drift-safety correctness). Batch Studio does not rewrite money math, but it *triggers* the real write Actions in bulk, so the hard gates are non-negotiable:
- **Per-action permissions, no umbrella "batch" permission** (§8): generation gates `create_finance_charges` / `generate_egc_finance_charges`; DNG push gates `create_finance_payments` **and** `void_finance_charges` *when a rerun can cancel a DNG that has linked charges*; reminders gate the operations/due-calendar permission. The hub landing uses only a thin **view** permission.
- **Preview-token recompute-compare blocks every commit** — never commit from a stale snapshot; any per-line drift → 422/validation error and forced re-preview.
- **Override = ad-hoc warning** — a DNG amount override that differs from the computed installment-aware total by ≥1 VND drops installment linkage (manual reconcile); the UI must surface this as danger, not convenience.
- **Rerun reassurance is per-job** — generation rerun is safe-on-skip (not concurrency-safe → disable while running); DNG rerun *cancels old DNG*; reminder rerun *re-sends* (block via `last_reminder_at`).
- **Atomicity is per-job** — generation result = single-transaction-envelope summary; DNG/reminders = per-recipient partial success with **retry-only-the-failed-subset**.

---

## Testing approach (same as M1/M2/M3 — read first)

Backend = TDD with Pest (`./scripts/dev.sh test <path>`), using the proven finance pattern: mock `PermissionService::getUserPermissions` to return the permissions under test, set `session(['current_campus_id' => $campus->id])` + `app()->singleton('campus', fn () => $campus)`, then `assertInertia` / `assertJsonPath`. A reusable `grantFinance(User $user, array $perms, Campus $campus)` helper is defined in **Task 0** and used by every backend test below.

Frontend = `./scripts/dev.sh npm run lint -- <files>` + browser smoke. There is **no JS unit runner**; do **not** run whole-project `vue-tsc` in the dev container — it OOMs (per project memory). Type-check edited files only via eslint or on the host/CI.

**Money-state evidence:** Batch Studio *does* exercise money writes (generation, DNG push). The final evidence task (Task 15) runs `./scripts/dev.sh artisan finance:audit-invariants` **after** exercising each write path and confirms zero new CRITICAL invariant breaks — this is the milestone acceptance gate.

---

## File Structure (M4)

**Created — backend safety core (shared by all 3 jobs):**
- `app/Modules/Finance/Support/Batch/BatchPreviewLineHasher.php` — pure canonical per-line SHA-256 + fee-config fingerprint (no `version` column → `config_id`+`updated_at`).
- `app/Modules/Finance/Services/Batch/BatchPreviewTokenService.php` — cache-backed `issue` / `verify` / `consume` (one-time, 30-min TTL, per-line).
- `app/Modules/Finance/Support/Batch/BatchPreviewLine.php` — the canonical line value object (`key`, `hashPayload`, `display`).
- `app/Modules/Finance/Support/Batch/BatchJobType.php` — enum: `charge_generation` / `dng_push` / `reminder`.

**Created — backend charge-generation job:**
- `app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php` — calls the right existing `Preview*Query` per `fee_category` and maps to canonical lines + token payload.
- `app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php`
- `app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php`
- `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php` — `hub` (landing) + `commitCharges` / `commitDng` / `commitReminders` (Inertia writes).
- `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php` — `previewCharges` / `previewDng` / `previewReminders` (JSON reads).

**Created — backend DNG + reminder jobs:**
- `app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php` — reuses `ListDngWorklistQuery` rows + installment-aware totals.
- `app/Modules/Finance/Queries/Batch/AssembleBatchReminderPreviewQuery.php` — reuses due/overdue rows + `last_reminder_at` guard flags.
- `app/Modules/Finance/Http/Requests/Batch/PreviewBatchDngRequest.php`, `CommitBatchDngRequest.php`
- `app/Modules/Finance/Http/Requests/Batch/PreviewBatchRemindersRequest.php`, `CommitBatchRemindersRequest.php`

**Modified — backend:**
- `config/permission.php` + `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` — add `view_finance_batch_studio`.
- `app/Modules/Finance/routes/web.php` — register batch-studio hub + 3 commit routes (per-action `can:`).
- `app/Modules/Finance/routes/api.php` — register 3 JSON preview routes (per-action `can:`).

**Created — frontend:**
- `resources/js/pages/Finance/BatchStudio/Hub.vue` — landing; renders only the job tiles the operator may run.
- `resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue` — charge-gen wizard host.
- `resources/js/pages/Finance/BatchStudio/DngPush.vue` — DNG-push wizard host.
- `resources/js/pages/Finance/BatchStudio/Reminders.vue` — reminders wizard host.
- `resources/js/components/finance/batch/BatchWizard.vue` — shared stepper + sticky summary bar shell (slots per step).
- `resources/js/components/finance/batch/PreviewDiffTable.vue` — 4-label diff (🟢/🔵/⚪/🟠) + quick filters + search + Excel export button.
- `resources/js/components/finance/batch/BatchResultPanel.vue` — ✅/⚪/🔴 summary + report download + per-row Student 360 deep links + retry-failed-subset (DNG/reminders).
- `resources/js/composables/useBatchStudio.ts` — wizard state machine + token round-trip (preview via `useApi`, commit via `useForm`).

**Modified — frontend:**
- `resources/js/constants/finance-routes.ts` + `resources/js/utils/routes.ts` — batch-studio route names + `financeRoutes.batchStudio` helper.
- `resources/js/constants/menu-sidebar.ts` — add "Batch Studio" to the "Sinh phí" group.
- `resources/js/types/finance.ts` — batch line/preview/result types.

**Tests:**
- `tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php`
- `tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php`
- `tests/Feature/Finance/Batch/BatchChargePreviewTest.php`
- `tests/Feature/Finance/Batch/BatchChargeCommitTest.php`
- `tests/Feature/Finance/Batch/BatchDngCommitTest.php`
- `tests/Feature/Finance/Batch/BatchReminderCommitTest.php`
- `tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`

---

## Verified backend facts this plan builds on

Confirmed by reading the code (paths are `path:line`). The plan **reuses** these; it does not change them.

**Charge generation (single-transaction envelope — per-student catch+skip → one commit):**
- `GenerateBatchChargesAction::run(array $data): array` (`app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php`) → `{total_students, created_count, created_invoices, updated_invoices, skipped_count, failed_count, errors[]}`. Outer `DB::beginTransaction()` :90, per-student `try` :93, inner `catch` :372, final `DB::commit()` :377. `createChargeIfNotExists()` :224–269 is the FIN-05 check-then-create (voided charges don't block regen).
- `GenerateMajorChargesAction::run(array $data): array` (`Actions/Major/GenerateMajorChargesAction.php`) → `{created, skipped, failed, errors[]}`. `hasActiveHpCharge()` check :66 (skip if active TUITION_TERM exists); **no DB unique/lock** → not concurrency-safe (UI must disable while running). `DB::beginTransaction()` :62, `DB::commit()` :134.
- `GenerateEgcChargesAction::run(array $data): array` (`Actions/Egc/GenerateEgcChargesAction.php`) → `{created, skipped, errors}`. Per-student `DB::transaction()` :74. `$data`: `semester_id`, `due_date`, `students: [{student_id, block_count}]`.
- Preview queries (preview == commit math, same resolvers):
  - `PreviewChargeGenerationQuery::handle(array $params): array` (`Queries/Operations/`) → `{students, total_students, new_charges_count, skip_count, total_amount, warnings}`.
  - `PreviewMajorChargeGenerationQuery::handle(int $semesterId, array $filters = [], ?int $campusId = null): array` (`Queries/Major/`) → `{eligible_students(paginator), ineligible_students, warning_students, summary{eligible_count,ineligible_count,warning_count,total_count}}`.
  - `PreviewEgcChargeGenerationQuery::handle(int $semesterId, array $filters = [], ?int $campusId = null): array` (`Queries/Egc/`) → `{eligible_students(paginator), ineligible_students, warning_students, summary{…,projected_block_count,projected_total_amount}}`.
- `GenerateChargesPreviewExport` (`Exports/GenerateChargesPreviewExport.php`) ctor `(array $students)`; `array()/headings()/styles()/title()`. Row keys: `id, student_id, full_name, status, has_existing_charge, estimated_amount, warning?, breakdown?, will_create_invoice?`.
- Shared resolvers (single source of truth for money — reused by preview **and** commit): `ScholarshipDiscountResolver::resolve(ScholarshipDefinition, float $baseAmount): float` (`Support/`), `VoucherDiscountAmountResolver`, `EgcLevelFeeResolver`, `StudentChargeTimingResolver`, `DeferChargeResolver`.

**DNG push (per-student transactions — real partial success, max 100/request):**
- `CreateBatchDngFromChargesAction::handle(array $data): array` (`Actions/CreateBatchDngFromChargesAction.php`) → `{created, failed, cancelled_old, errors[]}`. `$data`: `{student_ids[], dng_fee_type, due_date, semester_id, description, estimate_time, amount_overrides: {studentId=>float}|null}`. Per-student `try/catch` :68–91 (partial success). **Ad-hoc override:** `abs($amountOverride - $installmentAwareTotal) >= 1.0` :173–176 → logs + **clears `$installmentIds`** :190 → `createChargePivots(linkInstallments: ! $adHocOverride)` :259 → no `finance_charge_installment_id`. **Rerun cancels old:** lookup existing `awaitingPayment()->lockForUpdate()` :204–209 → `$this->cancelDngAction->run($existingDng)` + `$cancelledOld++` :211–224.
- `StoreBatchDngFromChargesRequest::rules()` (`Http/Requests/Dng/`) — `student_ids` `required|array|min:1|max:100` :23 (msg :43), `dng_fee_type` `in:DngFeeTypeOptions::values()`, `due_date` `required|date|after_or_equal:today`, `semester_id` `required|exists`, `description` `required|max:255`, `estimate_time` `required|max:10`, `amount_overrides` `nullable|array`, `amount_overrides.*` `numeric|min:1`.
- `DngWorklistController` (`Http/Web/Admin/`) `index` :41–79 renders `Finance/Operations/DngWorklist` via `ListDngWorklistQuery::handle($request)`; `store` :84–105 uses `StoreBatchDngFromChargesRequest`. Routes `finance.operations.dng-worklist` (GET) + `.store` (POST), both `can:create_finance_payments` (`routes/web.php` :135–140).
- `CancelDngPaymentRequestAction::run(DngPaymentRequest): void` (`Actions/`) **always** voids linked charges when present (`voidLinkedChargesAndRegistrations` :119–146 → `voidChargeAction->handle()` :135). ⚠️ Because batch DNG rerun calls this internally, **a batch DNG push that cancels an old DNG with linked charges performs a void** → the commit route must also require `void_finance_charges` (close the same gap M2 closed for the single cancel at `DngPaymentRequestController::cancelReviewed` :87–90).

**Reminders (per-recipient — `last_reminder_at` double-send guard, app-level):**
- `SendPaymentRemindersAction::run(array $data): array` (`Actions/Operations/`) → `{sent_count, failed_count, skipped_no_debt_count, skipped_no_student_email_count, message}`. Per-invoice; sets `last_reminder_at` on success :67. **No pre-send check** → caller/UI must guard.
- `SendDueItemRemindersAction::run(array $data): array` — `item_ids` as `"dng_request:{id}"` / `"invoice:{id}"`; sets `last_reminder_at` :125 (DNG) / :194 (invoice).
- Parent variants: `SendParentPaymentRemindersAction`, `SendDueItemParentRemindersAction`.
- `last_reminder_at` columns: `student_invoices` (migration `2026_04_07_000001_*`), `dng_payment_requests` (migration `2026_05_04_113617_*`).
- Existing reminder API routes (`routes/api.php` :35–38, group `api/v1/finance/operations`, middleware `['web','auth']`, **no `can:` gate**) call `App\Modules\Finance\Http\Api\Admin\BillingOperationsController` `sendReminders` :198 / `sendParentReminders` :210 / `sendDueItemReminders` :222 / `sendDueItemParentReminders` :240. Email via `EmailContentRegistry::resolve('payment_reminder' | 'parent_payment_reminder' | …)` + `emailService->sendSingleEmail()`.

**Preview-token precedent + gaps:**
- `StudentActionAuditController` (`app/Modules/Academic/Http/Web/`) `previewImport` :64–92 issues `Str::uuid()` and `Cache::put("student-action-import-preview:{userId}:{token}", ['file_hash'=>hash_file('sha256',…), 'shared_upload_record_id'=>…], now()->addMinutes(10))`; `executeImport` :94–113 validates cache presence + `file_hash` + `shared_upload_record_id` → 422 on mismatch. **This is the precedent Batch Studio upgrades** to per-line resolved-payload hashing + recompute-compare.
- Finance module has **no** existing preview-token / `content_hash` / per-line idempotency. Only hashing precedent: `DngWebhookEvent::computePayloadHash(array): string` = `ksort` + `hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE))` (`Dng/Models/DngWebhookEvent.php` :172–178). **Finance charges have no `idempotency_key` column** → the per-line key is *derived* (student+type+source), not stored.
- `TuitionPlan` (`app/Models/TuitionPlan.php`) fillable `curriculum_version_id, intake_semester_id, total_amount, currency, is_active` — **no `version` column**. `TuitionPlanTerm` fillable `tuition_plan_id, term_number, amount, due_date` — **no `version`**. → fee-config fingerprint = `"{plan.id}:{plan.updated_at}:{term.id}:{term.updated_at}"` (or `none`), never a `version` field.

**Read-model helpers (reused, read-only):** `SettlementService` (`Services/`) `getChargePaidAmount(int):float`, `getChargeDiscountAmount(int):float`, `deriveInvoiceSnapshot(StudentInvoice):array`, `invoiceCacheDrifts(StudentInvoice):bool`; `FinanceInvariantRegistry::all():array`; `GetFinanceAuditGraphQuery::handle(array $target, ?int, ?int):array` (Student 360 deep links from result errors).

**Foundation (reuse exactly):**
- `resources/js/constants/finance-routes.ts` (`FINANCE_ROUTE_NAMES`) + `resources/js/utils/routes.ts` (`financeRoutes`, `feeGeneration` group :455–462).
- `config/permission.php` `'finances'` block :337–373; seeder `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`; sync `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder`.
- `resources/js/constants/menu-sidebar.ts` → "Finance Office" → "Sinh phí" group :323–335.
- Shared `semester` prop (`app/Http/Middleware/HandleInertiaRequests.php` :65–95): `page.props.semester = { selected_id: int|null, options: {id,code,name,is_active}[] }` (only populated for finance users). Step ① defaults its semester to `semester.selected_id`.

---

## Task 0: Shared test helper `grantFinance()`

A single helper every backend test reuses (mirrors the M1/M2/M3 pattern). One place to change if the auth bootstrap moves.

**Files:**
- Create: `tests/Feature/Finance/Batch/helpers.php`
- Modify: `tests/Pest.php` (autoload the helper for the Batch suite)

- [ ] **Step 1: Write the helper**

Create `tests/Feature/Finance/Batch/helpers.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Grant a user a fixed set of finance permissions for the active campus and
 * bind the campus context the way HandleInertiaRequests + finance controllers expect.
 *
 * @param  string[]  $permissions
 */
function grantFinance(User $user, array $permissions, Campus $campus): void
{
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->instance(PermissionService::class, $mock);
}
```

- [ ] **Step 2: Autoload it for the Batch suite** — in `tests/Pest.php`, add (near the other `uses(...)`/requires):

```php
require_once __DIR__.'/Feature/Finance/Batch/helpers.php';
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Finance/Batch/helpers.php tests/Pest.php
git commit -m "test(finance): add grantFinance helper for Batch Studio suite"
```

---

## Task 1: New permission `view_finance_batch_studio` + seed

§8: Batch Studio is **gated per action** — there is deliberately **no umbrella "batch" permission**. This permission is a thin **view** gate for the hub landing only (consistent with `view_finance_cockpit` / `view_finance_student_overview`); it grants the ability to *open* Batch Studio, not to run any job. Every wizard's commit route still requires its specific action permission (Tasks 7–9).

**Files:** `config/permission.php`, `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`

- [ ] **Step 1: Declare the permission** — in `config/permission.php` `'finances'` array, after the `// Staff workspace foundation (S-010 milestone 1)` block (after line 372):

```php
            // Batch Studio (S-010 milestone 4) — thin view gate; each job gated per-action
            'view_finance_batch_studio' => 'view_finance_batch_studio',
```

- [ ] **Step 2: Map to roles** — in `RoleAndPermissionSeeder.php`, add `'view_finance_batch_studio'` to the same finance-capable role lists that already receive `view_finance_operations_dashboard` / `view_finance_student_overview` (`truong_phong`, `can_bo`). `super_admin` receives it via the `sync(all)` call.

- [ ] **Step 3: Sync** — Run: `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder`
Expected: output reports `view_finance_batch_studio` created.

- [ ] **Step 4: Commit**

```bash
git add config/permission.php database/seeders/InitialSetup/RoleAndPermissionSeeder.php
git commit -m "feat(finance): add view_finance_batch_studio permission"
```

---

## Task 2: `BatchPreviewLineHasher` — canonical per-line hash (pure unit)

The cryptographic core of the safety contract. Pure, deterministic, no DB — so it is unit-tested directly. It produces a stable SHA-256 over the **canonical resolved payload** of a single preview line, with a fee-config fingerprint that uses `updated_at` (no `version` column exists). Recursive `ksort` guarantees key-order independence (a re-resolved line with the same values hashes identically).

**Files:**
- Create: `app/Modules/Finance/Support/Batch/BatchPreviewLineHasher.php`
- Test: `tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;

it('hashes identical payloads identically regardless of key order', function () {
    $a = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0];
    $b = ['net' => 500000.0, 'charge_type' => 'BHYT', 'student_id' => 1];

    expect(BatchPreviewLineHasher::hashLine($a))->toBe(BatchPreviewLineHasher::hashLine($b));
});

it('changes the hash when any resolved value changes', function () {
    $base = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0];
    $changed = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500001.0];

    expect(BatchPreviewLineHasher::hashLine($base))->not->toBe(BatchPreviewLineHasher::hashLine($changed));
});

it('hashes nested arrays deterministically (warning_codes order independent)', function () {
    $a = ['student_id' => 1, 'warning_codes' => ['scholarship_expiring', 'late_enroll']];
    $b = ['student_id' => 1, 'warning_codes' => ['late_enroll', 'scholarship_expiring']];

    // warning_codes are sorted before hashing so display-order never breaks the token
    expect(BatchPreviewLineHasher::hashLine($a))->toBe(BatchPreviewLineHasher::hashLine($b));
});

it('builds a fee-config fingerprint from id + updated_at, not a version column', function () {
    $fp = BatchPreviewLineHasher::feeConfigFingerprint(42, '2026-06-01 10:00:00', 7, '2026-06-02 11:00:00');
    expect($fp)->toBe('plan:42@2026-06-01 10:00:00|term:7@2026-06-02 11:00:00');
});

it('returns a stable "none" fingerprint when there is no fee config', function () {
    expect(BatchPreviewLineHasher::feeConfigFingerprint(null, null, null, null))->toBe('none');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php`
Expected: FAIL — `Class "App\Modules\Finance\Support\Batch\BatchPreviewLineHasher" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Modules/Finance/Support/Batch/BatchPreviewLineHasher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

/**
 * Pure, deterministic per-line hashing for the Batch Studio preview-token contract.
 *
 * The hash binds the *resolved* payload of a single preview line so that a
 * recompute at commit time can detect any drift (changed scholarship, fee config,
 * registration, etc.) even when the resulting amount happens to be unchanged.
 */
final class BatchPreviewLineHasher
{
    /**
     * SHA-256 over the canonical (recursively key-sorted, value-sorted-arrays) payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hashLine(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    /**
     * Fee-config fingerprint. TuitionPlan / TuitionPlanTerm have no `version` column,
     * so identity = primary key + updated_at timestamp.
     */
    public static function feeConfigFingerprint(
        ?int $planId,
        ?string $planUpdatedAt,
        ?int $termId,
        ?string $termUpdatedAt,
    ): string {
        if ($planId === null && $termId === null) {
            return 'none';
        }

        return sprintf(
            'plan:%s@%s|term:%s@%s',
            $planId ?? '-',
            $planUpdatedAt ?? '-',
            $termId ?? '-',
            $termUpdatedAt ?? '-',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function canonicalJson(array $payload): string
    {
        $normalized = self::normalize($payload);

        return (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Recursively sort associative arrays by key and re-index + sort list arrays,
     * so display ordering never changes the hash.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private static function normalize($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $normalized = array_map(static fn ($v) => self::normalize($v), $value);

        if ($isList) {
            sort($normalized);

            return $normalized;
        }

        ksort($normalized);

        return $normalized;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Support/Batch/BatchPreviewLineHasher.php tests/Unit/Finance/Batch/BatchPreviewLineHasherTest.php
git commit -m "feat(finance): add BatchPreviewLineHasher for preview-token contract"
```

---

## Task 3: `BatchPreviewLine` value object + `BatchJobType` enum

The canonical shape every job maps its rows into, plus the job-type enum used for cache-key namespacing and per-action authz. Small, dependency-free — built before the token service consumes them.

**Files:**
- Create: `app/Modules/Finance/Support/Batch/BatchJobType.php`
- Create: `app/Modules/Finance/Support/Batch/BatchPreviewLine.php`
- Test: `tests/Unit/Finance/Batch/BatchPreviewLineTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Finance/Batch/BatchPreviewLineTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

it('exposes the key and the line hash', function () {
    $line = new BatchPreviewLine(
        key: 'charge:student:1:type:BHYT',
        hashPayload: ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0],
        display: ['label' => 'Nguyen Van A', 'diff' => 'create', 'net' => 500000.0],
    );

    expect($line->key)->toBe('charge:student:1:type:BHYT')
        ->and($line->hash())->toBeString()->toHaveLength(64);
});

it('maps a list of lines into a key=>hash token payload', function () {
    $lines = [
        new BatchPreviewLine('a', ['x' => 1], []),
        new BatchPreviewLine('b', ['x' => 2], []),
    ];

    $payload = BatchPreviewLine::toTokenPayload($lines);

    expect($payload)->toHaveKeys(['a', 'b'])
        ->and($payload['a'])->toHaveLength(64);
});

it('lists job types with their required action permissions', function () {
    expect(BatchJobType::ChargeGeneration->value)->toBe('charge_generation')
        ->and(BatchJobType::DngPush->value)->toBe('dng_push')
        ->and(BatchJobType::Reminder->value)->toBe('reminder');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/BatchPreviewLineTest.php`
Expected: FAIL — enum/class not found.

- [ ] **Step 3: Write the enum**

Create `app/Modules/Finance/Support/Batch/BatchJobType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

enum BatchJobType: string
{
    case ChargeGeneration = 'charge_generation';
    case DngPush = 'dng_push';
    case Reminder = 'reminder';

    /**
     * Cache-key namespace for issued preview tokens.
     */
    public function cacheNamespace(): string
    {
        return 'finance-batch-preview:'.$this->value;
    }
}
```

- [ ] **Step 4: Write the value object**

Create `app/Modules/Finance/Support/Batch/BatchPreviewLine.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

/**
 * One canonical preview line shared by every Batch Studio job.
 *
 * - $key         stable per-line identity (re-derivable at commit time)
 * - $hashPayload canonical resolved fields fed to BatchPreviewLineHasher
 * - $display     UI-only fields (label, diff bucket, amounts, reason, warning_codes)
 */
final class BatchPreviewLine
{
    /**
     * @param  array<string, mixed>  $hashPayload
     * @param  array<string, mixed>  $display
     */
    public function __construct(
        public readonly string $key,
        public readonly array $hashPayload,
        public readonly array $display,
    ) {}

    public function hash(): string
    {
        return BatchPreviewLineHasher::hashLine($this->hashPayload);
    }

    /**
     * @return array{key: string, display: array<string, mixed>}
     */
    public function toClientArray(): array
    {
        return ['key' => $this->key, 'display' => $this->display];
    }

    /**
     * @param  list<self>  $lines
     * @return array<string, string>  key => sha256
     */
    public static function toTokenPayload(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $out[$line->key] = $line->hash();
        }

        return $out;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/BatchPreviewLineTest.php`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Support/Batch/BatchJobType.php app/Modules/Finance/Support/Batch/BatchPreviewLine.php tests/Unit/Finance/Batch/BatchPreviewLineTest.php
git commit -m "feat(finance): add BatchPreviewLine value object and BatchJobType enum"
```

---

## Task 4: `BatchPreviewTokenService` — issue / verify / consume (cache, one-time, per-line)

The drift-proof handshake. `issue()` stores a per-line hash map under a namespaced cache key with a 30-minute TTL. `verify()` recompute-compares **only the submitted subset** (so excluding 🟠 warnings at step ③ is allowed — a whole-set fingerprint would always fail). `consume()` verifies then deletes the token (one-time use, blocks replay from a stale tab). Borrows the Student-Action-Import cache pattern; upgrades file-hash → per-line resolved-payload hash.

**Files:**
- Create: `app/Modules/Finance/Services/Batch/BatchPreviewTokenService.php`
- Test: `tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->service = app(BatchPreviewTokenService::class);
    $this->userId = 99;
    $this->lines = [
        new BatchPreviewLine('a', ['x' => 1], []),
        new BatchPreviewLine('b', ['x' => 2], []),
        new BatchPreviewLine('c', ['x' => 3], []),
    ];
});

it('issues a token and verifies an unchanged subset', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1],
        'b' => ['x' => 2],
    ]);

    expect($result['ok'])->toBeTrue()->and($result['changed'])->toBe([]);
});

it('blocks the commit when a selected line drifted', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1],
        'b' => ['x' => 999], // drifted
    ]);

    expect($result['ok'])->toBeFalse()->and($result['changed'])->toBe(['b']);
});

it('allows excluding warning lines (subset smaller than issued set still verifies)', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1], // commit only 1 of 3
    ]);

    expect($result['ok'])->toBeTrue();
});

it('reports unknown selected keys as drift, not silent skip', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'zzz' => ['x' => 1], // never issued
    ]);

    expect($result['ok'])->toBeFalse()->and($result['changed'])->toContain('zzz');
});

it('consume deletes the token so a second commit fails (one-time use)', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $first = $this->service->consume($this->userId, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);
    $second = $this->service->consume($this->userId, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);

    expect($first['ok'])->toBeTrue()
        ->and($second['ok'])->toBeFalse()
        ->and($second['missing'])->toBeTrue();
});

it('rejects a token issued for a different user', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, [], $this->lines);

    $result = $this->service->verify($this->userId + 1, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);

    expect($result['ok'])->toBeFalse()->and($result['missing'])->toBeTrue();
});

it('rejects a token replayed under a different job type', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, [], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::DngPush, ['a' => ['x' => 1]]);

    expect($result['ok'])->toBeFalse()->and($result['missing'])->toBeTrue();
});

it('returns the stored scope for re-resolution and null for a bad token', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 5, 'fee_category' => 'major'], $this->lines);

    expect($this->service->scope($this->userId, $token, BatchJobType::ChargeGeneration))
        ->toBe(['semester_id' => 5, 'fee_category' => 'major'])
        ->and($this->service->scope($this->userId, 'nope', BatchJobType::ChargeGeneration))->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php`
Expected: FAIL — service class not found.

- [ ] **Step 3: Write the implementation**

Create `app/Modules/Finance/Services/Batch/BatchPreviewTokenService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services\Batch;

use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Preview-token safety contract for Batch Studio.
 *
 * issue()   -> after step ② preview, store per-line hashes (one-time, 30-min TTL).
 * verify()  -> at step ④ commit, recompute-compare the *selected subset* (no mutation).
 * consume() -> verify then forget the token (one-time use; blocks stale-tab replay).
 */
final class BatchPreviewTokenService
{
    private const TTL_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $scope  semester/fee-type/scope params (audit only)
     * @param  list<BatchPreviewLine>  $lines
     */
    public function issue(int $userId, BatchJobType $jobType, array $scope, array $lines): string
    {
        $token = (string) Str::uuid();

        Cache::put(
            $this->cacheKey($userId, $jobType, $token),
            [
                'user_id' => $userId,
                'job_type' => $jobType->value,
                'scope' => $scope,
                'lines' => BatchPreviewLine::toTokenPayload($lines),
            ],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    /**
     * Recompute-compare the submitted subset against the issued per-line hashes.
     *
     * @param  array<string, array<string, mixed>>  $selected  key => re-resolved hash payload
     * @return array{ok: bool, missing: bool, changed: list<string>}
     */
    public function verify(int $userId, string $token, BatchJobType $jobType, array $selected): array
    {
        $stored = Cache::get($this->cacheKey($userId, $jobType, $token));

        if (! is_array($stored)
            || ($stored['user_id'] ?? null) !== $userId
            || ($stored['job_type'] ?? null) !== $jobType->value) {
            return ['ok' => false, 'missing' => true, 'changed' => []];
        }

        $issued = $stored['lines'] ?? [];
        $changed = [];

        foreach ($selected as $key => $payload) {
            $expected = $issued[$key] ?? null;
            $actual = BatchPreviewLineHasher::hashLine($payload);
            if ($expected === null || ! hash_equals($expected, $actual)) {
                $changed[] = (string) $key;
            }
        }

        return ['ok' => $changed === [], 'missing' => false, 'changed' => $changed];
    }

    /**
     * @param  array<string, array<string, mixed>>  $selected
     * @return array{ok: bool, missing: bool, changed: list<string>}
     */
    public function consume(int $userId, string $token, BatchJobType $jobType, array $selected): array
    {
        $result = $this->verify($userId, $token, $jobType, $selected);

        if ($result['ok']) {
            Cache::forget($this->cacheKey($userId, $jobType, $token));
        }

        return $result;
    }

    /**
     * The scope params stored at issue time — the commit controller re-resolves the
     * selected lines from current DB state using these, so drift is computed server-side
     * (never trust a client-sent payload).
     *
     * @return array<string, mixed>|null  null if missing/expired/foreign
     */
    public function scope(int $userId, string $token, BatchJobType $jobType): ?array
    {
        $stored = Cache::get($this->cacheKey($userId, $jobType, $token));

        if (! is_array($stored)
            || ($stored['user_id'] ?? null) !== $userId
            || ($stored['job_type'] ?? null) !== $jobType->value) {
            return null;
        }

        return $stored['scope'] ?? [];
    }

    private function cacheKey(int $userId, BatchJobType $jobType, string $token): string
    {
        return sprintf('%s:%d:%s', $jobType->cacheNamespace(), $userId, $token);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php`
Expected: PASS (8 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Services/Batch/BatchPreviewTokenService.php tests/Feature/Finance/Batch/BatchPreviewTokenServiceTest.php
git commit -m "feat(finance): add BatchPreviewTokenService (issue/verify/consume, one-time, per-line)"
```

## Task 5: Route constants + helper + Batch Studio hub (landing + wizard page shells)

The hub gates only the thin `view_finance_batch_studio` view permission and tells the page which job tiles the operator may actually run (per-action `can` flags). The three wizard GET routes render the per-job hosts; their POST preview/commit routes are added in Tasks 6–9 (so the app stays green between tasks).

**Files:**
- Modify: `resources/js/constants/finance-routes.ts`, `resources/js/utils/routes.ts`
- Create: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php`
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`

- [ ] **Step 1: Add route names** — in `resources/js/constants/finance-routes.ts`, before the closing `} as const;`:

```ts
    // Batch Studio (milestone 4)
    BATCH_STUDIO_HUB: 'finance.batch-studio.hub',
    BATCH_STUDIO_CHARGES: 'finance.batch-studio.charges',
    BATCH_STUDIO_CHARGES_PREVIEW: 'finance.batch-studio.charges.preview',
    BATCH_STUDIO_CHARGES_COMMIT: 'finance.batch-studio.charges.commit',
    BATCH_STUDIO_DNG: 'finance.batch-studio.dng',
    BATCH_STUDIO_DNG_PREVIEW: 'finance.batch-studio.dng.preview',
    BATCH_STUDIO_DNG_COMMIT: 'finance.batch-studio.dng.commit',
    BATCH_STUDIO_REMINDERS: 'finance.batch-studio.reminders',
    BATCH_STUDIO_REMINDERS_PREVIEW: 'finance.batch-studio.reminders.preview',
    BATCH_STUDIO_REMINDERS_COMMIT: 'finance.batch-studio.reminders.commit',
```

- [ ] **Step 2: Add the helper** — in `resources/js/utils/routes.ts`, inside `financeRoutes`, after the `feeGeneration: { … },` block (after line 462):

```ts
    batchStudio: {
        hub: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_HUB),
        charges: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES),
        chargesPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_PREVIEW),
        chargesCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_COMMIT),
        dng: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG),
        dngPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG_PREVIEW),
        dngCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_DNG_COMMIT),
        reminders: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS),
        remindersPreview: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS_PREVIEW),
        remindersCommit: () => route(FINANCE_ROUTE_NAMES.BATCH_STUDIO_REMINDERS_COMMIT),
    },
```

- [ ] **Step 3: Write the failing authz test**

Create `tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
});

it('forbids the hub without view_finance_batch_studio', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.hub'))
        ->assertForbidden();
});

it('renders the hub with only the job tiles the operator may run', function () {
    grantFinance($this->user, [
        'view_finance_batch_studio',
        'create_finance_charges', // can generate, cannot push DNG or remind
    ], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.hub'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/BatchStudio/Hub')
            ->where('jobs.charge_generation', true)
            ->where('jobs.dng_push', false)
            ->where('jobs.reminder', false)
        );
});

it('renders the charge-generation wizard page for an authorized operator', function () {
    grantFinance($this->user, ['view_finance_batch_studio', 'create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.charges'))
        ->assertInertia(fn (Assert $page) => $page->component('Finance/BatchStudio/ChargeGeneration'));
});
```

- [ ] **Step 4: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`
Expected: FAIL — route `finance.batch-studio.hub` not defined.

- [ ] **Step 5: Write the controller**

Create `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use App\Modules\Finance\Support\Dng\DngFeeTypeOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchStudioController extends Controller
{
    public function hub(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Finance/BatchStudio/Hub', [
            'jobs' => [
                'charge_generation' => (bool) ($user?->can('create_finance_charges')
                    || $user?->can('generate_egc_finance_charges')),
                'dng_push' => (bool) $user?->can('create_finance_payments'),
                'reminder' => (bool) $user?->can('view_finance_operations_due_calendar'),
            ],
        ]);
    }

    public function charges(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/ChargeGeneration', [
            'feeTypeOptions' => NonAcademicChargeTypeEnum::options(),
        ]);
    }

    public function dng(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/DngPush', [
            'dngFeeTypeOptions' => DngFeeTypeOptions::options(),
        ]);
    }

    public function reminders(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/Reminders');
    }
}
```

> **Verify before writing:** confirm `NonAcademicChargeTypeEnum::options()` and `DngFeeTypeOptions::options()` exist (the first agent confirmed `DngFeeTypeOptions::values()`; if only `values()` exists, map to `[{value,label}]` inline). Adjust the call to the real accessor — do not invent a method.

- [ ] **Step 6: Register the GET routes** — in `app/Modules/Finance/routes/web.php`, add a group (alongside the other finance web routes, using the existing `Route::prefix('finance')` group + controller import):

```php
    Route::prefix('batch-studio')->name('batch-studio.')->group(function () {
        Route::get('/', [BatchStudioController::class, 'hub'])
            ->middleware('can:view_finance_batch_studio')->name('hub');
        Route::get('/charges', [BatchStudioController::class, 'charges'])
            ->middleware('can:view_finance_batch_studio')->name('charges');
        Route::get('/dng', [BatchStudioController::class, 'dng'])
            ->middleware('can:view_finance_batch_studio')->name('dng');
        Route::get('/reminders', [BatchStudioController::class, 'reminders'])
            ->middleware('can:view_finance_batch_studio')->name('reminders');
        // POST preview/commit routes are added in Tasks 6–9.
    });
```

- [ ] **Step 7: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchStudioAuthzTest.php`
Expected: PASS (3 passed).

- [ ] **Step 8: Commit**

```bash
git add resources/js/constants/finance-routes.ts resources/js/utils/routes.ts app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/routes/web.php tests/Feature/Finance/Batch/BatchStudioAuthzTest.php
git commit -m "feat(finance): add Batch Studio hub + wizard route shells"
```

---

## Task 6: Charge-generation preview — assembler + JSON endpoint (issues token)

Step ② for sinh phí. `AssembleBatchChargePreviewQuery` dispatches to the existing `Preview*Query` per `fee_category` and maps every row into a canonical `BatchPreviewLine` (4-label diff bucket + the hash payload). The JSON endpoint returns the client lines + summary and **issues the preview token**. Read-only: no money math here beyond what the existing queries already compute.

**Files:**
- Create: `app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php`
- Create: `app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php`
- Create: `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php`
- Modify: `app/Modules/Finance/routes/api.php`
- Test: `tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php`, `tests/Feature/Finance/Batch/BatchChargePreviewTest.php`

- [ ] **Step 1: Write the failing mapping unit test** (the new logic — pure, no DB)

Create `tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;

it('maps an eligible row into a 🟢 create line with a stable key and net in the hash', function () {
    $row = [
        'id' => 11,
        'student_id' => 'SV011',
        'full_name' => 'Nguyen Van A',
        'estimated_amount' => 500000.0,
        'gross_amount' => 600000.0,
        'discount_amount' => 100000.0,
        'scholarship_id' => 7,
        'voucher_id' => null,
        'has_existing_charge' => false,
        'warning' => null,
        'fee_plan_id' => 42,
        'fee_plan_updated_at' => '2026-06-01 10:00:00',
        'fee_term_id' => 9,
        'fee_term_updated_at' => '2026-06-02 11:00:00',
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'major', 5, 'create');

    expect($line->key)->toBe('charge:major:student:11:semester:5')
        ->and($line->display['diff'])->toBe('create')
        ->and($line->display['net'])->toBe(500000.0)
        ->and($line->hashPayload['net'])->toBe(500000.0)
        ->and($line->hashPayload['scholarship_id'])->toBe(7)
        ->and($line->hashPayload['fee_config_fingerprint'])->toBe('plan:42@2026-06-01 10:00:00|term:9@2026-06-02 11:00:00');
});

it('maps a warning row into a 🟠 warning line carrying the reason', function () {
    $row = [
        'id' => 12, 'student_id' => 'SV012', 'full_name' => 'B',
        'estimated_amount' => 0.0, 'has_existing_charge' => false,
        'warning' => 'scholarship_expired',
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'major', 5, 'warning');

    expect($line->display['diff'])->toBe('warning')
        ->and($line->display['reason'])->toBe('scholarship_expired')
        ->and($line->hashPayload['warning_codes'])->toBe(['scholarship_expired']);
});

it('maps an already-charged row into a ⚪ skip line with a reason', function () {
    $row = [
        'id' => 13, 'student_id' => 'SV013', 'full_name' => 'C',
        'estimated_amount' => 500000.0, 'has_existing_charge' => true, 'warning' => null,
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'non_academic', 5, 'skip');

    expect($line->display['diff'])->toBe('skip')
        ->and($line->display['reason'])->toBe('already_charged');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php`
Expected: FAIL — class/method not found.

- [ ] **Step 3: Write the assembler**

Create `app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;

/**
 * Step ② for sinh phí: delegates to the existing Preview*Query per fee category and
 * normalizes every row into the canonical BatchPreviewLine. No new money math —
 * the amounts come straight from the shared resolvers inside those queries.
 */
final class AssembleBatchChargePreviewQuery
{
    public function __construct(
        private readonly PreviewMajorChargeGenerationQuery $majorPreview,
        private readonly PreviewEgcChargeGenerationQuery $egcPreview,
        private readonly PreviewChargeGenerationQuery $nonAcademicPreview,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(string $feeCategory, int $semesterId, array $scope, ?int $campusId): array
    {
        return match ($feeCategory) {
            'major' => $this->fromMajor($semesterId, $scope, $campusId),
            'egc' => $this->fromEgc($semesterId, $scope, $campusId),
            'non_academic' => $this->fromNonAcademic($semesterId, $scope),
            default => ['lines' => [], 'summary' => []],
        };
    }

    /**
     * Pure mapping of one preview row into a canonical line. (Unit-tested.)
     *
     * @param  array<string, mixed>  $row
     * @param  'create'|'update'|'skip'|'warning'  $bucket
     */
    public static function mapChargeRow(array $row, string $feeCategory, int $semesterId, string $bucket): BatchPreviewLine
    {
        $warningCodes = isset($row['warning']) && $row['warning'] !== null ? [(string) $row['warning']] : [];
        $reason = match ($bucket) {
            'warning' => (string) ($row['warning'] ?? ''),
            'skip' => ($row['has_existing_charge'] ?? false) ? 'already_charged' : ((string) ($row['skip_reason'] ?? 'ineligible')),
            default => null,
        };

        $hashPayload = [
            'student_id' => (int) $row['id'],
            'semester_id' => $semesterId,
            'fee_category' => $feeCategory,
            'charge_type' => (string) ($row['charge_type'] ?? $feeCategory),
            'source_type' => $row['source_type'] ?? null,
            'source_id' => isset($row['source_id']) ? (int) $row['source_id'] : null,
            'fee_config_fingerprint' => BatchPreviewLineHasher::feeConfigFingerprint(
                isset($row['fee_plan_id']) ? (int) $row['fee_plan_id'] : null,
                $row['fee_plan_updated_at'] ?? null,
                isset($row['fee_term_id']) ? (int) $row['fee_term_id'] : null,
                $row['fee_term_updated_at'] ?? null,
            ),
            'scholarship_id' => isset($row['scholarship_id']) ? (int) $row['scholarship_id'] : null,
            'voucher_id' => isset($row['voucher_id']) ? (int) $row['voucher_id'] : null,
            'gross' => (float) ($row['gross_amount'] ?? $row['estimated_amount'] ?? 0),
            'discount' => (float) ($row['discount_amount'] ?? 0),
            'net' => (float) ($row['estimated_amount'] ?? 0),
            'warning_codes' => $warningCodes,
        ];

        return new BatchPreviewLine(
            key: sprintf('charge:%s:student:%d:semester:%d', $feeCategory, (int) $row['id'], $semesterId),
            hashPayload: $hashPayload,
            display: [
                'student_id' => (string) ($row['student_id'] ?? ''),
                'label' => (string) ($row['full_name'] ?? ''),
                'diff' => $bucket,
                'gross' => $hashPayload['gross'],
                'discount' => $hashPayload['discount'],
                'net' => $hashPayload['net'],
                'reason' => $reason,
                'warning_codes' => $warningCodes,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromMajor(int $semesterId, array $scope, ?int $campusId): array
    {
        $result = $this->majorPreview->handle($semesterId, $scope['filters'] ?? [], $campusId);
        $lines = [];

        foreach ($this->collection($result['eligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'create');
        }
        foreach (($result['warning_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'warning');
        }
        foreach (($result['ineligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'skip');
        }

        return ['lines' => $lines, 'summary' => $result['summary'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromEgc(int $semesterId, array $scope, ?int $campusId): array
    {
        $result = $this->egcPreview->handle($semesterId, $scope['filters'] ?? [], $campusId);
        $lines = [];

        foreach ($this->collection($result['eligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'create');
        }
        foreach (($result['warning_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'warning');
        }
        foreach (($result['ineligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'skip');
        }

        return ['lines' => $lines, 'summary' => $result['summary'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromNonAcademic(int $semesterId, array $scope): array
    {
        $result = $this->nonAcademicPreview->handle(array_merge($scope, ['semester_id' => $semesterId]));
        $lines = [];

        foreach (($result['students'] ?? []) as $row) {
            $bucket = match (true) {
                ! empty($row['warning']) => 'warning',
                ($row['has_existing_charge'] ?? false) => 'skip',
                default => 'create',
            };
            $lines[] = self::mapChargeRow($row, 'non_academic', $semesterId, $bucket);
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => $result['total_students'] ?? count($lines),
            'new_charges_count' => $result['new_charges_count'] ?? 0,
            'skip_count' => $result['skip_count'] ?? 0,
            'total_amount' => $result['total_amount'] ?? 0,
        ]];
    }

    /**
     * Normalize a paginator or array into an iterable of row arrays.
     *
     * @param  mixed  $value
     * @return iterable<array<string, mixed>>
     */
    private function collection($value): iterable
    {
        if (is_array($value)) {
            return $value;
        }

        // LengthAwarePaginator / Collection
        return method_exists($value, 'items') ? $value->items() : (array) $value;
    }
}
```

> **Integration note:** the `fee_plan_id` / `fee_plan_updated_at` / `gross_amount` / `discount_amount` / `scholarship_id` keys must be present on the rows the `Preview*Query` classes return. If a query does not yet expose them, **extend that query's row builder** to include them (read-only additions; do not change the amounts). This is the only backend change to the existing preview queries and must be a separate, tested commit if the keys are missing.

- [ ] **Step 4: Run to verify the mapping test passes**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Write the FormRequest**

Create `app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Baseline action gate; EGC-only category additionally checked in the controller.
        return (bool) ($this->user()?->can('create_finance_charges')
            || $this->user()?->can('generate_egc_finance_charges'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_category' => 'required|in:major,egc,non_academic',
            'semester_id' => 'required|integer|exists:semesters,id',
            'scope' => 'nullable|array',
            'scope.filters' => 'nullable|array',
            'scope.fee_type' => 'nullable|string',
            'scope.amount' => 'nullable|numeric|min:1',
            'scope.due_date' => 'nullable|date',
        ];
    }
}
```

- [ ] **Step 6: Write the JSON preview controller**

Create `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Http\Requests\Batch\PreviewBatchChargesRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class BatchStudioPreviewController extends Controller
{
    public function __construct(
        private readonly BatchPreviewTokenService $tokens,
    ) {}

    public function previewCharges(
        PreviewBatchChargesRequest $request,
        AssembleBatchChargePreviewQuery $assembler,
    ): JsonResponse {
        $feeCategory = (string) $request->input('fee_category');

        if ($feeCategory === 'egc' && ! $request->user()?->can('generate_egc_finance_charges')) {
            throw new AuthorizationException('Bạn không có quyền sinh phí EGC.');
        }

        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $result = $assembler->handle(
            $feeCategory,
            (int) $request->input('semester_id'),
            (array) $request->input('scope', []),
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::ChargeGeneration,
            ['fee_category' => $feeCategory, 'semester_id' => (int) $request->input('semester_id')],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
    }
}
```

- [ ] **Step 7: Register the JSON route** — in `app/Modules/Finance/routes/api.php`, inside the existing finance API group (`api/v1/finance`, middleware `['web','auth']`):

```php
    Route::post('/batch-studio/charges/preview', [BatchStudioPreviewController::class, 'previewCharges'])
        ->middleware('can:create_finance_charges')
        ->name('batch-studio.charges.preview');
```

> Route name resolves to `finance.batch-studio.charges.preview` via the group's `->name('finance.')` prefix — matches `FINANCE_ROUTE_NAMES.BATCH_STUDIO_CHARGES_PREVIEW`. Verify the group's name prefix; if the API group uses a different prefix, set the constant to the real generated name.

- [ ] **Step 8: Write the endpoint feature test**

Create `tests/Feature/Finance/Batch/BatchChargePreviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('returns a preview token and lines for an authorized major preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $this->actingAs($this->user)
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);
});

it('forbids an EGC preview without generate_egc_finance_charges', function () {
    grantFinance($this->user, ['create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'egc',
            'semester_id' => $this->semester->id,
        ])
        ->assertForbidden();
});
```

- [ ] **Step 9: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargePreviewTest.php`
Expected: PASS (2 passed). If the major preview query requires seed data the factory does not provide, add the minimal eligible-student seed to `beforeEach` — keep the assertions tolerant of counts (assert structure + token, not exact line counts).

- [ ] **Step 10: Commit**

```bash
git add app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php app/Modules/Finance/Http/Requests/Batch/PreviewBatchChargesRequest.php app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php app/Modules/Finance/routes/api.php tests/Unit/Finance/Batch/AssembleBatchChargePreviewMappingTest.php tests/Feature/Finance/Batch/BatchChargePreviewTest.php
git commit -m "feat(finance): add Batch Studio charge preview + token issue"
```

---

## Task 7: Charge-generation commit — verify token, then call the existing Action

Step ④ for sinh phí. The Inertia commit re-resolves the submitted subset, **recompute-compares against the token** (`consume`, one-time), and on a clean match calls the existing generation Action for the selected fee category. On drift it returns a validation error naming the changed lines and forces a re-preview. The result is flashed back (single-transaction-envelope summary).

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php` (add `commitCharges`)
- Modify: `app/Modules/Finance/routes/web.php` (add POST `charges`)
- Test: `tests/Feature/Finance/Batch/BatchChargeCommitTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Batch/BatchChargeCommitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    grantFinance($this->user, ['create_finance_charges'], $this->campus);
    $this->key = 'charge:major:student:1:semester:'.$this->semester->id;
});

/**
 * Swap the assembler with a stub that re-resolves the one line to $payload, so the
 * test controls the SERVER-SIDE recompute result deterministically (drift is server data
 * changing between issue and commit — never a client-echoed hash).
 */
function stubChargeAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchChargePreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchChargePreviewQuery::class, $stub);
}

it('rejects an unknown or expired token (no scope to re-resolve against)', function () {
    stubChargeAssembler($this->key, ['net' => 500000.0]);

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), [
            'preview_token' => 'not-a-real-token',
            'selected_keys' => [$this->key],
        ])
        ->assertSessionHasErrors('preview_token');
});

it('blocks the commit when the re-resolved line drifted from the issued hash', function () {
    // Token issued with net=500000; the server now re-resolves the same key to net=999999.
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'major', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubChargeAssembler($this->key, ['net' => 999999.0]); // drift

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), [
            'preview_token' => $token,
            'selected_keys' => [$this->key],
        ])
        ->assertSessionHasErrors('preview_token');
});

it('commits the clean subset and flashes a job summary when nothing drifted', function () {
    $payload = ['net' => 500000.0];
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'major', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, $payload, [])],
    );
    stubChargeAssembler($this->key, $payload); // re-resolves identically → no drift

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), [
            'preview_token' => $token,
            'selected_keys' => [$this->key],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('finance.batch-studio.charges'));
});

it('forbids the commit without create_finance_charges', function () {
    grantFinance($this->user, ['view_finance_batch_studio'], $this->campus);

    $this->actingAs($this->user)
        ->post(route('finance.batch-studio.charges.commit'), [
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ])
        ->assertForbidden();
});
```

> The `GenerateMajorChargesAction::run` call in the happy-path test runs against a non-existent student id (1) — the verified Actions iterate an eligible-student query and return zero counts on an empty set without throwing, so the assertion stays on the flash/redirect, not on created counts.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargeCommitTest.php`
Expected: FAIL — route `finance.batch-studio.charges.commit` not defined.

- [ ] **Step 3: Write the FormRequest**

Create `app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create_finance_charges');
    }

    /**
     * Note: fee_category + semester_id are NOT accepted from the client — they are read
     * from the trusted token scope at commit time (tamper-proof). The client sends only
     * the token and the line keys it confirmed.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1',
            'selected_keys.*' => 'required|string',
            'acknowledged' => 'nullable|boolean',
        ];
    }
}
```

- [ ] **Step 4: Add `commitCharges` to the controller** — in `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php`, add the imports and method:

```php
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Actions\Major\GenerateMajorChargesAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchChargesRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
// `use Inertia\Inertia;` and `use Inertia\Response;` are already imported from Task 5.
```

```php
    public function commitCharges(
        CommitBatchChargesRequest $request,
        BatchPreviewTokenService $tokens,
        AssembleBatchChargePreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        // Scope is the trusted, tamper-proof copy stored at preview time.
        $scope = $tokens->scope($userId, $token, BatchJobType::ChargeGeneration);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $feeCategory = (string) ($scope['fee_category'] ?? '');
        $semesterId = (int) ($scope['semester_id'] ?? 0);

        if ($feeCategory === 'egc' && ! $request->user()?->can('generate_egc_finance_charges')) {
            throw new AuthorizationException('Bạn không có quyền sinh phí EGC.');
        }

        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        // Re-resolve the selected lines from CURRENT DB state, recompute-compare, one-time consume.
        $this->recomputeOrFail(
            $userId, $token, BatchJobType::ChargeGeneration, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($feeCategory, $semesterId, (array) ($scope['scope'] ?? []), $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        $studentIds = collect($selectedKeys)
            ->map(fn (string $key) => (int) (explode(':', $key)[3] ?? 0))
            ->filter()->values()->all();

        $summary = $this->runGeneration($feeCategory, $semesterId, $studentIds, $userId);

        return Inertia::flash('batch_result', [
            'job' => 'charge_generation',
            'fee_category' => $feeCategory,
            'summary' => $summary,
        ])->back();
    }

    /**
     * Re-resolve the selected line keys from current DB state via $reresolve, then
     * recompute-compare against the issued token (one-time consume). Throws on any drift.
     * Shared by all three commit flows.
     *
     * @param  string[]  $selectedKeys
     * @param  callable(): array<string, BatchPreviewLine>  $reresolve  key => current line
     */
    private function recomputeOrFail(
        int $userId,
        string $token,
        BatchJobType $jobType,
        array $selectedKeys,
        BatchPreviewTokenService $tokens,
        callable $reresolve,
    ): void {
        $currentByKey = $reresolve();

        $subset = [];
        foreach ($selectedKeys as $key) {
            $line = $currentByKey[$key] ?? null;
            // A key that no longer resolves is treated as drift, never silently skipped.
            $subset[$key] = $line?->hashPayload ?? ['__gone__' => $key];
        }

        $verdict = $tokens->consume($userId, $token, $jobType, $subset);

        if (! $verdict['ok']) {
            $message = $verdict['missing']
                ? 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.'
                : count($verdict['changed']).' dòng đã thay đổi từ lúc xem trước. Vui lòng xem trước lại.';
            throw ValidationException::withMessages(['preview_token' => $message]);
        }
    }

    /**
     * @param  int[]  $studentIds
     * @return array<string, mixed>
     */
    private function runGeneration(string $feeCategory, int $semesterId, array $studentIds, int $userId): array
    {
        return match ($feeCategory) {
            'major' => GenerateMajorChargesAction::run([
                'semester_id' => $semesterId,
                'student_ids' => $studentIds,
                'created_by_user_id' => $userId,
            ]),
            'egc' => GenerateEgcChargesAction::run([
                'semester_id' => $semesterId,
                'students' => array_map(fn ($id) => ['student_id' => $id], $studentIds),
                'created_by_user_id' => $userId,
            ]),
            'non_academic' => GenerateBatchChargesAction::run([
                'semester_id' => $semesterId,
                'student_ids' => $studentIds,
                'created_by_user_id' => $userId,
            ]),
            default => [],
        };
    }
```

> **Verify the Action input contracts before writing** (Task §"Verified backend facts"): `GenerateMajorChargesAction::run` / `GenerateEgcChargesAction::run` / `GenerateBatchChargesAction::run` accept the keys shown via the existing controllers. Match the existing controllers' `$data` shape exactly (e.g. EGC needs `due_date` + per-student `block_count`; pass them through from the request scope rather than defaulting). Do **not** alter the Actions.

- [ ] **Step 5: Register the POST route** — in `app/Modules/Finance/routes/web.php`, inside the `batch-studio` group from Task 5:

```php
        Route::post('/charges', [BatchStudioController::class, 'commitCharges'])
            ->middleware('can:create_finance_charges')->name('charges.commit');
```

- [ ] **Step 6: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchChargeCommitTest.php`
Expected: PASS (4 passed).

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Batch/CommitBatchChargesRequest.php app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/routes/web.php tests/Feature/Finance/Batch/BatchChargeCommitTest.php
git commit -m "feat(finance): add Batch Studio charge commit with token recompute-compare"
```

---

## Task 8: DNG-push job — preview + commit (chunk ≤100, override→ad-hoc, rerun-cancels-old, void gate)

Đẩy DNG differs from generation: **per-student transactions, real partial success, max 100/request**. The preview reuses `ListDngWorklistQuery` rows and surfaces the installment-aware total per student so the UI can warn on overrides. The commit reuses `CreateBatchDngFromChargesAction` (which already chunks at the Action level per student) but the **route must additionally require `void_finance_charges`** because a rerun can cancel an old DNG that has linked charges (which voids them). The commit returns a per-student result for retry-of-failed-subset.

**Files:**
- Create: `app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php`
- Create: `app/Modules/Finance/Http/Requests/Batch/PreviewBatchDngRequest.php`, `CommitBatchDngRequest.php`
- Modify: `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php` (add `previewDng`)
- Modify: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php` (add `commitDng`)
- Modify: `app/Modules/Finance/routes/api.php`, `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Batch/BatchDngCommitTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Batch/BatchDngCommitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    $this->key = 'dng:student:1:fee:tuition';
    $this->base = [
        'due_date' => now()->addWeek()->toDateString(),
        'description' => 'Học phí kỳ', 'estimate_time' => '3d',
    ];
});

function stubDngAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchDngPreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchDngPreviewQuery::class, $stub);
}

it('forbids the DNG commit without void_finance_charges (rerun can void linked charges)', function () {
    grantFinance($this->user, ['create_finance_payments'], $this->campus); // missing void perm

    $this->actingAs($this->user)
        ->post(route('finance.batch-studio.dng.commit'), array_merge($this->base, [
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ]))
        ->assertForbidden();
});

it('caps the batch at 100 students', function () {
    grantFinance($this->user, ['create_finance_payments', 'void_finance_charges'], $this->campus);

    $keys = collect(range(1, 101))->map(fn ($i) => "dng:student:$i:fee:tuition")->all();

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), array_merge($this->base, [
            'preview_token' => 'x', 'selected_keys' => $keys,
        ]))
        ->assertSessionHasErrors('selected_keys');
});

it('blocks the commit when the re-resolved DNG line drifted', function () {
    grantFinance($this->user, ['create_finance_payments', 'void_finance_charges'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::DngPush,
        ['dng_fee_type' => 'tuition', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubDngAssembler($this->key, ['net' => 700000.0]); // drift

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), array_merge($this->base, [
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ]))
        ->assertSessionHasErrors('preview_token');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchDngCommitTest.php`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the FormRequests**

Create `app/Modules/Finance/Http/Requests/Batch/PreviewBatchDngRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use App\Modules\Finance\Support\Dng\DngFeeTypeOptions;
use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchDngRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create_finance_payments');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => 'required|integer|exists:semesters,id',
            'dng_fee_type' => 'required|string|in:'.implode(',', DngFeeTypeOptions::values()),
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
        ];
    }
}
```

Create `app/Modules/Finance/Http/Requests/Batch/CommitBatchDngRequest.php` (mirrors `StoreBatchDngFromChargesRequest`'s `max:100`, plus the token contract):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchDngRequest extends FormRequest
{
    public function authorize(): bool
    {
        // create_finance_payments AND void_finance_charges — a rerun can cancel an old
        // DNG with linked charges, which voids them (CancelDngPaymentRequestAction).
        return (bool) ($this->user()?->can('create_finance_payments')
            && $this->user()?->can('void_finance_charges'));
    }

    /**
     * semester_id + dng_fee_type come from the trusted token scope (not the client).
     * due_date / description / estimate_time / amount_overrides are operator commit-time inputs.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'due_date' => 'required|date|after_or_equal:today',
            'description' => 'required|string|max:255',
            'estimate_time' => 'required|string|max:10',
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1|max:100',
            'selected_keys.*' => 'required|string',
            'amount_overrides' => 'nullable|array',
            'amount_overrides.*' => 'numeric|min:1',
            'acknowledged' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['selected_keys.max' => 'Tối đa 100 sinh viên mỗi lần. Hệ thống sẽ chia lô tự động.'];
    }
}
```

- [ ] **Step 4: Add `previewDng` to the preview controller** — in `BatchStudioPreviewController.php`:

```php
    public function previewDng(
        \App\Modules\Finance\Http\Requests\Batch\PreviewBatchDngRequest $request,
        \App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery $assembler,
    ): JsonResponse {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $result = $assembler->handle(
            (int) $request->input('semester_id'),
            (string) $request->input('dng_fee_type'),
            (array) $request->input('student_ids', []),
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::DngPush,
            ['dng_fee_type' => (string) $request->input('dng_fee_type'), 'semester_id' => (int) $request->input('semester_id')],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
    }
```

- [ ] **Step 5: Write the DNG preview assembler**

Create `app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php`. It reuses `ListDngWorklistQuery` to find eligible students/charges and emits one canonical line per student carrying the **installment-aware total** (so the UI computes "override differs ≥1 VND → ad-hoc"). The hash payload binds `student_id, dng_fee_type, charge_ids[], installment_aware_total, has_active_dng` (so a rerun-cancel risk that appears between preview and commit is caught):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Http\Request;

final class AssembleBatchDngPreviewQuery
{
    public function __construct(private readonly ListDngWorklistQuery $worklist) {}

    /**
     * @param  int[]  $studentIds
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(int $semesterId, string $dngFeeType, array $studentIds, ?int $campusId): array
    {
        // Reuse the existing worklist query (campus-scoped internally) to get eligible rows.
        $request = Request::create('', 'GET', array_filter([
            'semester_id' => $semesterId,
            'fee_type' => $dngFeeType,
            'student_ids' => $studentIds ?: null,
        ]));

        $worklist = $this->worklist->handle($request);
        $rows = $worklist['students'] ?? $worklist['rows'] ?? [];

        $lines = [];
        foreach ($rows as $row) {
            $studentId = (int) ($row['student_id'] ?? $row['id']);
            $chargeIds = array_map('intval', $row['charge_ids'] ?? []);
            sort($chargeIds);
            $total = (float) ($row['installment_aware_total'] ?? $row['total_amount'] ?? 0);
            $hasActiveDng = (bool) ($row['has_active_dng'] ?? false);

            $lines[] = new BatchPreviewLine(
                key: sprintf('dng:student:%d:fee:%s', $studentId, $dngFeeType),
                hashPayload: [
                    'student_id' => $studentId,
                    'dng_fee_type' => $dngFeeType,
                    'charge_ids' => $chargeIds,
                    'net' => $total,
                    'has_active_dng' => $hasActiveDng,
                ],
                display: [
                    'student_id' => (string) ($row['student_code'] ?? $row['student_id'] ?? ''),
                    'label' => (string) ($row['full_name'] ?? ''),
                    'diff' => $hasActiveDng ? 'warning' : 'create',
                    'net' => $total,
                    'installment_aware_total' => $total,
                    'reason' => $hasActiveDng ? 'rerun_cancels_old_dng' : null,
                    'warning_codes' => $hasActiveDng ? ['rerun_cancels_old_dng'] : [],
                ],
            );
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => count($lines),
            'total_amount' => array_sum(array_map(fn ($l) => $l->display['net'], $lines)),
        ]];
    }
}
```

> **Verify `ListDngWorklistQuery::handle` input/row shape** before writing — the first agent confirmed it takes a `Request` and returns worklist rows; match the real row keys (`charge_ids`, `installment_aware_total`, `has_active_dng`). If those exact keys are absent, extend the worklist row builder (read-only) to expose them, in a separate tested commit.

- [ ] **Step 6: Add `commitDng` to the controller** — in `BatchStudioController.php` add the import + method:

```php
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchDngRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
```

```php
    public function commitDng(
        CommitBatchDngRequest $request,
        BatchPreviewTokenService $tokens,
        CreateBatchDngFromChargesAction $action,
        AssembleBatchDngPreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        $scope = $tokens->scope($userId, $token, BatchJobType::DngPush);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $dngFeeType = (string) ($scope['dng_fee_type'] ?? '');
        $semesterId = (int) ($scope['semester_id'] ?? 0);
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $studentIds = collect($selectedKeys)
            ->map(fn (string $key) => (int) (explode(':', $key)[2] ?? 0))
            ->filter()->values()->all();

        // Re-resolve only the selected students from current DB state, recompute-compare, consume.
        $this->recomputeOrFail(
            $userId, $token, BatchJobType::DngPush, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($semesterId, $dngFeeType, $studentIds, $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        // Reuse the existing per-student, partial-success batch action (chunks internally).
        $result = $action->handle([
            'student_ids' => $studentIds,
            'dng_fee_type' => $dngFeeType,
            'due_date' => (string) $request->input('due_date'),
            'semester_id' => $semesterId,
            'description' => (string) $request->input('description'),
            'estimate_time' => (string) $request->input('estimate_time'),
            'amount_overrides' => $request->input('amount_overrides'),
        ]);

        return Inertia::flash('batch_result', [
            'job' => 'dng_push',
            'summary' => $result, // {created, failed, cancelled_old, errors[]}
        ])->back();
    }
```

- [ ] **Step 7: Register the routes** —

In `app/Modules/Finance/routes/api.php` (finance API group):
```php
    Route::post('/batch-studio/dng/preview', [BatchStudioPreviewController::class, 'previewDng'])
        ->middleware('can:create_finance_payments')->name('batch-studio.dng.preview');
```

In `app/Modules/Finance/routes/web.php` (`batch-studio` group):
```php
        Route::post('/dng', [BatchStudioController::class, 'commitDng'])
            ->middleware('can:create_finance_payments')->name('dng.commit');
```

> The `void_finance_charges` requirement is enforced in `CommitBatchDngRequest::authorize()` (Step 3), not the route, so the route mirrors the existing `dng-worklist.store` gate while the FormRequest adds the void gate — matching the M2 layered-gate precedent.

- [ ] **Step 8: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchDngCommitTest.php`
Expected: PASS (3 passed).

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Finance/Queries/Batch/AssembleBatchDngPreviewQuery.php app/Modules/Finance/Http/Requests/Batch/PreviewBatchDngRequest.php app/Modules/Finance/Http/Requests/Batch/CommitBatchDngRequest.php app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/routes/api.php app/Modules/Finance/routes/web.php tests/Feature/Finance/Batch/BatchDngCommitTest.php
git commit -m "feat(finance): add Batch Studio DNG push preview + commit (chunk, void gate, drift block)"
```

---

## Task 9: Reminders job — preview + commit (last_reminder_at double-send guard)

Nhắc nợ is per-recipient with no money write. The preview lists who would be emailed and flags who is **skipped** (no email / lifecycle / no debt / already reminded recently). The hash payload binds the recipient set + `last_reminder_at` so a set that changed since preview is caught. The commit reuses the existing reminder Actions and **must not re-send** to a recipient already reminded within the guard window unless the operator explicitly forces it.

**Files:**
- Create: `app/Modules/Finance/Queries/Batch/AssembleBatchReminderPreviewQuery.php`
- Create: `app/Modules/Finance/Http/Requests/Batch/PreviewBatchRemindersRequest.php`, `CommitBatchRemindersRequest.php`
- Modify: `BatchStudioPreviewController.php` (add `previewReminders`), `BatchStudioController.php` (add `commitReminders`)
- Modify: `app/Modules/Finance/routes/api.php`, `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Batch/BatchReminderCommitTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Batch/BatchReminderCommitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Queries\Batch\AssembleBatchReminderPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    $this->key = 'reminder:invoice:1';
});

function stubReminderAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchReminderPreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchReminderPreviewQuery::class, $stub);
}

it('forbids the reminder commit without the due-calendar permission', function () {
    grantFinance($this->user, ['view_finance_batch_studio'], $this->campus);

    $this->actingAs($this->user)
        ->post(route('finance.batch-studio.reminders.commit'), [
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ])
        ->assertForbidden();
});

it('blocks the reminder commit when a recipient was reminded since preview (drift = stale double-send)', function () {
    grantFinance($this->user, ['view_finance_operations_due_calendar'], $this->campus);

    // Issued when last_reminder_at was null; server now re-resolves a non-null last_reminder_at.
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::Reminder,
        ['recipient' => 'student', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['item' => 'invoice:1', 'recipient' => 'student', 'last_reminder_at' => null], [])],
    );
    stubReminderAssembler($this->key, ['item' => 'invoice:1', 'recipient' => 'student', 'last_reminder_at' => '2026-06-15 09:00:00']);

    $this->actingAs($this->user)
        ->from(route('finance.batch-studio.reminders'))
        ->post(route('finance.batch-studio.reminders.commit'), [
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ])
        ->assertSessionHasErrors('preview_token');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchReminderCommitTest.php`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the FormRequests**

Create `app/Modules/Finance/Http/Requests/Batch/PreviewBatchRemindersRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBatchRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_operations_due_calendar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => 'required|in:student,parent',
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ];
    }
}
```

Create `app/Modules/Finance/Http/Requests/Batch/CommitBatchRemindersRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_operations_due_calendar');
    }

    /**
     * recipient comes from the trusted token scope (not the client).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1',
            'selected_keys.*' => 'required|string',
            'force_resend' => 'nullable|boolean',
        ];
    }
}
```

- [ ] **Step 4: Write the reminder preview assembler**

Create `app/Modules/Finance/Queries/Batch/AssembleBatchReminderPreviewQuery.php`. Reuse the due/overdue rows the due-calendar already produces; bind `last_reminder_at` into the hash so a since-reminded recipient is treated as drift (forces a re-preview before re-sending):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Modules\Finance\Queries\Operations\GetDueItemsQuery; // existing due-items source
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

final class AssembleBatchReminderPreviewQuery
{
    public function __construct(private readonly GetDueItemsQuery $dueItems) {}

    /**
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(string $recipient, ?int $semesterId, ?int $campusId): array
    {
        $rows = $this->dueItems->handle($semesterId, $campusId); // {item_id, type, last_reminder_at, has_email, ...}
        $lines = [];

        foreach ($rows as $row) {
            $itemKey = (string) $row['item_id']; // e.g. "invoice:123" / "dng_request:7"
            $lastReminderAt = $row['last_reminder_at'] ?? null;
            $hasEmail = (bool) ($row[$recipient === 'parent' ? 'has_parent_email' : 'has_student_email'] ?? false);
            $recentlyReminded = $lastReminderAt !== null
                && now()->parse($lastReminderAt)->gt(now()->subDay()); // 24h window (confirm at story)

            $bucket = match (true) {
                ! $hasEmail => 'skip',
                $recentlyReminded => 'warning',
                default => 'create',
            };

            $lines[] = new BatchPreviewLine(
                key: 'reminder:'.$itemKey,
                hashPayload: [
                    'item' => $itemKey,
                    'recipient' => $recipient,
                    'last_reminder_at' => $lastReminderAt, // drift if reminded since preview
                ],
                display: [
                    'label' => (string) ($row['full_name'] ?? ''),
                    'student_id' => (string) ($row['student_code'] ?? ''),
                    'diff' => $bucket,
                    'net' => (float) ($row['outstanding'] ?? 0),
                    'reason' => match ($bucket) {
                        'skip' => 'no_email',
                        'warning' => 'recently_reminded',
                        default => null,
                    },
                    'warning_codes' => $recentlyReminded ? ['recently_reminded'] : [],
                ],
            );
        }

        return ['lines' => $lines, 'summary' => ['total_recipients' => count($lines)]];
    }
}
```

> **Verify the due-items source** — the second agent confirmed `SendDueItemRemindersAction` consumes `item_ids` as `"dng_request:{id}"` / `"invoice:{id}"`. Find the query that lists those due items (likely `GetDueItemsQuery`/`GetDueItemsSummaryQuery` family) and match its real method + row keys; add `last_reminder_at` / `has_*_email` to its row builder (read-only) if missing.

- [ ] **Step 5: Add `previewReminders` + `commitReminders`** — `previewReminders` mirrors Tasks 6/8 (gate `view_finance_operations_due_calendar`, call `AssembleBatchReminderPreviewQuery`, issue a `BatchJobType::Reminder` token whose scope is `['recipient', 'semester_id', 'scope']`). `commitReminders` re-resolves the recipient set server-side, recompute-compares, then dispatches the existing reminder Action by recipient. Add to `BatchStudioController` imports:

```php
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchRemindersRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchReminderPreviewQuery;
```

```php
    public function commitReminders(
        CommitBatchRemindersRequest $request,
        BatchPreviewTokenService $tokens,
        AssembleBatchReminderPreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        $scope = $tokens->scope($userId, $token, BatchJobType::Reminder);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $recipient = (string) ($scope['recipient'] ?? 'student');
        $semesterId = isset($scope['semester_id']) ? (int) $scope['semester_id'] : null;
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        // Re-resolve the recipient set: a recipient reminded since preview now carries a
        // different last_reminder_at → drift → blocked (no stale double-send).
        $this->recomputeOrFail(
            $userId, $token, BatchJobType::Reminder, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($recipient, $semesterId, $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        $itemIds = collect($selectedKeys)
            ->map(fn (string $key) => str_replace('reminder:', '', $key))
            ->values()->all();

        $result = $recipient === 'parent'
            ? SendDueItemParentRemindersAction::run(['item_ids' => $itemIds])
            : SendDueItemRemindersAction::run(['item_ids' => $itemIds]);

        return Inertia::flash('batch_result', ['job' => 'reminder', 'summary' => $result])->back();
    }
```

> **Force-resend escape hatch:** when the operator deliberately re-sends (`force_resend = true`), the UI re-runs preview first (so the token reflects the *current* `last_reminder_at`), then commits — the recompute-compare passes because the issued and re-resolved hashes now agree. There is no path that bypasses the recompute; "force" only means "re-preview then send", never "skip the drift check".

- [ ] **Step 6: Register the routes** —

In `routes/api.php`: `Route::post('/batch-studio/reminders/preview', [BatchStudioPreviewController::class, 'previewReminders'])->middleware('can:view_finance_operations_due_calendar')->name('batch-studio.reminders.preview');`

In `routes/web.php` (`batch-studio` group): `Route::post('/reminders', [BatchStudioController::class, 'commitReminders'])->middleware('can:view_finance_operations_due_calendar')->name('reminders.commit');`

- [ ] **Step 7: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Batch/BatchReminderCommitTest.php`
Expected: PASS (2 passed).

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Finance/Queries/Batch/AssembleBatchReminderPreviewQuery.php app/Modules/Finance/Http/Requests/Batch/PreviewBatchRemindersRequest.php app/Modules/Finance/Http/Requests/Batch/CommitBatchRemindersRequest.php app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php app/Modules/Finance/Http/Web/Admin/BatchStudioController.php app/Modules/Finance/routes/api.php app/Modules/Finance/routes/web.php tests/Feature/Finance/Batch/BatchReminderCommitTest.php
git commit -m "feat(finance): add Batch Studio reminder preview + commit (last_reminder_at drift guard)"
```

## Task 10: Frontend types + `useBatchStudio` composable (wizard state machine + token round-trip)

The state machine shared by all three job pages. Preview is a JSON read (`useApiRequest` — never `axios`); commit is an Inertia write (`useForm`). The token from preview is carried into commit; a `preview_token` error from the server forces the wizard back to step ② with a "data changed — review again" banner.

> **No JS unit runner** in this repo — frontend tasks verify with `./scripts/dev.sh npm run lint -- <files>` and the Task 15 browser smoke. Do **not** run whole-project `vue-tsc` (it OOMs in the dev container).

**Files:**
- Modify: `resources/js/types/finance.ts`
- Create: `resources/js/composables/useBatchStudio.ts`

- [ ] **Step 1: Add the types** — append to `resources/js/types/finance.ts`:

```ts
export type BatchDiffBucket = 'create' | 'update' | 'skip' | 'warning';

export interface BatchPreviewLineDisplay {
    student_id: string;
    label: string;
    diff: BatchDiffBucket;
    gross?: number;
    discount?: number;
    net: number;
    installment_aware_total?: number;
    reason?: string | null;
    warning_codes?: string[];
}

export interface BatchPreviewLineClient {
    key: string;
    display: BatchPreviewLineDisplay;
}

export interface BatchPreviewResponse {
    preview_token: string;
    lines: BatchPreviewLineClient[];
    summary: Record<string, number>;
}

export type BatchJobKind = 'charge_generation' | 'dng_push' | 'reminder';

export interface BatchResult {
    job: BatchJobKind;
    fee_category?: string;
    summary: Record<string, unknown> & { errors?: string[] };
}
```

- [ ] **Step 2: Write the composable**

Create `resources/js/composables/useBatchStudio.ts`:

```ts
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
// Confirm the exact JSON-read composable name + signature in docs/RULES_vue-form-useApi.md
// and docs/rules/api-interaction.md. It MUST be useApi/useApiRequest — never axios, never response()->json().
import { useApiRequest } from '@/composables/useApiRequest';
import type { BatchPreviewLineClient, BatchPreviewResponse, BatchResult } from '@/types/finance';

interface BatchStudioConfig<TSetup extends Record<string, unknown>> {
    previewUrl: string;
    commitUrl: string;
    defaultSetup: TSetup;
    /** Extra fields sent only at commit (DNG: due_date/description/…; reminder: force_resend). */
    commitExtras?: () => Record<string, unknown>;
    /** Default inclusion at step ②. Default: everything except 'skip'. */
    defaultInclude?: (line: BatchPreviewLineClient) => boolean;
}

export function useBatchStudio<TSetup extends Record<string, unknown>>(config: BatchStudioConfig<TSetup>) {
    const step = ref<1 | 2 | 3 | 4>(1);
    const setup = reactive({ ...config.defaultSetup });
    const lines = ref<BatchPreviewLineClient[]>([]);
    const summary = ref<Record<string, number>>({});
    const previewToken = ref<string | null>(null);
    const selected = ref<Set<string>>(new Set());
    const driftMessage = ref<string | null>(null);

    const include = config.defaultInclude ?? ((l: BatchPreviewLineClient) => l.display.diff !== 'skip');
    const preview = useApiRequest<BatchPreviewResponse>();

    async function runPreview(): Promise<void> {
        driftMessage.value = null;
        const res = await preview.post(config.previewUrl, { ...setup });
        const data = res.data; // ApiResponse envelope → { data: BatchPreviewResponse }
        lines.value = data.lines;
        summary.value = data.summary;
        previewToken.value = data.preview_token;
        selected.value = new Set(data.lines.filter(include).map((l) => l.key));
        step.value = 2;
    }

    function toggle(key: string): void {
        const next = new Set(selected.value);
        next.has(key) ? next.delete(key) : next.add(key);
        selected.value = next;
    }

    function excludeWarnings(): void {
        selected.value = new Set(
            [...selected.value].filter((k) => lines.value.find((l) => l.key === k)?.display.diff !== 'warning'),
        );
    }

    const form = useForm<{ preview_token: string; selected_keys: string[] } & Record<string, unknown>>({
        preview_token: '',
        selected_keys: [],
    });

    function commit(): void {
        form.preview_token = previewToken.value ?? '';
        form.selected_keys = [...selected.value];
        Object.assign(form, config.commitExtras?.() ?? {});
        form.post(config.commitUrl, {
            preserveScroll: true,
            onSuccess: () => {
                step.value = 4;
            },
            onError: (errors) => {
                if (errors.preview_token) {
                    driftMessage.value = errors.preview_token;
                    step.value = 2; // any drift forces a fresh preview
                }
            },
        });
    }

    const counts = computed(() => {
        const by: Record<string, number> = { create: 0, update: 0, skip: 0, warning: 0 };
        for (const l of lines.value) by[l.display.diff]++;
        return by;
    });

    // page.flash (v3), NOT page.props.flash
    const result = computed<BatchResult | null>(
        () => ((usePage().flash as Record<string, unknown> | undefined)?.batch_result as BatchResult) ?? null,
    );

    return {
        step, setup, lines, summary, previewToken, selected, driftMessage,
        counts, result, form, previewing: preview.loading,
        runPreview, toggle, excludeWarnings, commit,
    };
}
```

- [ ] **Step 3: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/composables/useBatchStudio.ts resources/js/types/finance.ts`
Expected: no errors (fix any import-path/`any` issues; the composable adds no `any`).

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/finance.ts resources/js/composables/useBatchStudio.ts
git commit -m "feat(finance): add useBatchStudio composable + batch types"
```

---

## Task 11: Frontend — shared `BatchWizard` shell + `Hub` landing

The 4-step stepper + sticky summary bar (§6.1) reused by all three jobs, and the hub that shows only runnable job tiles.

**Files:**
- Create: `resources/js/components/finance/batch/BatchWizard.vue`
- Create: `resources/js/pages/Finance/BatchStudio/Hub.vue`

- [ ] **Step 1: Write the wizard shell**

Create `resources/js/components/finance/batch/BatchWizard.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    step: 1 | 2 | 3 | 4;
    summaryText?: string;
    nextLabel?: string;
    nextDisabled?: boolean;
    canBack?: boolean;
}>();

const emit = defineEmits<{ (e: 'next'): void; (e: 'back'): void }>();

const steps = ['Thiết lập', 'Xem trước', 'Xác nhận', 'Kết quả'] as const;
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- stepper -->
        <ol class="flex items-center gap-2 text-sm">
            <li
                v-for="(label, i) in steps"
                :key="label"
                class="flex items-center gap-2"
                :class="step === i + 1 ? 'font-semibold text-foreground' : 'text-muted-foreground'"
            >
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full border text-xs"
                    :class="step > i + 1 ? 'border-emerald-500 bg-emerald-500 text-white' : step === i + 1 ? 'border-foreground' : 'border-muted'"
                >{{ i + 1 }}</span>
                {{ label }}
                <span v-if="i < steps.length - 1" class="mx-1 text-muted-foreground">→</span>
            </li>
        </ol>

        <div class="min-h-[24rem]">
            <slot :step="step" />
        </div>

        <!-- sticky summary bar -->
        <div class="sticky bottom-0 flex items-center justify-between gap-4 border-t bg-background/95 py-3 backdrop-blur">
            <p class="text-sm text-muted-foreground tabular-nums">{{ summaryText }}</p>
            <div class="flex gap-2">
                <Button v-if="canBack" variant="outline" @click="emit('back')">Quay lại</Button>
                <Button v-if="step < 4" :disabled="nextDisabled" @click="emit('next')">{{ nextLabel ?? 'Tiếp' }}</Button>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Write the hub**

Create `resources/js/pages/Finance/BatchStudio/Hub.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { GraduationCap, Send, Bell } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { financeRoutes } from '@/utils/routes';

defineProps<{ jobs: { charge_generation: boolean; dng_push: boolean; reminder: boolean } }>();

const tiles = [
    { key: 'charge_generation', title: 'Sinh phí hàng loạt', desc: 'HP · EGC · Phí phi học vụ', icon: GraduationCap, href: financeRoutes.batchStudio.charges() },
    { key: 'dng_push', title: 'Đẩy DNG hàng loạt', desc: 'Tạo yêu cầu thanh toán', icon: Send, href: financeRoutes.batchStudio.dng() },
    { key: 'reminder', title: 'Nhắc nợ hàng loạt', desc: 'Gửi email SV / phụ huynh', icon: Bell, href: financeRoutes.batchStudio.reminders() },
] as const;
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl p-6">
            <h1 class="text-2xl font-semibold">Batch Studio</h1>
            <p class="mt-1 text-muted-foreground">Một khuôn xem trước → xác nhận → kết quả cho mọi việc hàng loạt.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <component
                    :is="jobs[tile.key] ? Link : 'div'"
                    v-for="tile in tiles"
                    :key="tile.key"
                    :href="jobs[tile.key] ? tile.href : undefined"
                    class="rounded-xl border p-5 transition"
                    :class="jobs[tile.key] ? 'hover:border-foreground hover:shadow-sm' : 'cursor-not-allowed opacity-50'"
                >
                    <component :is="tile.icon" class="h-6 w-6" />
                    <h2 class="mt-3 font-semibold">{{ tile.title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ tile.desc }}</p>
                    <p v-if="!jobs[tile.key]" class="mt-2 text-xs text-muted-foreground">Bạn không có quyền chạy việc này.</p>
                </component>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 3: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/batch/BatchWizard.vue resources/js/pages/Finance/BatchStudio/Hub.vue`
Expected: no errors. (If `AppLayout` import path differs, match the path used by an existing Finance page, e.g. `resources/js/pages/Finance/Cockpit/Index.vue`.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/finance/batch/BatchWizard.vue resources/js/pages/Finance/BatchStudio/Hub.vue
git commit -m "feat(finance): add Batch Studio wizard shell + hub landing"
```

---

## Task 12: Frontend — `PreviewDiffTable` + `BatchResultPanel`

The two shared display components: the 4-label diff table (🟢/🔵/⚪/🟠 + quick filters + search + Excel export) and the result panel (✅/⚪/🔴 + Student 360 deep links + retry-failed-subset).

**Files:**
- Create: `resources/js/components/finance/batch/PreviewDiffTable.vue`
- Create: `resources/js/components/finance/batch/BatchResultPanel.vue`

- [ ] **Step 1: Write the diff table**

Create `resources/js/components/finance/batch/PreviewDiffTable.vue`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import type { BatchDiffBucket, BatchPreviewLineClient } from '@/types/finance';

const props = defineProps<{
    lines: BatchPreviewLineClient[];
    selected: Set<string>;
    counts: Record<string, number>;
    exportable?: boolean;
}>();

const emit = defineEmits<{ (e: 'toggle', key: string): void; (e: 'export'): void }>();

const filter = ref<BatchDiffBucket | 'all'>('all');
const search = ref('');

const bucketMeta: Record<BatchDiffBucket, { label: string; class: string }> = {
    create: { label: 'Tạo mới', class: 'bg-emerald-100 text-emerald-800' },
    update: { label: 'Cập nhật-gộp', class: 'bg-blue-100 text-blue-800' },
    skip: { label: 'Bỏ qua', class: 'bg-muted text-muted-foreground' },
    warning: { label: 'Cảnh báo', class: 'bg-amber-100 text-amber-800' },
};

const rows = computed(() =>
    props.lines.filter((l) => {
        const okBucket = filter.value === 'all' || l.display.diff === filter.value;
        const q = search.value.trim().toLowerCase();
        const okSearch = !q || l.display.label.toLowerCase().includes(q) || l.display.student_id.toLowerCase().includes(q);
        return okBucket && okSearch;
    }),
);

const vnd = (n: number) => new Intl.NumberFormat('vi-VN').format(n);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <Button :variant="filter === 'all' ? 'default' : 'outline'" size="sm" @click="filter = 'all'">Tất cả</Button>
            <Button v-for="(meta, key) in bucketMeta" :key="key" size="sm"
                :variant="filter === key ? 'default' : 'outline'" @click="filter = key as BatchDiffBucket">
                {{ meta.label }} ({{ counts[key] ?? 0 }})
            </Button>
            <Input v-model="search" placeholder="Tìm 1 SV…" class="ml-auto w-48" />
            <Button v-if="exportable" variant="outline" size="sm" @click="emit('export')">Xuất Excel</Button>
        </div>

        <table class="w-full text-sm">
            <thead class="border-b text-left text-muted-foreground">
                <tr><th class="w-8 py-2"></th><th>SV</th><th>Phân loại</th><th class="text-right">Số tiền</th><th>Lý do</th></tr>
            </thead>
            <tbody>
                <tr v-for="l in rows" :key="l.key" class="border-b">
                    <td class="py-2">
                        <Checkbox :model-value="selected.has(l.key)" :disabled="l.display.diff === 'skip'" @update:model-value="emit('toggle', l.key)" />
                    </td>
                    <td><span class="font-medium">{{ l.display.label }}</span> <span class="text-muted-foreground">{{ l.display.student_id }}</span></td>
                    <td><Badge :class="bucketMeta[l.display.diff].class">{{ bucketMeta[l.display.diff].label }}</Badge></td>
                    <td class="text-right tabular-nums">{{ vnd(l.display.net) }}</td>
                    <td class="text-muted-foreground">{{ l.display.reason ?? '' }}</td>
                </tr>
                <tr v-if="rows.length === 0"><td colspan="5" class="py-6 text-center text-muted-foreground">Không có dòng nào khớp bộ lọc.</td></tr>
            </tbody>
        </table>
    </div>
</template>
```

- [ ] **Step 2: Write the result panel**

Create `resources/js/components/finance/batch/BatchResultPanel.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { financeRoutes } from '@/utils/routes';
import type { BatchResult } from '@/types/finance';

const props = defineProps<{ result: BatchResult }>();
const emit = defineEmits<{ (e: 'retryFailed'): void; (e: 'restart'): void }>();

const s = computed(() => props.result.summary as Record<string, number | string[]>);
const errors = computed(() => (props.result.summary.errors as string[] | undefined) ?? []);
const canRetry = computed(() => props.result.job !== 'charge_generation' && errors.value.length > 0);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg border p-4">
                <p class="text-2xl font-semibold text-emerald-600 tabular-nums">{{ s.created ?? s.created_count ?? s.sent_count ?? 0 }}</p>
                <p class="text-sm text-muted-foreground">✅ Thành công</p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-2xl font-semibold text-muted-foreground tabular-nums">{{ s.skipped ?? s.skipped_count ?? 0 }}</p>
                <p class="text-sm text-muted-foreground">⚪ Bỏ qua</p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-2xl font-semibold text-red-600 tabular-nums">{{ s.failed ?? s.failed_count ?? 0 }}</p>
                <p class="text-sm text-muted-foreground">🔴 Lỗi</p>
            </div>
        </div>

        <p v-if="result.job === 'dng_push' && (s.cancelled_old as number) > 0" class="text-sm text-amber-700">
            ⚠️ Đã hủy {{ s.cancelled_old }} DNG cũ (cùng SV + loại phí) trước khi tạo mới.
        </p>

        <div v-if="errors.length" class="rounded-lg border border-red-200 p-3">
            <p class="mb-2 font-medium text-red-700">Các lỗi từng SV</p>
            <ul class="space-y-1 text-sm">
                <li v-for="(err, i) in errors" :key="i" class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground">{{ err }}</span>
                </li>
            </ul>
        </div>

        <div class="flex gap-2">
            <Button v-if="canRetry" variant="outline" @click="emit('retryFailed')">Thử lại các dòng lỗi</Button>
            <Button variant="ghost" @click="emit('restart')">Chạy lô mới</Button>
        </div>
    </div>
</template>
```

> **Student 360 deep links:** when the result errors carry a `student_id` (extend the Action error strings or return a structured `failures: [{student_id, message}]` from the commit controller in a follow-up if richer linking is wanted), render each as `<Link :href="financeRoutes.students.overview(student_id)">`. v1 may show plain error strings; the structured version is a small, separate enhancement.

- [ ] **Step 3: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/batch/PreviewDiffTable.vue resources/js/components/finance/batch/BatchResultPanel.vue`
Expected: no errors. (Match the real `@/components/ui/*` export names — `Checkbox`, `Badge`, `Input`, `Button` — against an existing Finance page's imports.)

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/finance/batch/PreviewDiffTable.vue resources/js/components/finance/batch/BatchResultPanel.vue
git commit -m "feat(finance): add Batch Studio preview diff table + result panel"
```

---

## Task 13: Frontend — charge-generation wizard page (the reference job)

Wires the shell + composable + shared components for sinh phí: step ① fee-category + semester (default from the shared `semester` prop) + scope; step ② diff table + Excel export; step ③ confirm + exclude-warnings + big-batch ack; step ④ result. This is the reference page Tasks 14 mirrors.

**Files:**
- Create: `resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue`

- [ ] **Step 1: Write the page**

Create `resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import BatchWizard from '@/components/finance/batch/BatchWizard.vue';
import PreviewDiffTable from '@/components/finance/batch/PreviewDiffTable.vue';
import BatchResultPanel from '@/components/finance/batch/BatchResultPanel.vue';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { financeRoutes } from '@/utils/routes';
import type { BatchResult } from '@/types/finance';

defineProps<{ feeTypeOptions?: { value: string; label: string }[] }>();

const semester = computed(() => (usePage().props.semester as { selected_id: number | null } | null)?.selected_id ?? null);

const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.chargesPreview(),
    commitUrl: financeRoutes.batchStudio.chargesCommit(),
    defaultSetup: { fee_category: 'major' as 'major' | 'egc' | 'non_academic', semester_id: semester.value, scope: { filters: {} } },
});

const ack = computed({
    get: () => Boolean(wizard.form.acknowledged),
    set: (v: boolean) => (wizard.form.acknowledged = v),
});

const needsAck = computed(() => wizard.selected.value.size > 50);
const nextDisabled = computed(() => wizard.step.value === 3 && needsAck.value && !ack.value);

const summaryText = computed(() => {
    if (wizard.step.value === 1) return 'Chọn loại phí và phạm vi';
    const c = wizard.counts.value;
    return `${wizard.selected.value.size} đã chọn · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function onNext() {
    if (wizard.step.value === 1) return void wizard.runPreview();
    if (wizard.step.value === 2) return void (wizard.step.value = 3);
    if (wizard.step.value === 3) return wizard.commit();
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl p-6">
            <h1 class="text-2xl font-semibold">Sinh phí hàng loạt</h1>

            <Alert v-if="wizard.driftMessage.value" variant="destructive" class="my-3">
                <AlertDescription>{{ wizard.driftMessage.value }}</AlertDescription>
            </Alert>

            <BatchWizard
                :step="wizard.step.value"
                :summary-text="summaryText"
                :next-disabled="nextDisabled || wizard.previewing.value"
                :can-back="wizard.step.value > 1 && wizard.step.value < 4"
                :next-label="wizard.step.value === 3 ? 'Chạy' : 'Tiếp'"
                @next="onNext"
                @back="wizard.step.value--"
            >
                <template #default="{ step }">
                    <!-- ① Setup -->
                    <div v-if="step === 1" class="grid max-w-md gap-4">
                        <label class="grid gap-1 text-sm">Loại phí
                            <select v-model="wizard.setup.fee_category" class="rounded border p-2">
                                <option value="major">HP (Tuition)</option>
                                <option value="egc">EGC</option>
                                <option value="non_academic">Phi học vụ</option>
                            </select>
                        </label>
                        <p class="text-sm text-muted-foreground">Kỳ: theo thanh trên cùng (#{{ wizard.setup.semester_id ?? '—' }}).</p>
                    </div>

                    <!-- ② Preview -->
                    <PreviewDiffTable
                        v-else-if="step === 2"
                        :lines="wizard.lines.value"
                        :selected="wizard.selected.value"
                        :counts="wizard.counts.value"
                        exportable
                        @toggle="wizard.toggle"
                    />

                    <!-- ③ Confirm -->
                    <div v-else-if="step === 3" class="grid gap-4">
                        <p class="text-sm">{{ summaryText }}</p>
                        <button class="text-left text-sm text-blue-600 underline" @click="wizard.excludeWarnings()">
                            Loại trừ tất cả dòng 🟠 cảnh báo
                        </button>
                        <label v-if="needsAck" class="flex items-center gap-2 text-sm">
                            <Checkbox v-model="ack" /> Tôi đã rà soát preview trước khi chạy.
                        </label>
                    </div>

                    <!-- ④ Result -->
                    <BatchResultPanel
                        v-else-if="step === 4 && wizard.result.value"
                        :result="(wizard.result.value as BatchResult)"
                        @restart="wizard.step.value = 1"
                    />
                </template>
            </BatchWizard>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 2: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Finance/BatchStudio/ChargeGeneration.vue
git commit -m "feat(finance): add Batch Studio charge-generation wizard page"
```

---

## Task 14: Frontend — DNG-push + reminders wizard pages

Mirror Task 13 with job-specific step ① inputs and commit extras. DNG step ① adds due date / description / estimate / optional amount override (with the ad-hoc danger warning); reminders step ① picks recipient (student/parent). Both reuse `BatchWizard` + `PreviewDiffTable` + `BatchResultPanel`, and both wire retry-of-failed-subset.

**Files:**
- Create: `resources/js/pages/Finance/BatchStudio/DngPush.vue`
- Create: `resources/js/pages/Finance/BatchStudio/Reminders.vue`

- [ ] **Step 1: Write `DngPush.vue`** — same shape as Task 13 with these differences:

```ts
// setup + commit extras
const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.dngPreview(),
    commitUrl: financeRoutes.batchStudio.dngCommit(),
    defaultSetup: { semester_id: semester.value, dng_fee_type: 'tuition', student_ids: [] as number[] },
    commitExtras: () => ({
        due_date: form.due_date,
        description: form.description,
        estimate_time: form.estimate_time,
        amount_overrides: form.amount_overrides, // { [studentId]: number }
    }),
});
```

The DNG step ③ MUST show two danger banners driven by the preview line `warning_codes`:

```vue
<Alert v-if="hasOverrideDrift" variant="destructive">
    <AlertDescription>
        Số tiền ghi đè lệch tổng tính được (≥1 VND) ở {{ overrideDriftCount }} SV → khoản này thành
        <strong>ad-hoc</strong>, <strong>bỏ liên kết đợt (installment)</strong> và phải
        <strong>đối soát thủ công</strong>. Không phải "chỉnh số cho tiện".
    </AlertDescription>
</Alert>
<Alert v-if="rerunCancelsCount > 0" variant="destructive">
    <AlertDescription>
        {{ rerunCancelsCount }} SV đã có DNG đang chờ — chạy lại sẽ <strong>hủy DNG cũ</strong> rồi tạo mới.
    </AlertDescription>
</Alert>
```

where `rerunCancelsCount = lines.filter(l => l.display.warning_codes?.includes('rerun_cancels_old_dng')).length` and `hasOverrideDrift` is computed client-side by comparing each `amount_overrides[studentId]` against the line's `display.installment_aware_total` with a ≥1 VND tolerance (mirrors the backend `abs(override - total) >= 1.0` rule so the UI warns before submit). The step ④ panel passes `@retry-failed="retryFailedSubset"`, which re-runs preview filtered to the failed student IDs, then commits.

- [ ] **Step 2: Write `Reminders.vue`** — same shape with:

```ts
const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.remindersPreview(),
    commitUrl: financeRoutes.batchStudio.remindersCommit(),
    defaultSetup: { recipient: 'student' as 'student' | 'parent', semester_id: semester.value },
    commitExtras: () => ({ force_resend: Boolean(force.value) }),
    defaultInclude: (l) => l.display.diff === 'create', // never auto-select already-reminded (🟠) or no-email (⚪)
});
```

Reminders step ② shows the skip reasons (`no_email`, `recently_reminded`); the 🟠 `recently_reminded` lines are excluded by default. A "Buộc gửi lại" toggle (`force_resend`) re-runs preview first (so the token reflects current `last_reminder_at`) and only then allows including them — there is no client path that bypasses the server recompute.

- [ ] **Step 3: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/BatchStudio/DngPush.vue resources/js/pages/Finance/BatchStudio/Reminders.vue`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Finance/BatchStudio/DngPush.vue resources/js/pages/Finance/BatchStudio/Reminders.vue
git commit -m "feat(finance): add Batch Studio DNG-push + reminders wizard pages"
```

---

## Task 15: Nav entry + milestone evidence (acceptance gate)

Add Batch Studio to the sidebar, then prove the milestone: token round-trip, drift-block, per-job result models, and **zero new CRITICAL finance invariants** after exercising the real write paths.

**Files:**
- Modify: `resources/js/constants/menu-sidebar.ts`
- Modify: `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md`

- [ ] **Step 1: Add the nav entry** — in `resources/js/constants/menu-sidebar.ts`, in the "Finance Office" → "Sinh phí" group `children` array (after the `EGC · Carry Forward` entry, around line 333), add:

```ts
                    { title: 'Batch Studio', href: financeRoutes.batchStudio.hub(), icon: Layers, requiredPermissions: ['view_finance_batch_studio'] },
```

> `Layers` is already imported (used by the existing "Batch Charges" entry). If a distinct icon is preferred, import one from `lucide-vue-next` alongside the others.

- [ ] **Step 2: Lint the menu**

Run: `./scripts/dev.sh npm run lint -- resources/js/constants/menu-sidebar.ts`
Expected: no errors.

- [ ] **Step 3: Run the full Batch backend suite**

Run: `./scripts/dev.sh test tests/Unit/Finance/Batch tests/Feature/Finance/Batch`
Expected: all green (hasher 5 + line 3 + token 8 + charge-mapping 3 + charge-preview 2 + charge-commit 4 + dng-commit 3 + reminder-commit 2 + authz 3).

- [ ] **Step 4: Capture the invariant baseline**

Run: `./scripts/dev.sh artisan finance:audit-invariants`
Record the CRITICAL/HIGH counts (this is the "before" snapshot).

- [ ] **Step 5: Browser smoke (all three jobs)** — `./scripts/dev.sh start`, then as a `truong_phong`/`can_bo` user with the relevant permissions:
  1. **Charge gen:** Batch Studio → Sinh phí → preview (see 🟢/🔵/⚪/🟠 buckets + token issued) → confirm → run → result summary. ✔ verify charges created via `/finance/charges`.
  2. **Drift block:** open a charge-gen preview; in another tab change a relevant fee/scholarship for one previewed student; commit → expect the "X dòng đã thay đổi" block + forced re-preview (no write).
  3. **DNG push:** preview with one student who already has an awaiting DNG → see the 🟠 "rerun cancels old DNG" warning; enter an override differing ≥1 VND → see the ad-hoc danger banner; run → result shows `cancelled_old` + per-student success/failure; retry the failed subset only.
  4. **Reminders:** preview students → already-reminded ones are 🟠 and excluded by default; send → result counts; re-running immediately is blocked by the `last_reminder_at` drift unless "Buộc gửi lại" re-previews first.

- [ ] **Step 6: Re-run invariants (acceptance gate)**

Run: `./scripts/dev.sh artisan finance:audit-invariants`
Expected: **no new CRITICAL** invariant breaks vs Step 4. If any appears, STOP — a write path is wrong; do not mark the milestone complete.

- [ ] **Step 7: Record evidence** — append an "M4 Batch Studio acceptance" section to the umbrella story's `validation.md`: the backend suite result (Step 3), the before/after invariant counts (Steps 4 & 6), and the four smoke outcomes (Step 5). Mirror the M1/M2 evidence format already in that file.

- [ ] **Step 8: Commit**

```bash
git add resources/js/constants/menu-sidebar.ts docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md
git commit -m "feat(finance): mount Batch Studio in sidebar + record M4 acceptance evidence"
```

---

## Self-Review

Checked the plan against the design spec (§6 Batch Studio, §7.1 pattern 3, §8 contracts, §10 milestone 4) with fresh eyes:

**1. Spec coverage**
- §6.1 one shared 4-step wizard + sticky summary → Task 11 `BatchWizard` (stepper + sticky bar), reused by Tasks 13–14. ✓
- §6.2 setup (semester default from top bar, fee type, scope; CSV/filter; DNG override; reminder recipient) → Tasks 13 (charge), 14 (DNG/reminder). CSV-upload scope for non-academic is **delegated to the existing `/finance/operations/generate-charges` page** (kept, per non-goals) and re-resolved through `PreviewChargeGenerationQuery`; the wizard's non-academic path covers all-eligible/filter scope, with CSV flagged as a follow-up (see Unresolved). ⚠️ documented.
- §6.2 batch limit + chunking (DNG ≤100) → `CommitBatchDngRequest` `selected_keys` `max:100` (Task 8) + UI chunk note (Task 14). ✓
- §6.2 override → ad-hoc danger → backend already drops linkage (verified `:173–190`); UI danger banner mirrors `abs(override-total)>=1.0` (Task 14). ✓
- §6.3 preview == commit (shared resolvers) + preview-token contract (per-line hash, exclude-warnings subset, one-time, 30-min TTL, recompute-compare on the **re-resolved** subset, fee-config fingerprint without `version`) → Tasks 2/3/4 + server-side re-resolve in Tasks 7/8/9. ✓ (corrected from the client-echo first draft.)
- §6.4 confirm (1-line summary, exclude warnings, big-batch ack, per-job rerun reassurance) → Task 13 step ③ + Task 14 banners. ✓
- §6.5 result (charge gen = single-transaction-envelope summary v1; DNG/reminders = per-recipient partial success + retry failed subset) → `BatchResultPanel` (Task 12) + per-job summaries (Tasks 7/8/9). ✓
- §6.6 three jobs share the frame → Tasks 13/14. ✓
- §8 per-action permissions (no umbrella batch perm; DNG also needs `void_finance_charges`; reminders gate due-calendar) → Tasks 1/7/8/9. ✓
- §8 frontend contracts (preview = `useApi`/`useApiRequest` JSON + `ApiResponse`; commit = `useForm` Inertia + `Inertia::flash` read via `page.flash`; `route()`/`financeRoutes` not literals; `useDataTable` not needed here as the diff table is a single non-paginated preview set) → Tasks 6/10/13. ✓

**2. Placeholder scan** — no "TBD"/"add validation"/"similar to Task N"/"handle edge cases". Every step has real code or an explicit verify-then-write note grounded in a `path:line`. The two prose-condensed FE pages (Task 14) reference Task 13's full page as the concrete template and list only the deltas — acceptable since Task 13 is shown in full.

**3. Type/name consistency** — `BatchPreviewLine` (`key`, `hashPayload`, `display`, `hash()`, `toClientArray()`, `toTokenPayload()`), `BatchJobType` (`ChargeGeneration`/`DngPush`/`Reminder`, `cacheNamespace()`), `BatchPreviewTokenService` (`issue`/`verify`/`consume`/`scope`), `recomputeOrFail`, `selected_keys`, `financeRoutes.batchStudio.*`, `page.flash.batch_result` — all consistent across backend tasks, FE composable, and FE pages. The token service signature (`key => hashPayload`) is fed by server re-resolution in every commit (never client input). ✓

No gaps requiring new tasks were found.

## Unresolved Questions

- **EGC-only operators:** the charge preview/commit routes gate `create_finance_charges` as the baseline, with an in-controller `generate_egc_finance_charges` check for `fee_category=egc`. An operator who has *only* `generate_egc_finance_charges` (not `create_finance_charges`) cannot reach the EGC path. Confirm at story time whether that role exists; if so, split the EGC preview/commit onto their own routes gated by the EGC permission.
- **Non-academic CSV scope inside the wizard:** v1 covers all-eligible/filter scope and keeps the existing CSV upload page. Confirm whether CSV upload should move into the wizard step ① (would add a file-upload + per-row validation sub-flow) or stay on the legacy page.
- **`useApi`/`useApiRequest` exact signature:** the composable assumes a `useApiRequest().post(url, body) → { data }` shape returning the `ApiResponse` envelope. Confirm against `docs/RULES_vue-form-useApi.md` + `docs/rules/api-interaction.md` and adjust; do not fall back to `axios`.
- **Preview-query row keys:** the assemblers assume the existing `Preview*Query` rows expose `gross_amount`/`discount_amount`/`scholarship_id`/`fee_plan_id`/`fee_plan_updated_at` (charge) and `charge_ids`/`installment_aware_total`/`has_active_dng` (DNG) and `last_reminder_at`/`has_*_email` (reminders). Where missing, extend the query row builders (read-only, separate tested commits) — confirm which keys already exist before Task 6/8/9.
- **Async result for very large batches:** v1 commits synchronously (design §6.5 v1). The design defers per-student-transaction + live progress (chunk/queue) to a separate story. Confirm volume thresholds (design §11 open question) to decide if that follow-up is needed this cycle.

---

**Plan complete and saved to `docs/superpowers/plans/2026-06-15-finance-office-batch-studio.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints for review.

**Which approach?**

