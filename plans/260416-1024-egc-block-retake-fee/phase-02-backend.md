---
phase: 2
title: Backend — Query + Action + Controller + Route
status: pending
---

# Phase 2: Backend

## Overview

4 thành phần theo thứ tự dependency:
1. `PreviewEgcRetakeChargesQuery` — detect sinh viên eligible
2. `ProcessEgcBlockRetakeAction` — batch void + create + record
3. `BillingOperationsController` — 2 method mới (preview + process)
4. `routes/web.php` — 2 route mới

---

## 2.1 PreviewEgcRetakeChargesQuery

**File:** `app/Modules/Finance/Queries/Operations/PreviewEgcRetakeChargesQuery.php`

**Logic:** Join academic_records → course_offerings → curriculum_units → units để tìm EGC students trượt Block 1 đủ điều kiện chuyên cần, chưa được xử lý retake.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\AcademicRecord;
use App\Models\EgcBlockRetake;
use App\Models\FinanceCharge;
use Illuminate\Support\Collection;

class PreviewEgcRetakeChargesQuery
{
    /**
     * Tìm EGC students đủ điều kiện nhận giảm học phí retake Block 1.
     *
     * Điều kiện:
     *   - EGC student (status = intake_pre_uni_gc)
     *   - completion_status = 'failed'
     *   - meets_attendance_requirement = true
     *   - attempt_number = 1, is_repeat_course = false
     *   - Chưa có egc_block_retakes record cho semester + block + level này
     *
     * @return Collection<int, array{
     *   student_id: int,
     *   student_code: string,
     *   student_name: string,
     *   level: int,
     *   attendance_percentage: float,
     *   level_fee: float,
     *   retake_amount: float,
     *   credit_amount: float,
     *   voided_charge_id: int,
     * }>
     */
    public function handle(int $semesterId): Collection
    {
        // Tìm processed student IDs để exclude (idempotency)
        $processedStudentIds = EgcBlockRetake::where('semester_id', $semesterId)
            ->where('block_number', 1)
            ->pluck('student_id')
            ->toArray();

        // Query academic_records của EGC students trượt Block 1
        $records = AcademicRecord::query()
            ->with([
                'student:id,student_id,first_name,last_name,status',
                'courseOffering.curriculumUnit.unit:id,unit_type,level,base_fee',
            ])
            ->whereHas('student', function ($q) {
                $q->where('status', 'intake_pre_uni_gc');
            })
            ->whereHas('courseOffering.curriculumUnit.unit', function ($q) {
                $q->where('unit_type', 'egc');
            })
            ->where('semester_id', $semesterId)
            ->where('completion_status', 'failed')
            ->where('meets_attendance_requirement', true)
            ->where('is_repeat_course', false)
            ->where('attempt_number', 1)
            ->when(count($processedStudentIds) > 0, function ($q) use ($processedStudentIds) {
                $q->whereNotIn('student_id', $processedStudentIds);
            })
            ->get();

        return $records->map(function (AcademicRecord $record) use ($semesterId) {
            $student = $record->student;
            $unit = $record->courseOffering?->curriculumUnit?->unit;
            $level = $unit?->level ?? 0;
            $levelFee = (float) ($unit?->base_fee ?? 0);
            $retakeAmount = round($levelFee * 0.5); // 50% discount
            $creditAmount = $levelFee - $retakeAmount;

            // Tìm Level X+1 charge sẽ bị void
            $nextLevelCharge = FinanceCharge::where('student_id', $student->id)
                ->where('semester_id', $semesterId)
                ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->whereHas('source', function ($q) use ($level) {
                    // Unit level = X+1
                    $q->where('unit_type', 'egc')->where('level', $level + 1);
                }, '>=', 0) // fallback: lấy charge có amount cao nhất nếu không trace được source
                ->orderByDesc('amount')
                ->first();

            // Nếu không trace được qua source, tìm egc_level_fee charge thứ 2 (amount lớn hơn = Level X+1)
            if (! $nextLevelCharge) {
                $egcCharges = FinanceCharge::where('student_id', $student->id)
                    ->where('semester_id', $semesterId)
                    ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                    ->active()
                    ->orderByDesc('amount')
                    ->get();

                // Level X+1 thường có amount >= Level X (hoặc lấy charge cuối nếu 2 level cùng giá)
                $nextLevelCharge = $egcCharges->count() > 1 ? $egcCharges->first() : null;
            }

            return [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'student_name' => trim("{$student->first_name} {$student->last_name}"),
                'level' => $level,
                'attendance_percentage' => (float) $record->attendance_percentage,
                'level_fee' => $levelFee,
                'retake_amount' => $retakeAmount,
                'credit_amount' => $creditAmount,
                'voided_charge_id' => $nextLevelCharge?->id,
                'can_process' => $nextLevelCharge !== null,
            ];
        })->filter(fn ($item) => $item['can_process'])->values();
    }
}
```

> **Lưu ý về tìm Level X+1 charge:** Cần kiểm tra `source_type/source_id` trong `finance_charges` khi generate EGC charges. Nếu charges được tạo với `source_type = App\Models\Unit` và `source_id = unit_id`, thì có thể join chính xác. Xem `GenerateBatchChargesAction::createChargeIfNotExists()`.

---

## 2.2 ProcessEgcBlockRetakeAction

**File:** `app/Modules/Finance/Actions/Operations/ProcessEgcBlockRetakeAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\EgcBlockRetake;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessEgcBlockRetakeAction
{
    public function __construct(
        private readonly VoidFinanceChargeAction $voidChargeAction,
    ) {}

    /**
     * Xử lý batch EGC Block 1 retake fee cho danh sách sinh viên eligible.
     *
     * Với mỗi student:
     *   1. Void Level X+1 charge (egc_level_fee)
     *   2. Tạo Level X retake charge (egc_retake_fee, 50%)
     *   3. Link retake charge vào invoice hiện tại
     *   4. Ghi EgcBlockRetake record (idempotency guard)
     *
     * Settlement tự recalculate → 7.5M thành unapplied credit → carry-forward tự động.
     *
     * @param array $items Từ PreviewEgcRetakeChargesQuery output
     */
    public function run(int $semesterId, array $items, int $userId): array
    {
        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_credit' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                try {
                    $this->processOne($semesterId, $item, $userId, $stats);
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "Student {$item['student_code']}: " . $e->getMessage();
                    Log::error('EGC retake processing failed', [
                        'student_id' => $item['student_id'],
                        'semester_id' => $semesterId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    private function processOne(int $semesterId, array $item, int $userId, array &$stats): void
    {
        $studentId = (int) $item['student_id'];
        $level = (int) $item['level'];
        $voidedChargeId = (int) $item['voided_charge_id'];

        // Idempotency check
        $alreadyProcessed = EgcBlockRetake::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('block_number', 1)
            ->where('level', $level)
            ->exists();

        if ($alreadyProcessed) {
            $stats['skipped']++;
            return;
        }

        // 1. Load charge to void
        $chargeToVoid = FinanceCharge::find($voidedChargeId);
        if (! $chargeToVoid || $chargeToVoid->status !== FinanceCharge::STATUS_ACTIVE) {
            throw new \RuntimeException("Charge {$voidedChargeId} not found or already voided");
        }

        $originalAmount = (float) $chargeToVoid->amount;

        // 2. Void Level X+1 charge
        $this->voidChargeAction->handle(
            $voidedChargeId,
            "EGC Block 1 fail — học lại Level {$level} tại Block 2 (giảm 50%)",
            $userId
        );

        // 3. Tạo Level X retake charge (50%)
        $retakeAmount = (float) $item['retake_amount'];
        $retakeCharge = FinanceCharge::create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_EGC_RETAKE_FEE,
            'amount' => $retakeAmount,
            'description' => "EGC Level {$level} Retake Fee (50%) — Block 2",
            'source_type' => EgcBlockRetake::class, // sẽ update sau khi record tạo xong
            'source_id' => 0,                        // placeholder
            'status' => FinanceCharge::STATUS_ACTIVE,
            'effective_at' => now(),
            'created_by_user_id' => $userId,
        ]);

        // 4. Link retake charge vào invoice hiện tại của semester
        $invoice = StudentInvoice::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->reusableForChargeGeneration()
            ->latest('id')
            ->first();

        if ($invoice) {
            InvoiceLine::firstOrCreate(
                ['invoice_id' => $invoice->id, 'charge_id' => $retakeCharge->id],
                [
                    'amount_snapshot' => $retakeCharge->amount,
                    'description_snapshot' => $retakeCharge->description,
                ]
            );
        }

        // 5. Ghi EgcBlockRetake record
        $retakeRecord = EgcBlockRetake::create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'block_number' => 1,
            'level' => $level,
            'voided_charge_id' => $voidedChargeId,
            'retake_charge_id' => $retakeCharge->id,
            'original_amount' => $originalAmount,
            'retake_amount' => $retakeAmount,
            'credit_amount' => $originalAmount - $retakeAmount,
            'meets_attendance_requirement' => true,
            'attendance_percentage' => $item['attendance_percentage'] ?? null,
            'recorded_by_user_id' => $userId,
        ]);

        // 6. Update retake charge source_id → trỏ về EgcBlockRetake record
        $retakeCharge->update(['source_id' => $retakeRecord->id]);

        $stats['processed']++;
        $stats['total_credit'] += $originalAmount - $retakeAmount;
    }
}
```

---

## 2.3 BillingOperationsController — thêm 2 methods

**File:** `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php`

Thêm vào cuối class (sau method hiện tại cuối cùng):

```php
/**
 * Preview danh sách EGC students đủ điều kiện nhận giảm học phí retake.
 */
