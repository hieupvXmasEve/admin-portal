<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CaptureDngProviderReceiptAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('captures the actual provider receipt exactly once despite reservation drift', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()->forProgram($program)->withEffectiveSemester($semester)->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'student_id' => 'STU-RECEIPT-001',
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ])->create();
    $request = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'ITEM-RECEIPT-001',
        'amount' => 100000,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'PAY-RECEIPT-001',
        'paid_at' => now(),
    ]);
    $receipt = [
        'amount' => '75000',
        'payload' => ['PaymentId' => 'PAY-RECEIPT-001', 'Amount' => '75000'],
        'source' => 'daily_reconciliation',
        'authenticity' => ['status' => 'provider_authenticated_query'],
        'payer_correlation' => ['status' => 'matched'],
        'target_validation' => ['status' => 'amount_mismatch'],
    ];

    $first = CaptureDngProviderReceiptAction::run(['request' => $request, 'receipt' => $receipt]);
    $second = CaptureDngProviderReceiptAction::run(['request' => $request->fresh(), 'receipt' => $receipt]);

    expect($first)->not->toBeNull()
        ->and($second?->id)->toBe($first?->id)
        ->and(Payment::query()->count())->toBe(1)
        ->and((float) $first->amount)->toBe(75000.0)
        ->and($first->external_ref)->toBe('PAY-RECEIPT-001')
        ->and($first->raw_payload)->toMatchArray([
            'dng_payment_request_id' => $request->id,
            'receipt_source' => 'daily_reconciliation',
            'target_validation' => ['status' => 'amount_mismatch'],
        ])
        ->and($request->fresh()->payment_id)->toBe($first->id)
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($request->fresh()->error_message)->toContain('target allocation requires review')
        ->and(DngReceiptException::query()->count())->toBe(1);

    $exception = DngReceiptException::query()->sole();
    expect($exception->status)->toBe(DngReceiptException::STATUS_OPEN)
        ->and($exception->payment_id)->toBe($first->id)
        ->and($exception->mismatch_reasons)->toBe(['Provider receipt requires target reconciliation.'])
        ->and($exception->raw_provider_evidence)->toBe(['PaymentId' => 'PAY-RECEIPT-001', 'Amount' => '75000']);
});
