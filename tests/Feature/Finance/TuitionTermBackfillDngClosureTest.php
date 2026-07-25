<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

function makeTuitionStudent(Campus $campus, Semester $semester, string $code): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'full_name' => "Student {$code}",
        'email' => strtolower($code).'@example.com',
    ]);
}

function makeTuitionLegacyCharge(
    Student $student,
    Semester $semester,
    float $amount,
    string $status = FinanceCharge::STATUS_ACTIVE,
): FinanceCharge {
    return FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Legacy tuition',
        'effective_at' => now(),
        'status' => $status,
        'source_type' => 'MajorChargeBatch',
        'source_id' => null,
        'created_by_user_id' => test()->user->id,
        'voided_at' => $status === FinanceCharge::STATUS_VOID ? now() : null,
        'voided_by_user_id' => $status === FinanceCharge::STATUS_VOID ? test()->user->id : null,
        'void_reason' => $status === FinanceCharge::STATUS_VOID ? 'legacy void' : null,
    ]);
}

function attachTuitionInvoiceLine(FinanceCharge $charge, float $amountSnapshot): InvoiceLine
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-TUI-'.uniqid(),
        'student_id' => $charge->student_id,
        'semester_id' => $charge->semester_id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => $amountSnapshot,
        'discount_total' => 0,
        'total_amount' => $amountSnapshot,
        'paid_amount' => 0,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amountSnapshot,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);
}

function makeTuitionBatchDngAction(): CreateBatchDngFromChargesAction
{
    return new CreateBatchDngFromChargesAction;
}

// ─── Legacy backfill ────────────────────────────────────────────────────────

it('HP fee_type: fails with no payables when student has no HP charges (no auto-create)', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI3004');

    $action = makeTuitionBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'Tuition DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('Không tìm thấy khoản phí')
        ->and(FinanceCharge::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceObligation::where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0);
});