public function showEgcRetake(
    Request $request,
    PreviewEgcRetakeChargesQuery $previewQuery
): Response {
    $semesters = Semester::orderBy('start_date', 'desc')->get();
    $currentSemester = Semester::where('is_active', true)->first();

    $semesterId = $request->integer('semester_id') ?: $currentSemester?->id;
    $preview = $semesterId ? $previewQuery->handle($semesterId) : collect();

    return Inertia::render('Finance/Operations/EgcRetake', [
        'semesters' => $semesters,
        'currentSemester' => $currentSemester,
        'preview' => $preview,
        'filters' => ['semester_id' => $semesterId ? (string) $semesterId : null],
    ]);
}

/**
 * Xử lý batch EGC retake fee cho semester đã chọn.
 */
public function processEgcRetake(
    Request $request,
    PreviewEgcRetakeChargesQuery $previewQuery,
    ProcessEgcBlockRetakeAction $action
): \Illuminate\Http\RedirectResponse {
    $validated = $request->validate([
        'semester_id' => 'required|integer|exists:semesters,id',
    ]);

    $semesterId = (int) $validated['semester_id'];
    $items = $previewQuery->handle($semesterId)->toArray();

    if (empty($items)) {
        return back()->with('warning', 'Không có sinh viên nào đủ điều kiện để xử lý.');
    }

    $stats = $action->run($semesterId, $items, auth()->id());

    $message = "Đã xử lý {$stats['processed']} sinh viên. "
        . "Tổng credit carry-forward: " . number_format($stats['total_credit']) . " VNĐ.";

    if ($stats['failed'] > 0) {
        $message .= " Lỗi: {$stats['failed']} sinh viên.";
    }

    return back()->with('success', $message);
}
```

**Import cần thêm vào đầu file:**
```php
use App\Modules\Finance\Actions\Operations\ProcessEgcBlockRetakeAction;
use App\Modules\Finance\Queries\Operations\PreviewEgcRetakeChargesQuery;
```

---

## 2.4 Routes

**File:** `app/Modules/Finance/routes/web.php`

Thêm vào trong `prefix('operations')` group:

```php
Route::get('/egc-retake', [BillingOperationsController::class, 'showEgcRetake'])
    ->middleware('can:view_finance_operations_generate_charges')
    ->name('egc-retake');

