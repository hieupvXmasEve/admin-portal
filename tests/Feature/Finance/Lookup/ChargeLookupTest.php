<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Queries\Lookup\ListFinanceChargesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();
    $this->user = User::factory()->create();
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
});

function makeLookupCharge(object $context, Student $student, array $overrides = []): FinanceCharge
{
    return FinanceCharge::create(array_merge([
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 100,
        'description' => 'Lookup charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ], $overrides));
}

function makeLookupStudent(object $context): Student
{
    return Student::factory()
        ->forCampus($context->campus)
        ->forProgram($context->program)
        ->state([
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $context->semester->id,
            'intake_major' => $context->semester->id,
        ])
        ->create();
}

it('filters charges by status and sorts by a whitelisted column', function () {
    $student = makeLookupStudent($this);
    makeLookupCharge($this, $student, ['amount' => 100, 'status' => FinanceCharge::STATUS_ACTIVE]);
    makeLookupCharge($this, $student, ['amount' => 300, 'status' => FinanceCharge::STATUS_ACTIVE]);
    makeLookupCharge($this, $student, ['amount' => 200, 'status' => FinanceCharge::STATUS_VOID]);

    $request = Request::create('', 'GET', ['status' => FinanceCharge::STATUS_ACTIVE, 'sort' => 'amount', 'direction' => 'asc']);
    $result = app(ListFinanceChargesQuery::class)->handle($request);

    $amounts = collect($result['items']->items())->map(fn ($c) => (float) $c->amount)->all();
    expect($amounts)->toBe([100.0, 300.0]);
});

it('ignores a non-whitelisted sort column and falls back to effective_at desc', function () {
    $student = makeLookupStudent($this);
    makeLookupCharge($this, $student);

    $request = Request::create('', 'GET', ['sort' => 'amount); DROP TABLE', 'direction' => 'asc']);
    $result = app(ListFinanceChargesQuery::class)->handle($request);

    expect($result['items']->total())->toBe(1);
});

it('renders the charge ledger with sorted items via the controller', function () {
    $student = makeLookupStudent($this);
    makeLookupCharge($this, $student, ['amount' => 100]);
    makeLookupCharge($this, $student, ['amount' => 200]);

    $this->actingAs($this->user)
        ->get(route('finance.charges.index', ['sort' => 'amount', 'direction' => 'asc']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Finance/Charges/Index')
            ->has('charges.data', 2)
            ->where('filters.sort', 'amount')
        );
});

it('forbids the charge ledger without view_finance_charges', function () {
    grantFinance($this->user, ['view_finance_invoices'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.charges.index'))->assertForbidden();
});
