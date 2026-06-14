# Finance Operations Stubs and Performance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Finance Operations pages truthful (no fake-success stubs) and fix due-reminder count/performance issues so staff can rely on Operations dashboards and lists.

**Architecture:** Extract shared billing-exception detection into one collector used by both counts and list queries; encode exception identity as a stable synthetic ID (`type` + `source_id`). Replace PHP-side full-table loads with DB pagination and eager-loaded settlement snapshots. Align due-date bucket boundaries between summary and list. Rename the misleading "Due Calendar" label to reflect the DNG reminder queue.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Inertia v3 (Vue 3), MySQL 8, Docker wrappers via `./scripts/dev.sh`.

**Story packet:** `docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/`

**Portal impact:** `none`

**Depends on:** `FIN-REV-001` (safety gates). Does **not** re-open DNG security (`S-005`) or lifecycle exception page work (`E-finance-lifecycle-exceptions`).

---

## Scope Guardrails

In scope (review IDs):

| ID | Work |
| --- | --- |
| FIN-20 | Remove fake-success `FixBillingExceptionAction` |
| FIN-21 | Implement `ListBillingExceptionsQuery` |
| FIN-22 | Honor `priorityOrder` in allocation line sorting |
| FIN-26 | Align `upcoming` bucket between summary and list |
| FIN-27 | DB-paginate `ListDueItemsQuery` |
| FIN-28 | Verify `retake_unpaid_count` uses invoice lifecycle status (regression test) |
| FIN-29 | Remove N+1 in `GetDueInvoicesSummaryQuery` overdue amount |
| DB-23 | Rewrite lifecycle `whereHas` to JOIN where practical |
| UI-IA-1 | Rename "Due Calendar" → DNG due reminder queue |

Out of scope:

- Full `GetBillingDashboardStatsQuery` SQL rewrite (DB-22 dashboard slice — defer unless validation proves it blocks this story).
- `mismatch` exception auto-detection/fix (counts hard-coded to `0`; keep `fixable: false`).
- `defer_no_case` auto-fix (needs fee-policy human choice — return honest unsupported).
- BOD charts (`S-008`), ledger canonical refactor (`S-002`).

**Stop condition (human gate):** If product wants auto-fix for `defer_no_case` with a default `fee_policy`, pause and confirm policy before implementing Task 3 Step 3b.

---

## File Structure

Create:

- `app/Modules/Finance/Support/BillingExceptionIdentifier.php` — encode/decode synthetic exception IDs.
- `app/Modules/Finance/Support/BillingExceptionCollector.php` — shared detection for all exception types.
- `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php` — list + fix honesty.
- `tests/Feature/Finance/Operations/DueItemsSummaryParityTest.php` — FIN-26 bucket boundaries.
- `tests/Feature/Finance/Operations/ListDueItemsQueryPaginationTest.php` — FIN-27 DB pagination.
- `tests/Unit/Finance/SettlementServicePriorityOrderTest.php` — FIN-22 ordering.
- `tests/Feature/Finance/Operations/GetDueInvoicesSummaryQueryTest.php` — FIN-29 eager-load path.
- `tests/Feature/Finance/Operations/RetakeUnpaidCountTest.php` — FIN-28 regression.
- `tests/Unit/Finance/Operations/LifecycleDueItemPredicateJoinTest.php` — DB-23 join scope.

Modify:

- `app/Modules/Finance/Queries/Operations/GetBillingExceptionCountsQuery.php` — delegate to collector.
- `app/Modules/Finance/Queries/Operations/ListBillingExceptionsQuery.php` — real paginated rows.
- `app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php` — real fix or honest error.
- `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php` — map exceptions to `ApiResponse::error()`.
- `app/Modules/Finance/Queries/Operations/GetDueItemsSummaryQuery.php` — FIN-26 boundary fix.
- `app/Modules/Finance/Queries/Operations/GetDueInvoicesSummaryQuery.php` — FIN-26 + FIN-29.
- `app/Modules/Finance/Queries/Operations/ListDueItemsQuery.php` — FIN-27 DB pagination.
- `app/Modules/Finance/Services/SettlementService.php` — FIN-22 priority sort.
- `app/Modules/Finance/Support/LifecycleDueItemPredicate.php` — DB-23 JOIN scope.
- `resources/js/pages/Finance/Operations/ExceptionsQueue.vue` — `fixable` guard + error handling.
- `resources/js/pages/Finance/Operations/DueCalendar.vue` — UI-IA-1 labels.
- `resources/js/pages/Finance/Operations/LifecycleExceptions.vue` — link label.
- `resources/js/constants/menu-sidebar.ts` — sidebar label.
- `docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/validation.md` — evidence after implementation.

---

### Task 0: Harness Intake and Story Registration

**Files:**
- Modify: `docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/validation.md` (evidence section only at end)

- [ ] **Step 1: Record intake**

Run:

```bash
./scripts/harness intake \
  --type spec_slice \
  --summary "S-006: Finance operations stubs, due reminder parity, query performance" \
  --lane high_risk \
  --flags "public-contracts,existing-behavior,weak-proof,multi-domain" \
  --docs "docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/"
```

