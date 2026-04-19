---
phase: 4
title: Tách Generate Charges — EGC vs Major
status: pending
---

# Phase 4: Tách Generate Charges

## Overview

Tách `GenerateBatchChargesAction` thành 2 action độc lập:
- `GenerateEgcChargesAction` — chỉ xử lý EGC students (`intake_pre_uni_gc`)
- `GenerateBatchChargesAction` giữ lại — chỉ xử lý Major/tuition (`intake_course`)

**Mục tiêu:** EGC rule thay đổi → chỉ sửa `GenerateEgcChargesAction`, không đụng đến Major code.

---

## 4.1 Tạo GenerateEgcChargesAction

**File:** `app/Modules/Finance/Actions/Operations/GenerateEgcChargesAction.php`

Extract EGC logic từ `GenerateBatchChargesAction` (Case A: Intake Pre-Uni GC → GC Fee, lines 182–229):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\BillingScopeHelper;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generate EGC level fee charges cho intake_pre_uni_gc students.
 * Tách riêng khỏi GenerateBatchChargesAction để EGC rule changes
 * không ảnh hưởng đến Major tuition logic.
 */
class GenerateEgcChargesAction
{
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $createdByUserId = auth()->id();

        // EGC-only scope
        $query = BillingScopeHelper::getEligibleStudentsQuery(
            $semesterId,
            $data['scope_type'],
            [
                'program_id' => $data['filter_program_id'] ?? null,
                'enrollment_status' => $data['filter_enrollment_status'] ?? 'all',
                'uploaded_student_ids' => $data['uploaded_student_ids'] ?? [],
            ]
        )->where('students.status', 'intake_pre_uni_gc'); // Hard filter: EGC only

        // Campus scope
        if (app()->bound('campus') && ($campusId = app('campus')->id ?? null)) {
            $query->where('students.campus_id', $campusId);
        }

        $query->with(['scholarshipAward.scholarshipDefinition']);
        $students = $query->get();

        $deferChargeResolver = app(DeferChargeResolver::class);
        $studentChargeTimingResolver = app(StudentChargeTimingResolver::class);

        $students = $students
            ->filter(fn (Student $student) => $studentChargeTimingResolver->shouldIncludeStudentForChargeGeneration(
                $student, $semesterId, [FinanceCharge::TYPE_EGC_LEVEL_FEE]
            ))
            ->values();

        $stats = [
            'total_students' => $students->count(),
            'created_count' => 0,
            'created_invoices' => 0,
            'updated_invoices' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                try {
                    $deferCase = $deferChargeResolver->findApplicableFullCase($student, $semesterId);

                    if ($deferCase) {
                        $deferChargeResolver->markFullCaseApplied($deferCase, $semesterId);
                        $stats['skipped_count']++;
                        continue;
                    }

                    // Check: already issued for this semester?
                    $alreadyIssued = StudentInvoice::query()
                        ->where('student_id', $student->id)
                        ->where('semester_id', $semesterId)
                        ->whereHas('invoiceLines.charge', function ($q) {
                            $q->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                              ->where('status', FinanceCharge::STATUS_ACTIVE);
                        })
                        ->exists();

                    if ($alreadyIssued) {
                        $stats['skipped_count']++;
                        continue;
                    }

                    // Determine levels to charge
                    $startLevel = $student->gc_current_level ?? 1;
                    $levelsToCharge = $startLevel <= 5 ? [$startLevel] : [];
                    if ($startLevel < 5) {
                        $levelsToCharge[] = $startLevel + 1;
                    }

                    $chargesToLink = [];
                    foreach ($levelsToCharge as $level) {
                        $unit = \App\Models\Unit::where('unit_type', 'egc')->where('level', $level)->first();
                        $fee = $unit ? (float) $unit->base_fee : 0;

                        if ($fee > 0) {
                            $charge = self::createChargeIfNotExists(
                                $student, $semesterId,
                                FinanceCharge::TYPE_EGC_LEVEL_FEE,
                                $fee, 'App\Models\Unit', $unit?->id, $createdByUserId
                            );
                            if ($charge) {
                                $charge->update(['description' => "EGC Level {$level} Fee"]);
                                $charge->refresh();
                                $chargesToLink[] = $charge;
                            }
                        }
                    }

                    if (empty($chargesToLink)) {
                        $stats['skipped_count']++;
                        continue;
                    }

                    $invoice = self::findReusableInvoice($student->id, $semesterId)
                        ?? self::createDraftInvoice($student, $semesterId);

                    foreach ($chargesToLink as $chargeItem) {
                        InvoiceLine::firstOrCreate(
                            ['invoice_id' => $invoice->id, 'charge_id' => $chargeItem->id],
                            ['amount_snapshot' => $chargeItem->amount, 'description_snapshot' => $chargeItem->description]
                        );
                        $stats['created_count']++;
                    }

                    $stats['created_invoices']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "Student {$student->student_id}: " . $e->getMessage();
                    $stats['failed_count']++;
                    Log::error('EGC charge generation failed', ['student' => $student->id, 'error' => $e->getMessage()]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    private static function findReusableInvoice(int $studentId, int $semesterId): ?StudentInvoice
    {
        return StudentInvoice::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->reusableForChargeGeneration()
            ->latest('id')
            ->first();
    }

    private static function createDraftInvoice(Student $student, int $semesterId): StudentInvoice
    {
        return StudentInvoice::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'invoice_number' => 'INV-' . time() . '-' . $student->student_id . '-' . $semesterId,
            'due_date' => now()->addDays(30),
            'opened_at' => now(),
            'status' => 'draft',
        ]);
    }

    private static function createChargeIfNotExists(Student $student, int $semesterId, string $type, float $amount, ?string $sourceType, $sourceId, ?int $userId): ?FinanceCharge
    {
        $query = FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', $type)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId);

        if ($query->exists()) {
            return null; // Already exists, skip
        }

        return FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'charge_type' => $type,
            'description' => 'EGC Level Fee',
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'active',
            'effective_at' => now(),
            'created_by_user_id' => $userId,
        ]);
    }
}
```

---

## 4.2 Strip EGC khỏi GenerateBatchChargesAction

**File:** `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php`

Xóa hoàn toàn "Case A: Intake Pre-Uni GC → GC Fee" block (lines 88–229 approx):
- Xóa `$hasEgc` logic
- Xóa `$canGenerateEgc` checks
- Xóa Case A block
- Giữ lại: Case B (Tuition), Case C (Voucher), Case D (Manual Adjustment)
- Đổi filter scope: chỉ `intake_course`

> **Lưu ý:** `GenerateBatchChargesAction` vẫn có `$chargeTypes` param. Sau khi strip EGC, nếu `TYPE_EGC_LEVEL_FEE` được truyền vào → ignore hoặc return warning.

---

## 4.3 Controller: Thêm showGenerateEgcCharges

**File:** `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php`

```php
/**
 * Page generate EGC charges — EGC students only.
 */
