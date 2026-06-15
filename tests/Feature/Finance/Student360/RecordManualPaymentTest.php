<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantRecordPayment')) {
    function grantRecordPayment(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
});

it('records a manual payment for a visible student', function () {
    $user = grantRecordPayment(['create_finance_payments']);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post("/finance/students/{$this->student->id}/payments", [
            '_token' => 'test-token',
            'amount' => 500000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'external_ref' => 'RCPT-001',
            'notes' => 'Cash at counter',
        ])->assertRedirect();

    expect(Payment::where('student_id', $this->student->id)->where('amount', 500000)->exists())->toBeTrue();
});

it('rejects a non-positive amount', function () {
    $user = grantRecordPayment(['create_finance_payments']);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post("/finance/students/{$this->student->id}/payments", ['_token' => 'test-token', 'amount' => 0, 'method' => 'cash'])
        ->assertSessionHasErrors('amount');
});

it('denies recording without permission', function () {
    $user = grantRecordPayment([]);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post("/finance/students/{$this->student->id}/payments", ['_token' => 'test-token', 'amount' => 1, 'method' => 'cash'])
        ->assertForbidden();
});

it('hides a cross-campus student as not found', function () {
    $user = grantRecordPayment(['create_finance_payments']);
    $other = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post("/finance/students/{$other->id}/payments", ['_token' => 'test-token', 'amount' => 1, 'method' => 'cash'])
        ->assertNotFound();
});
