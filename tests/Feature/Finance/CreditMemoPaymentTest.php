<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['create_finance_charges', 'void_finance_charges', 'view_finance_charges']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('rejects negative charges instead of creating credit memo payments', function () {
    $action = app(CreateFinanceChargeAction::class);

    expect(fn () => $action->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
        'amount' => -5000000,
        'description' => 'EGC refund',
    ]))->toThrow(InvalidArgumentException::class, 'debit obligations only');

    expect(Payment::where('source', 'credit_memo')->count())->toBe(0);
    expect(InvoiceLine::count())->toBe(0);
});

it('does not assign a negative charge to an invoice', function () {
    $action = app(CreateFinanceChargeAction::class);

    expect(fn () => $action->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
        'amount' => -4500000,
        'description' => 'Scholarship discount',
    ]))->toThrow(InvalidArgumentException::class, 'debit obligations only');

    expect(FinanceCharge::query()->count())->toBe(0);
    expect(InvoiceLine::count())->toBe(0);
});
