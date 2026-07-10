<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Fixed resit fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function activeExamResitChargeForAttempt(ExamResitAttempt $attempt): FinanceCharge
{
    $obligationId = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->where('obligation_type', AcademicFinanceObligationSource::EXAM_RESIT_FEE)
        ->value('id');

    return FinanceCharge::query()
        ->where('finance_obligation_id', $obligationId)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->firstOrFail();
}

it('creates one exam_resit_fee charge linked to the attempt and transitions it to charge_created', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'fee_amount' => 750_000,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle([
        'attempt_id' => $attempt->id,
    ]);

    $attempt->refresh();
    $charge = activeExamResitChargeForAttempt($attempt);

    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->and((float) $charge->amount)->toBe(750_000.0)
        ->and($charge->semester_id)->toBe($this->semester->id)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->finance_obligation_id)->not->toBeNull()
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->finance_charge_id)->toBe($charge->id)
        ->and($attempt->charge_created_by_user_id)->toBe($this->user->id)
        ->and($attempt->charge_created_at)->not->toBeNull()
        ->and($attempt->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
});

it('bills the charge on the charge_semester, distinct from the operation semester', function () {
    $chargeSemester = Semester::factory()->create();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $chargeSemester->id,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $charge = activeExamResitChargeForAttempt($attempt);

    expect($charge->semester_id)->toBe($chargeSemester->id);
});

it('reuses an existing intake charge instead of creating a duplicate', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $firstChargeId = $attempt->fresh()->finance_charge_id;

    // Force HQ status back so the action can run again (idempotent intake).
    ExamResitAttempt::query()->whereKey($attempt->id)->update([
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $attempt->refresh();

    expect(FinanceCharge::query()
        ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->where('student_id', $this->student->id)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count())->toBe(1)
        ->and($attempt->finance_charge_id)->toBe($firstChargeId)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
});

it('rejects charge creation when the attempt is not waiting for HQ fee creation', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester, [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
    ]);

    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
})->throws(ValidationException::class);
