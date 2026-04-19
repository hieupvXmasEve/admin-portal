---
phase: 5
title: EGC Dashboard riêng
status: pending
---

# Phase 5: EGC Dashboard

## Overview

Tạo dashboard riêng cho EGC team với KPIs và danh sách sinh viên chỉ thuộc `intake_pre_uni_gc`.
Clone + filter từ `GetBillingDashboardStatsQuery` và `GetBillingDashboardStudentsQuery` hiện tại.

**Mục tiêu:** EGC team không thấy số liệu Major, không lẫn lộn, không sai số.

---

## 5.1 GetEgcDashboardStatsQuery

**File:** `app/Modules/Finance/Queries/Operations/GetEgcDashboardStatsQuery.php`

Clone từ `GetBillingDashboardStatsQuery` với filter cứng `students.status = 'intake_pre_uni_gc'`.
Thêm EGC-specific KPIs:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\EgcBlockRetake;
use App\Models\Student;
use App\Modules\Finance\Support\BillingScopeHelper;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard stats cho EGC students (intake_pre_uni_gc) only.
 * Tách riêng khỏi GetBillingDashboardStatsQuery để tránh lẫn Major numbers.
 */
class GetEgcDashboardStatsQuery
{
    public function handle(?int $semesterId): array
    {
        // Base query: EGC students only
        $baseQuery = Student::query()
            ->where('status', 'intake_pre_uni_gc');

        // Campus scope
        if (app()->bound('campus') && ($campusId = app('campus')->id ?? null)) {
            $baseQuery->where('campus_id', $campusId);
        }

        $totalEgcStudents = (clone $baseQuery)->count();

        // Students with active invoice this semester
        $studentsWithInvoice = $semesterId
            ? (clone $baseQuery)->whereHas('invoices', fn ($q) => $q->where('semester_id', $semesterId)->whereNotIn('status', ['cancelled']))->count()
            : 0;

        // Outstanding balance (EGC students only)
        $totalOutstanding = $semesterId
            ? (clone $baseQuery)->join('student_invoices', 'students.id', '=', 'student_invoices.student_id')
                ->where('student_invoices.semester_id', $semesterId)
                ->whereIn('student_invoices.status', ['pending', 'overdue'])
                ->sum(DB::raw('student_invoices.total_amount - student_invoices.paid_amount'))
            : 0;

        // EGC-specific: Retake stats this semester
        $retakeStats = $semesterId ? [
            'total_retakes' => EgcBlockRetake::where('semester_id', $semesterId)->count(),
            'total_credit_carry_forward' => EgcBlockRetake::where('semester_id', $semesterId)->sum('credit_amount'),
        ] : ['total_retakes' => 0, 'total_credit_carry_forward' => 0];

        // Level distribution
        $levelDistribution = (clone $baseQuery)
            ->select('gc_current_level', DB::raw('count(*) as count'))
            ->whereNotNull('gc_current_level')
            ->groupBy('gc_current_level')
            ->orderBy('gc_current_level')
            ->pluck('count', 'gc_current_level')
            ->toArray();

        return [
            'total_egc_students' => $totalEgcStudents,
            'students_with_invoice' => $studentsWithInvoice,
            'total_outstanding' => (float) $totalOutstanding,
            'retake_stats' => $retakeStats,
            'level_distribution' => $levelDistribution,
        ];
    }
}
```

---

## 5.2 GetEgcDashboardStudentsQuery

**File:** `app/Modules/Finance/Queries/Operations/GetEgcDashboardStudentsQuery.php`

Clone từ `GetBillingDashboardStudentsQuery` với:
- Filter cứng `status = 'intake_pre_uni_gc'`
- Thêm columns: `gc_current_level`, `retake_count` (từ `egc_block_retakes`)
- Bỏ Major-specific columns (tuition plan, installment)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\Student;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetEgcDashboardStudentsQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    public function handle(?int $semesterId, array $filters = []): LengthAwarePaginator
    {
        $query = Student::query()
            ->where('status', 'intake_pre_uni_gc')
            ->with([
                'invoices' => fn ($q) => $semesterId ? $q->where('semester_id', $semesterId) : $q,
                'egcBlockRetakes' => fn ($q) => $semesterId ? $q->where('semester_id', $semesterId) : $q,
            ])
            ->select([
                'students.id', 'students.student_id', 'students.first_name', 'students.last_name',
                'students.email', 'students.status', 'students.gc_current_level', 'students.gc_total_levels',
                'students.campus_id',
            ]);

        // Campus scope
        if (app()->bound('campus') && ($campusId = app('campus')->id ?? null)) {
            $query->where('students.campus_id', $campusId);
        }

        // Filters
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q
                ->where('students.student_id', 'like', "%{$filters['search']}%")
                ->orWhere('students.first_name', 'like', "%{$filters['search']}%")
                ->orWhere('students.last_name', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['level'])) {
            $query->where('students.gc_current_level', (int) $filters['level']);
        }

        if (!empty($filters['has_retake'])) {
            $query->whereHas('egcBlockRetakes', fn ($q) => $semesterId ? $q->where('semester_id', $semesterId) : $q);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        return $query->paginate($perPage);
    }
}
```

