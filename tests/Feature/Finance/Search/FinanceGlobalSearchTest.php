<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceSearch')) {
    function grantFinanceSearch(array $codes): User
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
});

it('resolves a student code to a 360 deep link', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id, 'student_id' => 'SWB12345'])
        ->create();

    actingAs($user)->getJson('/finance/search?q=SWB12345')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'single')
        ->assertJsonPath('data.results.0.type', 'student')
        ->assertJsonPath('data.results.0.id', $student->id);
});

it('maps an invoice number to the owning student 360 with a focus link', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-SEARCH-1',
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 0, 'discount_total' => 0, 'total_amount' => 0, 'paid_amount' => 0,
    ]);

    actingAs($user)->getJson('/finance/search?q=INV-SEARCH-1')
        ->assertOk()
        ->assertJsonPath('data.status', 'single')
        ->assertJsonPath('data.results.0.type', 'invoice')
        ->assertJsonPath('data.results.0.id', $student->id); // result id is the owning student
});

it('returns empty for a cross-campus identifier', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id, 'student_id' => 'OTHER999'])
        ->create();

    actingAs($user)->getJson('/finance/search?q=OTHER999')
        ->assertOk()
        ->assertJsonPath('data.status', 'empty')
        ->assertJsonPath('data.results', []);
});

it('denies search without the permission', function () {
    $user = grantFinanceSearch([]);
    actingAs($user)->getJson('/finance/search?q=anything')->assertForbidden();
});
