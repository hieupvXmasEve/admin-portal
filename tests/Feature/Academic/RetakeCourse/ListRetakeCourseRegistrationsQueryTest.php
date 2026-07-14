<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Queries\ListRetakeCourseRegistrationsQuery;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRetakeListRegistration(bool $paid, ?Campus $campus = null): array
{
    $user = User::factory()->create();
    $campus ??= Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $registration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 5000000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);

    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'created_by_user_id' => $user->id,
    ]);
    if ($paid) {
        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => 5000000,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'dng',
            'external_ref' => 'DNG-RETAKE-LIST-'.$registration->id,
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
        app(SettlementService::class)->createPaymentApplication(
            $payment,
            $line,
            5000000,
            'application',
            $user->id,
            ListRetakeCourseRegistrationsQuery::class,
        );
    }

    return compact('campus', 'registration', 'charge');
}

it('filters paid waiting class by derived payment state, not raw retake status', function () {
    ['campus' => $campus, 'registration' => $registration] = createRetakeListRegistration(paid: true);

    $result = app(ListRetakeCourseRegistrationsQuery::class)->handle([
        'operation_state' => 'paid_waiting_class',
        'per_page' => 15,
    ], $campus->id);

    $rows = $result['registrations']->items();

    expect($result['registrations']->total())->toBe(1)
        ->and($rows[0]['id'])->toBe($registration->id)
        ->and($rows[0]['status'])->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
        ->and($rows[0]['payment_state']['value'])->toBe('paid')
        ->and($rows[0]['operation_state']['value'])->toBe('paid_waiting_class');
});

it('keeps summary counts independent from the selected operation filter', function () {
    ['campus' => $campus] = createRetakeListRegistration(paid: true);
    createRetakeListRegistration(paid: false, campus: $campus);

    $result = app(ListRetakeCourseRegistrationsQuery::class)->handle([
        'operation_state' => 'paid_waiting_class',
        'per_page' => 15,
    ], $campus->id);

    expect($result['registrations']->total())->toBe(1)
        ->and($result['summary']['total'])->toBe(2)
        ->and($result['summary']['paid_waiting_class'])->toBe(1)
        ->and($result['summary']['awaiting_payment'])->toBe(1);
});

it('treats linked paid dng evidence as paid before ledger bridge', function () {
    ['campus' => $campus, 'registration' => $registration, 'charge' => $charge] = createRetakeListRegistration(paid: false);

    $dng = DngPaymentRequest::create([
        'student_id' => $registration->student_id,
        'campus_code' => 'TEST',
        'student_code' => $registration->student->student_id,
        'fee_type' => 'HL',
        'description' => 'Retake fee',
        'semester_id' => $charge->semester_id,
        'due_date' => now()->addDays(5),
        'item_id' => 'HL-LIST-'.$charge->id,
        'amount' => $charge->amount,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'DNG-HL-LIST-'.$charge->id,
        'paid_at' => now(),
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $dng->id,
        'finance_charge_id' => $charge->id,
        'amount' => $charge->amount,
    ]);

    $result = app(ListRetakeCourseRegistrationsQuery::class)->handle([
        'operation_state' => 'paid_waiting_class',
        'per_page' => 15,
    ], $campus->id);
    $rows = $result['registrations']->items();

    expect($result['registrations']->total())->toBe(1)
        ->and($rows[0]['id'])->toBe($registration->id)
        ->and($rows[0]['payment_state']['value'])->toBe('paid')
        ->and($rows[0]['operation_state']['value'])->toBe('paid_waiting_class')
        ->and($result['summary']['paid_waiting_class'])->toBe(1)
        ->and($result['summary']['awaiting_payment'])->toBe(0)
        ->and($dng->fresh()->payment_id)->toBeNull();
});