> **Lưu ý:** Cần thêm relationship `egcBlockRetakes` vào `Student` model.

---

## 5.3 Student Model: Thêm relationship

**File:** `app/Models/Student.php` — thêm:

```php
public function egcBlockRetakes(): HasMany
{
    return $this->hasMany(EgcBlockRetake::class);
}
```

---

## 5.4 Controller: Thêm egcDashboard method

**File:** `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php`

```php
/**
 * EGC-only billing dashboard — intake_pre_uni_gc students.
 */
public function egcDashboard(
    Request $request,
    GetEgcDashboardStatsQuery $statsQuery,
    GetEgcDashboardStudentsQuery $studentsQuery
): Response {
    $validated = $request->validate([
        'semester_id' => 'nullable|integer|exists:semesters,id',
        'search' => 'nullable|string',
        'level' => 'nullable|integer',
        'has_retake' => 'nullable|string',
        'per_page' => 'nullable|integer',
    ]);

    $currentSemester = Semester::where('is_active', true)->first();
    $semesterId = isset($validated['semester_id'])
        ? (int) $validated['semester_id']
        : $currentSemester?->id;

    return Inertia::render('Finance/Operations/EgcDashboard', [
        'kpiStats' => $statsQuery->handle($semesterId),
        'students' => $studentsQuery->handle($semesterId, $validated),
        'semesters' => Semester::orderBy('start_date', 'desc')->get(),
        'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
        'filters' => [
            'semester_id' => $semesterId ? (string) $semesterId : null,
            'search' => $validated['search'] ?? '',
            'level' => $validated['level'] ?? null,
            'has_retake' => $validated['has_retake'] ?? 'all',
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ],
    ]);
}
```

---

## 5.5 Route

```php
Route::get('/egc-dashboard', [BillingOperationsController::class, 'egcDashboard'])
    ->middleware('can:view_finance_operations_dashboard')
    ->name('egc-dashboard');
```

---

## 5.6 EgcDashboard.vue

**File:** `resources/js/Pages/Finance/Operations/EgcDashboard.vue`

Clone từ `Dashboard.vue` với:
- KPI cards: Total EGC Students, Students with Invoice, Outstanding Balance
- EGC-specific cards: Total Retakes, Total Credit Carry-forward
- Level distribution: progress bars hoặc simple bar chart (Level 1→6)
- Student table: thêm cột "Level", "Retake", bỏ cột Major-specific
- Filter thêm: "Level" dropdown, "Has Retake" checkbox

**KPI layout:**
```
┌─────────────────┬─────────────────┬─────────────────┐
│  EGC Students   │  Has Invoice    │  Outstanding    │
│      120        │      98         │   45,000,000    │
└─────────────────┴─────────────────┴─────────────────┘
┌─────────────────┬─────────────────┐
│  Retakes (kỳ)  │  Credit Carry   │
│       15        │   112,500,000   │
└─────────────────┴─────────────────┘
```

---

## Todo

- [ ] Tạo `GetEgcDashboardStatsQuery`
- [ ] Tạo `GetEgcDashboardStudentsQuery`
- [ ] Thêm `egcBlockRetakes` relationship vào `Student` model
- [ ] Thêm `egcDashboard` method vào `BillingOperationsController`
- [ ] Thêm route `egc-dashboard`
- [ ] Tạo `EgcDashboard.vue`
- [ ] Chạy `./scripts/dev.sh artisan pint`
- [ ] Chạy `./scripts/dev.sh npm run type-check`

## Success Criteria

- EGC Dashboard chỉ hiện sinh viên `intake_pre_uni_gc`
- KPI stats không lẫn Major students
- Retake stats hiển thị đúng từ `egc_block_retakes`
- Level filter hoạt động
- Trang Major Dashboard (existing) không bị thay đổi