public function showGenerateEgcCharges(Request $request): Response
{
    $semesters = Semester::orderBy('start_date', 'desc')->get();
    $currentSemester = Semester::where('is_active', true)->first();

    return Inertia::render('Finance/Operations/GenerateEgcCharges', [
        'semesters' => $semesters,
        'currentSemester' => $currentSemester,
    ]);
}

/**
 * Execute EGC charge generation batch.
 */
public function generateEgcCharges(Request $request): \Illuminate\Http\RedirectResponse
{
    $validated = $request->validate([
        'semester_id' => 'required|integer|exists:semesters,id',
        'scope_type' => 'required|string|in:all,upload',
        'uploaded_student_ids' => 'nullable|array',
    ]);

    $stats = GenerateEgcChargesAction::run([
        'semester_id' => $validated['semester_id'],
        'scope_type' => $validated['scope_type'],
        'uploaded_student_ids' => $validated['uploaded_student_ids'] ?? [],
    ]);

    return back()->with('success', "Đã tạo {$stats['created_count']} charges cho {$stats['created_invoices']} sinh viên EGC.");
}
```

---

## 4.4 Routes

**File:** `app/Modules/Finance/routes/web.php` — trong `prefix('operations')`:

```php
Route::get('/generate-egc-charges', [BillingOperationsController::class, 'showGenerateEgcCharges'])
    ->middleware('can:view_finance_operations_generate_charges')
    ->name('generate-egc-charges');

Route::post('/generate-egc-charges', [BillingOperationsController::class, 'generateEgcCharges'])
    ->middleware('can:create_finance_charges')
    ->name('generate-egc-charges.process');
```

---

## 4.5 Frontend: GenerateEgcCharges.vue

**File:** `resources/js/Pages/Finance/Operations/GenerateEgcCharges.vue`

Clone từ `GenerateCharges.vue` nhưng:
- Bỏ charge type selector (hardcode EGC only)
- Bỏ Major-specific fields
- Label rõ ràng: "Generate EGC Level Charges"
- POST đến `finance.operations.generate-egc-charges.process`

---

## Todo

- [ ] Tạo `GenerateEgcChargesAction` (extract từ GenerateBatchChargesAction)
- [ ] Strip EGC logic khỏi `GenerateBatchChargesAction`
- [ ] Verify `GenerateBatchChargesAction` vẫn hoạt động đúng cho Major sau khi strip
- [ ] Thêm 2 methods vào `BillingOperationsController`
- [ ] Thêm 2 routes
- [ ] Tạo `GenerateEgcCharges.vue`
- [ ] Chạy `./scripts/dev.sh test --filter=GenerateCharge` để verify Major không bị ảnh hưởng
- [ ] Chạy `./scripts/dev.sh artisan pint`

## Success Criteria

- Generate EGC Charges page chỉ generate cho `intake_pre_uni_gc` students
- Generate Major Charges (existing route) chỉ generate cho `intake_course` students
- Thay đổi EGC logic không cần mở `GenerateBatchChargesAction`
- Tests cũ cho Major generation vẫn pass
