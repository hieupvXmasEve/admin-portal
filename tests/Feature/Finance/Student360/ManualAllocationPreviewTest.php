<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\Payment;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantAllocPreview')) {
    function grantAllocPreview(array $codes): User
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

it('previews a payment unapplied amount and candidate lines', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_id', $payment->id)
        ->assertJsonPath('data.unapplied', 1000000)
        ->assertJsonStructure(['data' => ['payment_id', 'unapplied', 'candidates']]);
});

it('denies preview without allocate permission', function () {
    $user = grantAllocPreview([]);
    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertForbidden();
});

it('hides a cross-campus payment as not found', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $other = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
    $payment = Payment::create([
        'student_id' => $other->id,
        'amount' => 1,
        'method' => Payment::METHOD_OTHER,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertNotFound();
});