Route::post('/egc-retake/process', [BillingOperationsController::class, 'processEgcRetake'])
    ->middleware('can:create_finance_charges')
    ->name('egc-retake.process');
```

> **Permission reuse:** Dùng `view_finance_operations_generate_charges` và `create_finance_charges` đã có sẵn, phù hợp với nghiệp vụ này.

---

## Todo

- [ ] Tạo `PreviewEgcRetakeChargesQuery` — verify source_type/source_id của egc_level_fee charges
- [ ] Tạo `ProcessEgcBlockRetakeAction`
- [ ] Verify `VoidFinanceChargeAction::handle()` signature (chargeId, reason, userId)
- [ ] Verify `StudentInvoice::reusableForChargeGeneration()` scope tồn tại
- [ ] Thêm 2 methods vào `BillingOperationsController`
- [ ] Thêm 2 routes vào `routes/web.php`
- [ ] Chạy `php artisan route:list | grep egc-retake` để verify
- [ ] Chạy `./vendor/bin/pint --dirty`

## Success Criteria

- `GET /finance/operations/egc-retake?semester_id=X` trả về data preview
- `POST /finance/operations/egc-retake/process` xử lý batch thành công
- Sau xử lý: `finance_charges` có void + retake records, `egc_block_retakes` có record
- Chạy lại POST lần 2: skip tất cả (idempotency)
- Settlement invoice giảm 7.5M, payment unapplied tăng 7.5M

## Risk

- **Source_type của egc_level_fee charges:** `GenerateBatchChargesAction` tạo charge với `source_type = App\Models\Unit`, `source_id = unit_id`. Cần verify để tìm đúng Level X+1 charge. Nếu không có source → fallback dựa trên description string "EGC Level {X+1} Fee".