Expected: harness prints a new intake row without error.

- [ ] **Step 2: Mark story in progress**

Run:

```bash
./scripts/harness story update --id FIN-REV-006-operations-stubs-and-performance --status in_progress
```

Expected: `./scripts/harness query matrix` shows `FIN-REV-006-operations-stubs-and-performance` as `in_progress`.

---

### Task 1: Shared Billing Exception Detection

**Files:**
- Create: `app/Modules/Finance/Support/BillingExceptionIdentifier.php`
- Create: `app/Modules/Finance/Support/BillingExceptionCollector.php`
- Modify: `app/Modules/Finance/Queries/Operations/GetBillingExceptionCountsQuery.php`
- Test: `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php` (first test only)

- [ ] **Step 1: Write the failing list test**

Create `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Enums\StudentActionType;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    app()->singleton('campus', fn () => $this->campus);
});

it('lists missing charge exceptions with stable encoded ids', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MISS-01',
            'intake_semester_id' => $this->semester->id,
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()
        ->forSemester($this->semester)
        ->create();

    $registration = CourseRegistration::factory()
        ->forStudent($student)
        ->forCourseOffering($offering)
        ->state(['registration_status' => 'registered'])
        ->create();

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'missing_charge', 'http://localhost/exceptions');

    expect($counts['missing_charge'])->toBe(1)
        ->and($list->total())->toBe(1)
        ->and($list->items()[0]['type'])->toBe('missing_charge')
        ->and($list->items()[0]['student_code'])->toBe('EXC-MISS-01')
        ->and($list->items()[0]['id'])->toBe(
            BillingExceptionIdentifier::encode('missing_charge', $registration->id)
        )
        ->and($list->items()[0]['fixable'])->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="lists missing charge exceptions with stable encoded ids"
```

Expected: FAIL — `ListBillingExceptionsQuery` returns `total() === 0` or missing `fixable` key.

- [ ] **Step 3: Implement identifier + collector**

Create `app/Modules/Finance/Support/BillingExceptionIdentifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

final class BillingExceptionIdentifier
{
    private const OFFSETS = [
        'missing_charge' => 1_000_000_000,
        'retake_no_charge' => 2_000_000_000,
        'defer_no_case' => 3_000_000_000,
    ];

    public static function encode(string $type, int $sourceId): int
    {
        if (! isset(self::OFFSETS[$type])) {
            throw new InvalidArgumentException("Unknown billing exception type: {$type}");
        }

        return self::OFFSETS[$type] + $sourceId;
    }

    /**
     * @return array{type: string, source_id: int}
     */
    public static function decode(int $exceptionId): array
    {
        foreach (self::OFFSETS as $type => $offset) {
            if ($exceptionId >= $offset) {
                return [
                    'type' => $type,
                    'source_id' => $exceptionId - $offset,
                ];
            }
        }

        throw new InvalidArgumentException("Unknown billing exception id: {$exceptionId}");
    }
}
```

