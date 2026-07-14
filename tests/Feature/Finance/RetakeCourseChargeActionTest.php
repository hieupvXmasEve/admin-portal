<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function canonicalRetakeRegistration(): CourseRetakeRegistration
{
    $campus = Campus::factory()->create(['dng_code' => 'HCM']);
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
        'status' => 'intake_course',
    ]);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    return CourseRetakeRegistration::query()->create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'attempt_number' => 2,
        'retake_fee' => 5_000_000,
        'approved_by_user_id' => User::factory()->create()->id,
        'approved_at' => now(),
    ]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5_000_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    app()->instance(DngPaymentService::class, $service);
    $resolver = Mockery::mock(DngCampusCodeResolver::class);
    $resolver->shouldReceive('requireForStudent')->andReturn('HCM');
    app()->instance(DngCampusCodeResolver::class, $resolver);
});

it('materializes the retake obligation then reserves its exact canonical invoice line', function (): void {
    $registration = canonicalRetakeRegistration();

    $result = app(CreateRetakeCourseChargeAction::class)->handle(['registration_id' => $registration->id]);
    $request = DngPaymentRequest::query()->sole();

    $chargeId = FinanceCharge::query()
        ->where('finance_obligation_id', FinanceObligation::query()
            ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
            ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($result))
            ->where('obligation_type', AcademicFinanceObligationSource::RETAKE_FEE)
            ->value('id'))
        ->value('id');

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
        ->and($request->fee_type)->toBe('HL')
        ->and((float) $request->amount)->toBe(5_000_000.0)
        ->and($request->reservationTargets->sole()->invoice_line_id)->toBe(InvoiceLine::query()->where('charge_id', $chargeId)->sole()->id);
});

it('ignores an arbitrary retake amount and uses the catalog-derived canonical target', function (): void {
    $registration = canonicalRetakeRegistration();

    app(CreateRetakeCourseChargeAction::class)->handle([
        'registration_id' => $registration->id,
        'amount' => 1,
    ]);

    expect((float) DngPaymentRequest::query()->sole()->amount)->toBe(5_000_000.0);
});
