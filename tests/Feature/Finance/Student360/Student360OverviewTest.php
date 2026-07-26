<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantStudent360')) {
    function grantStudent360(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(CampusPermissionReader::class);
        $mock->shouldReceive('permissionCodesForUserId')->andReturn($codes);
        app()->singleton(CampusPermissionReader::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
});

it('exposes the four status cards and action flags on the 360', function () {
    $user = grantStudent360(['view_finance_student_overview']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Student360/Show')
            ->has('status_cards.balance')
            ->has('status_cards.dng')
            ->has('status_cards.installments')
            ->has('status_cards.exception')
            ->has('actions')
            ->missing('ledger_groups'));
});

it('reports action permission flags from the user permissions', function () {
    $user = grantStudent360(['view_finance_student_overview', 'create_finance_payments']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('actions.can_record_payment', true)
            ->where('actions.can_cancel_dng', true)
            ->where('actions.can_void_charges', false)
            ->where('actions.can_allocate', false));
});

it('surfaces the latest unapplied payment id on the balance card', function () {
    $user = grantStudent360(['view_finance_student_overview']);

    Payment::create([
        'student_id' => $this->student->id,
        'amount' => 1000000,
        'method' => Payment::METHOD_CASH,
        'source' => 'manual',
        'paid_at' => now()->subDay(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $latest = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 2000000,
        'method' => Payment::METHOD_CASH,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.balance.has_unapplied', true)
            ->where('status_cards.balance.unapplied_payment_id', $latest->id));
});

it('returns null unapplied payment id when no unapplied credit exists', function () {
    $user = grantStudent360(['view_finance_student_overview']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.balance.has_unapplied', false)
            ->where('status_cards.balance.unapplied_payment_id', null));
});

it('loads grouped ledger data as a deferred prop', function () {
    $user = grantStudent360(['view_finance_student_overview']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('default', fn ($reload) => $reload->has('ledger_groups')));
});
