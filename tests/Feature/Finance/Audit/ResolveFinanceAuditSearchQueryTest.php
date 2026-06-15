<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Queries\Audit\ResolveFinanceAuditSearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (! function_exists('resolverStudent')) {
    function resolverStudent(Campus $campus, Program $program, Semester $semester, array $state = []): Student
    {
        return Student::factory()->forCampus($campus)->forProgram($program)
            ->state(array_merge(['intake' => 1, 'intake_semester_id' => $semester->id], $state))
            ->create();
    }
}

if (! function_exists('resolverInvoice')) {
    function resolverInvoice(Student $student, Semester $semester, string $number): StudentInvoice
    {
        return StudentInvoice::create([
            'invoice_number' => $number,
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'subtotal' => 0,
            'discount_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
        ]);
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
});

it('resolves an exact invoice number to a single invoice target', function () {
    $student = resolverStudent($this->campus, $this->program, $this->semester);
    $invoice = resolverInvoice($student, $this->semester, 'INV-RES-001');

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('INV-RES-001', $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'invoice', 'id' => $invoice->id]);
});

it('resolves an exact student code to a student target, outranking a colliding external_ref', function () {
    $student = resolverStudent($this->campus, $this->program, $this->semester, ['student_id' => 'SWB123']);

    // A payment whose external_ref collides with the MSSV must NOT win.
    $other = resolverStudent($this->campus, $this->program, $this->semester);
    Payment::create([
        'student_id' => $other->id,
        'amount' => 100,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'external_ref' => 'SWB123',
    ]);

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('SWB123', $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'student', 'id' => $student->id]);
});

it('resolves an explicit prefix id directly, ahead of any free-form search', function () {
    $student = resolverStudent($this->campus, $this->program, $this->semester);
    $invoice = resolverInvoice($student, $this->semester, 'INV-PFX-001');

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle("invoice:{$invoice->id}", $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'invoice', 'id' => $invoice->id]);
});

it('never resolves a bare integer to a guessed entity', function () {
    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('12345', $this->campus->id);
    expect($result['status'])->toBe('empty');
});

it('does not surface a same-code record from another campus', function () {
    resolverStudent($this->otherCampus, $this->program, $this->semester, ['student_id' => 'SWB999']);

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('SWB999', $this->campus->id);
    expect($result['status'])->toBe('empty');
});

it('returns ambiguous when a name matches multiple students', function () {
    resolverStudent($this->campus, $this->program, $this->semester, ['full_name' => 'Nguyen Van A']);
    resolverStudent($this->campus, $this->program, $this->semester, ['full_name' => 'Nguyen Van A']);

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('Nguyen Van A', $this->campus->id);

    expect($result['status'])->toBe('ambiguous')
        ->and(count($result['matches']))->toBeGreaterThanOrEqual(2);
});