Create `app/Modules/Finance/Support/BillingExceptionCollector.php` with three private query builders mirroring the existing count logic in `GetBillingExceptionCountsQuery`, each returning row DTOs:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BillingExceptionCollector
{
    public function collect(?int $semesterId, ?string $type, ?int $campusId): Collection
    {
        $rows = collect();

        if ($type === null || $type === 'all' || $type === 'missing_charge') {
            $rows = $rows->merge($this->missingChargeRows($semesterId, $campusId));
        }

        if ($type === null || $type === 'all' || $type === 'retake_no_charge') {
            $rows = $rows->merge($this->retakeNoChargeRows($semesterId, $campusId));
        }

        if ($type === null || $type === 'all' || $type === 'defer_no_case') {
            $rows = $rows->merge($this->deferNoCaseRows($semesterId, $campusId));
        }

        return $rows->sortByDesc('created_at')->values();
    }

    public function counts(?int $semesterId, ?int $campusId): array
    {
        return [
            'missing_charge' => $this->missingChargeRows($semesterId, $campusId)->count(),
            'retake_no_charge' => $this->retakeNoChargeRows($semesterId, $campusId)->count(),
            'defer_no_case' => $this->deferNoCaseRows($semesterId, $campusId)->count(),
            'mismatch' => 0,
        ];
    }

    private function missingChargeRows(?int $semesterId, ?int $campusId): Collection
    {
        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->get()
            ->unique('student_id')
            ->map(fn (CourseRegistration $registration) => [
                'type' => 'missing_charge',
                'source_id' => $registration->id,
                'student_id' => $registration->student_id,
                'student_code' => $registration->student?->student_id,
                'student_name' => $registration->student?->full_name,
                'description' => 'Sinh viên đăng ký học nhưng chưa có phí active trong học kỳ',
                'severity' => 'high',
                'context' => [
                    'course_registration_id' => $registration->id,
                    'semester_id' => $semesterId,
                ],
                'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => true,
            ]);
    }

    private function retakeNoChargeRows(?int $semesterId, ?int $campusId): Collection
    {
        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->where('is_retake', true)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->get()
            ->map(fn (CourseRegistration $registration) => [
                'type' => 'retake_no_charge',
                'source_id' => $registration->id,
                'student_id' => $registration->student_id,
                'student_code' => $registration->student?->student_id,
                'student_name' => $registration->student?->full_name,
                'description' => 'Sinh viên học lại nhưng chưa có phí retake active',
                'severity' => 'medium',
                'context' => [
                    'course_registration_id' => $registration->id,
                    'semester_id' => $semesterId,
                ],
                'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => true,
            ]);
    }

    private function deferNoCaseRows(?int $semesterId, ?int $campusId): Collection
    {
        return StudentActionLog::query()
            ->with(['student:id,student_id,full_name'])
            ->where('action_type', StudentActionType::ACADEMIC_DEFER->value)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->where('from_semester_id', $semesterId))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_action_log_id', 'student_action_logs.id');
            })
            ->get()
            ->map(fn (StudentActionLog $log) => [
                'type' => 'defer_no_case',
                'source_id' => $log->id,
                'student_id' => $log->student_id,
                'student_code' => $log->student?->student_id,
                'student_name' => $log->student?->full_name,
                'description' => 'Defer action ghi nhận nhưng chưa có defer_case',
                'severity' => 'medium',
                'context' => [
                    'student_action_log_id' => $log->id,
                    'from_semester_id' => $log->from_semester_id,
                ],
                'created_at' => $log->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => false,
            ]);
    }
}
```

Refactor `GetBillingExceptionCountsQuery` to:

```php
public function handle(?int $semesterId): array
{
    $campusId = app('campus')?->id;

    return app(BillingExceptionCollector::class)->counts($semesterId, $campusId);
}
```

Implement `ListBillingExceptionsQuery`:

```php
public function handle(?int $semesterId, ?string $type, string $path): LengthAwarePaginator
{
    $campusId = app('campus')?->id;
    $filterType = $type === 'all' ? null : $type;
    $rows = app(BillingExceptionCollector::class)
        ->collect($semesterId, $filterType, $campusId)
        ->map(fn (array $row) => [
            'id' => BillingExceptionIdentifier::encode($row['type'], $row['source_id']),
            'type' => $row['type'],
            'student_id' => $row['student_id'],
            'student_code' => $row['student_code'],
            'student_name' => $row['student_name'],
            'description' => $row['description'],
            'severity' => $row['severity'],
            'context' => $row['context'],
            'created_at' => $row['created_at'],
            'fixable' => $row['fixable'],
        ]);

    $page = (int) request()->get('page', 1);
    $perPage = 20;

    return new LengthAwarePaginator(
        $rows->forPage($page, $perPage)->values(),
        $rows->count(),
        $perPage,
        $page,
        ['path' => $path, 'pageName' => 'page']
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
./scripts/dev.sh test --filter="lists missing charge exceptions with stable encoded ids"
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Support/BillingExceptionIdentifier.php \
  app/Modules/Finance/Support/BillingExceptionCollector.php \
  app/Modules/Finance/Queries/Operations/GetBillingExceptionCountsQuery.php \
  app/Modules/Finance/Queries/Operations/ListBillingExceptionsQuery.php \
  tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php
git commit -m "feat(finance): implement billing exception list and shared collector"
```

---

### Task 2: Honest Fix Billing Exception Action (FIN-20)

**Files:**
- Modify: `app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php`
- Modify: `app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php`
- Modify: `resources/js/pages/Finance/Operations/ExceptionsQueue.vue`
- Test: `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php` (add fix tests)

- [ ] **Step 1: Write failing fix tests**

Append to `BillingExceptionsQueryTest.php`:

```php
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Services\FinanceChargeService;
use function Pest\Laravel\actingAs;
use App\Models\User;

it('fixes a missing charge exception by generating tuition for one student', function () {
    $user = User::factory()->create();
    actingAs($user);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-FIX-01',
            'intake_semester_id' => $this->semester->id,
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->forSemester($this->semester)->create();

    $registration = CourseRegistration::factory()
        ->forStudent($student)
        ->forCourseOffering($offering)
        ->state(['registration_status' => 'registered'])
        ->create();

    $exceptionId = BillingExceptionIdentifier::encode('missing_charge', $registration->id);

    $result = FixBillingExceptionAction::run([
        'exception_id' => $exceptionId,
        'semester_id' => $this->semester->id,
    ]);

    expect($result['fixed'])->toBeTrue();

    expect(
        FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $this->semester->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists()
    )->toBeTrue();
});

it('refuses defer_no_case auto-fix with a clear unsupported message', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create();

    $log = StudentActionLog::factory()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'from_semester_id' => $this->semester->id,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('defer_no_case', $log->id);

    expect(fn () => FixBillingExceptionAction::run(['exception_id' => $exceptionId]))
        ->toThrow(\RuntimeException::class, 'Defer case creation requires manual fee-policy selection');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./scripts/dev.sh test --filter=BillingExceptionsQueryTest
```

Expected: FAIL — `FixBillingExceptionAction` still returns fake success / no charge created.

- [ ] **Step 3: Implement honest fix action**

Replace `FixBillingExceptionAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use RuntimeException;

class FixBillingExceptionAction
{
    public static function run(array $data): array
    {
        $decoded = BillingExceptionIdentifier::decode((int) $data['exception_id']);

        return match ($decoded['type']) {
            'missing_charge' => self::fixMissingCharge($decoded['source_id'], (int) ($data['semester_id'] ?? 0)),
            'retake_no_charge' => self::fixRetakeNoCharge($decoded['source_id']),
            'defer_no_case' => throw new RuntimeException(
                'Defer case creation requires manual fee-policy selection'
            ),
            default => throw new RuntimeException('Unsupported billing exception type'),
        };
    }

    private static function fixMissingCharge(int $registrationId, int $semesterId): array
    {
        $registration = CourseRegistration::query()
            ->with(['student', 'courseOffering'])
            ->findOrFail($registrationId);

        if ($semesterId <= 0) {
            $semesterId = (int) $registration->courseOffering?->semester_id;
        }

        $result = GenerateBatchChargesAction::run([
            'semester_id' => $semesterId,
            'scope_type' => 'upload_list',
            'uploaded_student_ids' => [$registration->student?->student_id],
            'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
            'filter_enrollment_status' => 'all',
        ]);

        if (($result['created_count'] ?? 0) === 0) {
            throw new RuntimeException($result['errors'][0] ?? 'Could not create missing charge');
        }

        return [
            'fixed' => true,
            'message' => 'Created missing tuition charge',
            'details' => $result,
        ];
    }

    private static function fixRetakeNoCharge(int $registrationId): array
    {
        $registration = CourseRegistration::query()->findOrFail($registrationId);

        $charge = app(FinanceChargeService::class)->generateRetakeCharge($registration);

        if ($charge === null) {
            return [
                'fixed' => true,
                'message' => 'Retake charge skipped by defer policy',
            ];
        }

        return [
            'fixed' => true,
            'message' => 'Created retake fee charge',
            'charge_id' => $charge->id,
        ];
    }
}
```

Update API controller:

```php
public function fixException(Request $request, int $exceptionId): JsonResponse
{
    try {
        $result = FixBillingExceptionAction::run([
            'exception_id' => $exceptionId,
            'semester_id' => $request->integer('semester_id'),
        ]);

        return ApiResponse::success($result);
    } catch (\Throwable $e) {
        return ApiResponse::error($e->getMessage(), 422);
    }
}
```

Update `ExceptionsQueue.vue`:

1. Extend `ExceptionItem` with `fixable: boolean`.
2. Pass `semester_id` in fix POST body.
3. Hide/disable Fix button when `!exception.fixable`.
4. On non-OK response, read `data.message` and show toast error (do not claim success).

```ts
interface ExceptionItem {
    // ...existing fields
    fixable: boolean;
}

const runFixException = async (exception: ExceptionItem) => {
    if (!exception.fixable) {
        toast.error('Exception này cần xử lý thủ công');
        return;
    }

    const response = await fetch(route('api.finance.operations.fix-exception', exception.id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
            semester_id: semesterId.value !== 'all' ? Number(semesterId.value) : undefined,
        }),
    });

    const data = await response.json();

    if (response.ok && data.success) {
        toast.success(data.data?.message ?? 'Đã sửa lỗi thành công');
        router.reload({ only: ['exceptions', 'counts'] });
        return;
    }

    toast.error(data.message ?? 'Không thể sửa lỗi');
};
```

Template guard:

```vue
<Button
    v-if="exception.fixable"
    variant="outline"
    size="sm"
    :disabled="fixingId === exception.id"
    @click="fixException(exception)"
>
```

- [ ] **Step 4: Run tests to verify they pass**

Run:

```bash
./scripts/dev.sh test --filter=BillingExceptionsQueryTest
```

Expected: PASS (all tests in file)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php \
  app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php \
  resources/js/pages/Finance/Operations/ExceptionsQueue.vue \
  tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php
git commit -m "fix(finance): replace fake-success billing exception fix with real handlers"
```

---

### Task 3: Due Date Bucket Parity (FIN-26)

**Files:**
- Modify: `app/Modules/Finance/Queries/Operations/GetDueItemsSummaryQuery.php`
- Modify: `app/Modules/Finance/Queries/Operations/GetDueInvoicesSummaryQuery.php`
- Test: `tests/Feature/Finance/Operations/DueItemsSummaryParityTest.php`

- [ ] **Step 1: Write failing parity test**

Create `tests/Feature/Finance/Operations/DueItemsSummaryParityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not double-count due-today items in upcoming summary bucket', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state(['status' => 'intake_course', 'intake_semester_id' => $semester->id])
        ->create();

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'TODAY-1',
        'fee_type' => 'tuition',
        'description' => 'Due today',
        'semester_id' => $semester->id,
        'due_date' => now()->startOfDay(),
        'amount' => 1000000,
        'status' => 'pushed_to_dng',
    ]);

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'TOMORROW-1',
        'fee_type' => 'tuition',
        'description' => 'Due tomorrow',
        'semester_id' => $semester->id,
        'due_date' => now()->startOfDay()->addDay(),
        'amount' => 2000000,
        'status' => 'pushed_to_dng',
    ]);

    $summary = app(GetDueItemsSummaryQuery::class)->handle($semester->id);
    $upcomingList = app(ListDueItemsQuery::class)->handle($semester->id, 'upcoming', null);
    $dueTodayList = app(ListDueItemsQuery::class)->handle($semester->id, 'due_today', null);

    expect($summary['due_today_count'])->toBe(1)
        ->and($summary['upcoming_count'])->toBe(1)
        ->and($upcomingList->total())->toBe(1)
        ->and($dueTodayList->total())->toBe(1)
        ->and($summary['upcoming_count'] + $summary['due_today_count'] + $summary['overdue_count'])
        ->toBe($upcomingList->total() + $dueTodayList->total() + app(ListDueItemsQuery::class)->handle($semester->id, 'overdue', null)->total());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="does not double-count due-today"
```

Expected: FAIL — `upcoming_count` is `2` (includes today) while list `upcoming` total is `1`.

- [ ] **Step 3: Fix summary boundaries**

In `GetDueItemsSummaryQuery.php`, change upcoming window to match list:

```php
'upcoming_count' => $dngBaseQuery()
    ->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])
    ->count(),
```

In `GetDueInvoicesSummaryQuery.php`, same change:

```php
'upcoming_count' => $baseQuery()
    ->whereNotIn('status', ['paid', 'cancelled'])
    ->whereNotNull('due_date')
    ->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])
    ->count(),
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
./scripts/dev.sh test --filter=DueItemsSummaryParityTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Operations/GetDueItemsSummaryQuery.php \
  app/Modules/Finance/Queries/Operations/GetDueInvoicesSummaryQuery.php \
  tests/Feature/Finance/Operations/DueItemsSummaryParityTest.php
git commit -m "fix(finance): align due upcoming summary window with list filters"
```

---

### Task 4: DB Pagination for Due Items (FIN-27)

**Files:**
- Modify: `app/Modules/Finance/Queries/Operations/ListDueItemsQuery.php`
- Test: `tests/Feature/Finance/Operations/ListDueItemsQueryPaginationTest.php`

- [ ] **Step 1: Write failing pagination test**

Create `tests/Feature/Finance/Operations/ListDueItemsQueryPaginationTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('paginates due items in the database instead of loading all rows into php', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state(['status' => 'intake_course'])
        ->create();

    foreach (range(1, 25) as $i) {
        DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => 'TEST',
            'student_code' => $student->student_id,
            'item_id' => "ITEM-{$i}",
            'fee_type' => 'tuition',
            'description' => "Request {$i}",
            'semester_id' => $semester->id,
            'due_date' => now()->addDays($i),
            'amount' => 100000,
            'status' => 'pushed_to_dng',
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $page1 = app(ListDueItemsQuery::class)->handle($semester->id, null, null);
    $queries = DB::getQueryLog();

    expect($page1->total())->toBe(25)
        ->and($page1->count())->toBe(20)
        ->and(count($queries))->toBeLessThan(6);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="paginates due items in the database"
```

Expected: FAIL — query count too high and/or `->get()` loads all rows before `forPage`.

- [ ] **Step 3: Rewrite ListDueItemsQuery to paginate at DB**

Replace the `$dngQuery->get()->map(...)` + manual `forPage` block with:

```php
$page = (int) request()->get('page', 1);
$perPage = 20;

$paginator = $dngQuery
    ->orderBy('due_date')
    ->paginate($perPage, ['*'], 'page', $page);

return $paginator->through(function (DngPaymentRequest $request) use ($today) {
    $dueDate = $request->due_date;
    $daysUntilDue = $today->diffInDays($dueDate, false);

    $requestStatus = 'upcoming';
    if ($daysUntilDue < 0) {
        $requestStatus = 'overdue';
    } elseif ($daysUntilDue === 0) {
        $requestStatus = 'due_today';
    }

    return [
        'id' => $request->id,
        'type' => 'dng_request',
        'invoice_number' => 'DNG-'.$request->id,
        'student_id' => $request->student_id,
        'student_code' => $request->student?->student_id,
        'student_name' => $request->student?->full_name,
        'student_email' => $request->student?->email,
        'total_amount' => (float) $request->amount,
        'paid_amount' => 0.0,
        'balance' => (float) $request->amount,
        'due_date' => $dueDate->toDateString(),
        'days_until_due' => $daysUntilDue,
        'status' => $requestStatus,
        'student_status_label' => $request->student?->status_label,
        'student_status_color' => $request->student?->status_color,
        'last_reminder_at' => $request->last_reminder_at,
    ];
});
```

Keep existing filters and `LifecycleDueItemPredicate::applyActiveCollectionScope($dngQuery)` call.

- [ ] **Step 4: Run tests to verify they pass**

Run:

```bash
./scripts/dev.sh test --filter=ListDueItemsQueryPaginationTest
./scripts/dev.sh test --filter=DueCalendarLifecycleFilterTest
```

Expected: PASS for both

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Operations/ListDueItemsQuery.php \
  tests/Feature/Finance/Operations/ListDueItemsQueryPaginationTest.php
git commit -m "perf(finance): paginate DNG due items at the database"
```

---

### Task 5: Allocation Priority Ordering (FIN-22)

**Files:**
- Modify: `app/Modules/Finance/Services/SettlementService.php`
- Test: `tests/Unit/Finance/SettlementServicePriorityOrderTest.php`

- [ ] **Step 1: Write failing priority order test**

Create `tests/Unit/Finance/SettlementServicePriorityOrderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sorts outstanding lines by configured charge-type priority before due date', function () {
    $student = Student::factory()->create();

    $tuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => 1,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $retakeCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => 1,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1000000,
        'description' => 'Retake',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $tuitionInvoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => 1,
        'invoice_number' => 'INV-TUITION',
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $retakeInvoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => 1,
        'invoice_number' => 'INV-RETAKE',
        'status' => 'pending',
        'due_date' => now()->addDay(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $tuitionInvoice->id,
        'charge_id' => $tuitionCharge->id,
        'amount_snapshot' => 5000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    InvoiceLine::create([
        'invoice_id' => $retakeInvoice->id,
        'charge_id' => $retakeCharge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Retake',
        'status' => 'active',
    ]);

    $lines = app(SettlementService::class)
        ->getOutstandingLinesForStudent($student->id, ['retake_fee', 'tuition_term']);

    expect($lines->first()?->charge?->charge_type)->toBe('retake_fee');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="sorts outstanding lines by configured charge-type priority"
```

Expected: FAIL — first line is `tuition_term` (due-date sort ignores priority).

- [ ] **Step 3: Apply priority ordering in SettlementService**

In `getOutstandingLinesForStudent`, build a rank map and sort by it first:

```php
$priorityRank = [];
foreach (array_values($priorityOrder) as $index => $chargeType) {
    $priorityRank[$chargeType] = $index;
}
$fallbackRank = count($priorityRank);

return $lines
    ->filter(fn (InvoiceLine $line) => $this->getLineOutstandingAmount($line) > 0)
    ->sortBy(function (InvoiceLine $line) use ($priorityRank, $fallbackRank) {
        $invoice = $line->invoice;
        $chargeType = $line->charge?->charge_type ?? '';
        $priority = $priorityRank[$chargeType] ?? $fallbackRank;

        return [
            $priority,
            optional($invoice?->due_date)?->getTimestamp() ?? PHP_INT_MAX,
            optional($invoice?->created_at)?->getTimestamp() ?? 0,
            $invoice?->id ?? 0,
            optional($line->created_at)?->getTimestamp() ?? 0,
            $line->id,
        ];
    })
    ->values();
```

- [ ] **Step 4: Run tests to verify they pass**

Run:

```bash
./scripts/dev.sh test --filter=SettlementServicePriorityOrderTest
./scripts/dev.sh test --filter=AutoAllocatePaymentsTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Services/SettlementService.php \
  tests/Unit/Finance/SettlementServicePriorityOrderTest.php
git commit -m "fix(finance): honor allocation priority order before due-date sorting"
```

---

### Task 6: Due Invoice Summary N+1 Fix (FIN-29 partial)

**Files:**
- Modify: `app/Modules/Finance/Queries/Operations/GetDueInvoicesSummaryQuery.php`
- Test: `tests/Feature/Finance/Operations/GetDueInvoicesSummaryQueryTest.php`

- [ ] **Step 1: Write failing eager-load test**

Create `tests/Feature/Finance/Operations/GetDueInvoicesSummaryQueryTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\GetDueInvoicesSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('computes overdue amount from settlement snapshots without per-invoice accessor n+1', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 3000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-OVERDUE',
        'status' => 'pending',
        'due_date' => now()->subDay(),
        'subtotal' => 3000000,
        'discount_total' => 0,
        'total_amount' => 3000000,
        'paid_amount' => 0,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 3000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $summary = app(GetDueInvoicesSummaryQuery::class)->handle($semester->id);
    $queryCount = count(DB::getQueryLog());

    expect($summary['overdue_count'])->toBe(1)
        ->and($summary['total_overdue_amount'])->toBe(3000000.0)
        ->and($queryCount)->toBeLessThan(12);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="computes overdue amount from settlement snapshots"
```

Expected: FAIL — query count too high from `outstanding_balance` accessor lazy loads.

- [ ] **Step 3: Eager-load and use SettlementService snapshot**

Replace overdue amount block in `GetDueInvoicesSummaryQuery`:

```php
$overdueInvoices = $baseQuery()
    ->whereNotIn('status', ['paid', 'cancelled'])
    ->whereNotNull('due_date')
    ->where('due_date', '<', $today)
    ->with([
        'invoiceLines.charge',
        'invoiceLines.paymentApplications',
        'invoiceLines.discountAllocations.invoiceDiscount',
    ])
    ->get();

$settlementService = app(SettlementService::class);

return [
    // ...counts unchanged...
    'total_overdue_amount' => (float) $overdueInvoices->sum(
        fn (StudentInvoice $invoice) => $settlementService->deriveInvoiceSnapshot($invoice)['remaining']
    ),
];
```

Add constructor injection or `app(SettlementService::class)` — match existing `ListDueInvoicesQuery` style.

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
./scripts/dev.sh test --filter=GetDueInvoicesSummaryQueryTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Operations/GetDueInvoicesSummaryQuery.php \
  tests/Feature/Finance/Operations/GetDueInvoicesSummaryQueryTest.php
git commit -m "perf(finance): eager-load overdue invoices for due summary amount"
```

---

### Task 7: Lifecycle Predicate JOIN Rewrite (DB-23)

**Files:**
- Modify: `app/Modules/Finance/Support/LifecycleDueItemPredicate.php`
- Test: `tests/Unit/Finance/Operations/LifecycleDueItemPredicateJoinTest.php`

- [ ] **Step 1: Write failing join-scope test**

Create `tests/Unit/Finance/Operations/LifecycleDueItemPredicateJoinTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies active collection scope using a join instead of nested whereHas', function () {
    $query = DngPaymentRequest::query();

    LifecycleDueItemPredicate::applyActiveCollectionScope($query);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('join')
        ->and($sql)->not->toContain('exists');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./scripts/dev.sh test --filter="applies active collection scope using a join"
```

Expected: FAIL — SQL contains `exists` from `whereHas`.

- [ ] **Step 3: Rewrite applyActiveCollectionScope**

```php
public static function applyActiveCollectionScope(Builder $query): void
{
    if (! self::hasStudentsJoin($query)) {
        $query->join('students', 'students.id', '=', 'dng_payment_requests.student_id');
    }

    $query->whereIn('students.status', Student::FINANCIAL_STATUSES)
        ->select('dng_payment_requests.*');
}

private static function hasStudentsJoin(Builder $query): bool
{
    $joins = $query->getQuery()->joins ?? [];

    foreach ($joins as $join) {
        if ($join->table === 'students') {
            return true;
        }
    }

    return false;
}
```

Run existing lifecycle tests after this change; if `ListDueItemsQuery` eager `student` breaks, remove duplicate join by calling predicate before `with()`.

- [ ] **Step 4: Run tests to verify they pass**

Run:

```bash
./scripts/dev.sh test --filter=LifecycleDueItemPredicate
./scripts/dev.sh test --filter=DueCalendarLifecycleFilterTest
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Support/LifecycleDueItemPredicate.php \
  tests/Unit/Finance/Operations/LifecycleDueItemPredicateJoinTest.php
git commit -m "perf(finance): replace lifecycle due whereHas with students join"
```

---

### Task 8: Retake Unpaid Count Regression (FIN-28)

**Files:**
- Test: `tests/Feature/Finance/Operations/RetakeUnpaidCountTest.php`

- [ ] **Step 1: Write regression test**

Create `tests/Feature/Finance/Operations/RetakeUnpaidCountTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts retake unpaid students by invoice lifecycle status not raw paid cache', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state(['intake_semester_id' => $semester->id, 'status' => 'intake_course'])
        ->create();

    $offering = CourseOffering::factory()->forSemester($semester)->create();

    CourseRegistration::factory()
        ->forStudent($student)
        ->forCourseOffering($offering)
        ->state(['is_retake' => true, 'registration_status' => 'registered'])
        ->create();

    StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-PAID-CACHE-LIE',
        'status' => 'paid',
        'due_date' => now()->addDays(7),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    $stats = app(GetBillingDashboardStatsQuery::class)->handle($semester->id);

    expect($stats['retake_unpaid_count'])->toBe(0);
});
```

- [ ] **Step 2: Run test to verify current behavior**

Run:

```bash
./scripts/dev.sh test --filter=RetakeUnpaidCountTest
```

Expected: PASS already (S-002 fix landed). If FAIL, adjust `GetBillingDashboardStatsQuery` retake filter to `whereNotIn('status', ['paid', 'cancelled'])` only.

- [ ] **Step 3: Commit test-only if green**

```bash
git add tests/Feature/Finance/Operations/RetakeUnpaidCountTest.php
git commit -m "test(finance): lock retake unpaid dashboard count to invoice status"
```

---

### Task 9: Due Calendar IA Rename (UI-IA-1)

**Files:**
- Modify: `resources/js/pages/Finance/Operations/DueCalendar.vue`
- Modify: `resources/js/pages/Finance/Operations/LifecycleExceptions.vue`
- Modify: `resources/js/constants/menu-sidebar.ts`

- [ ] **Step 1: Update labels (route slug unchanged)**

In `DueCalendar.vue`:

```vue
<Head title="DNG Due Reminders" />
<!-- header -->
<h1 class="text-3xl font-bold tracking-tight">DNG Due Reminders</h1>
<p class="text-muted-foreground mt-1">Hàng đợi nhắc nợ DNG — không phải lịch tháng</p>
```

In `LifecycleExceptions.vue`, change button text `Due Calendar` → `DNG Due Reminders`.

In `menu-sidebar.ts`, change both `title: 'Due Calendar'` entries to `title: 'DNG Due Reminders'`.

Keep permission key `view_finance_operations_due_calendar` and route name `finance.operations.due-calendar` unchanged.

- [ ] **Step 2: Run frontend checks**

Run:

```bash
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run type-check
```

Expected: PASS for touched files (repo-wide `vue-tsc` baseline drift may still exist outside Finance Operations — record in validation evidence if so).

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Finance/Operations/DueCalendar.vue \
  resources/js/pages/Finance/Operations/LifecycleExceptions.vue \
  resources/js/constants/menu-sidebar.ts
git commit -m "ui(finance): rename due calendar surface to DNG due reminders"
```

---

### Task 10: Story Validation and Harness Trace

**Files:**
- Modify: `docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/validation.md`

- [ ] **Step 1: Run full validation bundle**

Run:

```bash
./scripts/dev.sh test --filter=BillingExceptions
./scripts/dev.sh test --filter=DueItems
./scripts/dev.sh test --filter=DueCalendar
./scripts/dev.sh test --filter=Dashboard
./scripts/dev.sh test --filter=AutoAllocatePaymentsTest
./scripts/dev.sh test --filter=LifecycleDueItemPredicate
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run type-check
git diff --check
```

Expected: targeted Finance Operations tests PASS; record exact counts in validation.md. Note any pre-existing failures separately — do not claim green for unrelated suites.

- [ ] **Step 2: Update validation evidence**

Append to `validation.md`:

```markdown
### Implementation evidence (YYYY-MM-DD)

- BillingExceptionsQueryTest: N tests / M assertions — list + fix honesty
- DueItemsSummaryParityTest: ...
- ListDueItemsQueryPaginationTest: query count < 6 for 25 rows
- SettlementServicePriorityOrderTest: retake_fee sorts before tuition_term
- GetDueInvoicesSummaryQueryTest: overdue amount without accessor N+1
- RetakeUnpaidCountTest: paid invoice with stale cache not counted unpaid
- finance:audit-invariants --sample: [paste output summary]
- Pint / lint / type-check: [pass or documented baseline drift]
```

- [ ] **Step 3: Record harness trace and close story**

Run:

```bash
./scripts/harness trace \
  --summary "S-006 operations stubs removed, due parity fixed, query perf improved" \
  --story FIN-REV-006-operations-stubs-and-performance \
  --actions "billing exception list+fix, due summary parity, DB pagination, priority order, due invoice eager load, lifecycle join, UI rename" \
  --changed "app/Modules/Finance/**, resources/js/pages/Finance/Operations/**, tests/Feature/Finance/Operations/**" \
  --outcome completed \
  --friction "defer_no_case auto-fix deferred; mismatch detection still 0"

./scripts/harness story update --id FIN-REV-006-operations-stubs-and-performance --status implemented
```

- [ ] **Step 4: Final commit for docs**

```bash
git add docs/stories/E-finance-module-review-2026-06/S-006-operations-stubs-and-performance/validation.md
git commit -m "docs(finance): record S-006 validation evidence"
```

---

## Self-Review

### 1. Spec coverage

| Requirement | Task |
| --- | --- |
| FIN-20 fake-success fix | Task 2 |
| FIN-21 empty list stub | Task 1 |
| FIN-22 priorityOrder dead param | Task 5 |
| FIN-26 upcoming double-count | Task 3 |
| FIN-27 PHP pagination | Task 4 |
| FIN-28 retake_unpaid_count | Task 8 |
| FIN-29 N+1 overdue amount | Task 6 |
| DB-23 lifecycle whereHas | Task 7 |
| UI-IA-1 Due Calendar naming | Task 9 |
| Validation commands | Task 10 |
| Portal impact none | N/A (no portal files) |
| defer_no_case policy gate | Task 2 stop condition |
| mismatch type | Task 1 (`fixable: false`, count 0) |

**Gap:** Full `GetBillingDashboardStatsQuery` SQL aggregate rewrite (DB-22 dashboard row) is intentionally deferred — not required by story validation filter set unless expanded.

### 2. Placeholder scan

No `TBD`, `TODO`, or "implement later" steps. Each task includes concrete code, commands, and expected outcomes.

### 3. Type consistency

- `BillingExceptionIdentifier::encode/decode` used consistently in list, fix action, and tests.
- `fixable` boolean flows from collector → list → Vue guard.
- Due bucket boundaries: list uses `[today+1, today+7]`; summary updated to match in Task 3.
- `priorityOrder` charge type strings match `FinanceCharge::TYPE_*` constants used in UI (`tuition_term`, `retake_fee`, etc.).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-15-finance-operations-stubs-and-performance.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration. REQUIRED SUB-SKILL: `superpowers:subagent-driven-development`.

2. **Inline Execution** — execute tasks in this session using `superpowers:executing-plans`, batch execution with checkpoints.

**Which approach?**