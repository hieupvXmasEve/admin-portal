<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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
    grantFinance($this->user, ['view_finance_payments'], $this->campus);
});

function makeLookupPaymentStudent(object $context): Student
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

it('renders payments with stats and accepts a date_range filter', function () {
    $student = makeLookupPaymentStudent($this);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 500000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'manual',
        'external_ref' => 'PAY-LOOKUP-001',
        'paid_at' => '2026-06-10 09:00:00',
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('finance.payments.index', [
            'date_range' => ['2026-06-01', '2026-06-30'],
            'sort' => 'amount',
            'direction' => 'asc',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Payments/Index')
            ->has('items.data')
            ->has('stats')
            ->where('filters.sort', 'amount')
        );
});

it('rejects a malformed date_range', function () {
    $this->actingAs($this->user)
        ->get(route('finance.payments.index', ['date_range' => 'not-an-array']))
        ->assertSessionHasErrors('date_range');
});

it('forbids the payment lookup without view_finance_payments', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.payments.index'))->assertForbidden();
});
