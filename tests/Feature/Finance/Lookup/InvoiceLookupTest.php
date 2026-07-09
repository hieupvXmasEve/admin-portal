<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\StudentInvoice;
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
    grantFinance($this->user, ['view_finance_invoices'], $this->campus);
});

function makeLookupInvoice(object $context, Student $student): StudentInvoice
{
    return StudentInvoice::create([
        'invoice_number' => 'INV-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
}

function makeLookupInvoiceStudent(object $context): Student
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

it('renders campus-scoped invoices with the filters echoed back', function () {
    $student = makeLookupInvoiceStudent($this);
    makeLookupInvoice($this, $student);
    makeLookupInvoice($this, $student);

    $this->actingAs($this->user)
        ->get(route('finance.invoices.index', ['sort' => 'due_date', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Invoices/Index')
            ->has('invoices.data')
            ->where('filters.sort', 'due_date')
        );
});

it('forbids the invoice lookup without view_finance_invoices', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.invoices.index'))->assertForbidden();
});
