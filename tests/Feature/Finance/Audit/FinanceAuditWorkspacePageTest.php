<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantAuditWorkspace')) {
    function grantAuditWorkspace(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(CampusPermissionReader::class);
        $mock->shouldReceive('permissionCodesForUserId')->andReturn($codes);
        app()->singleton(CampusPermissionReader::class, fn () => $mock);

        return $user;
    }
}

if (! function_exists('makeWorkspaceInvoice')) {
    function makeWorkspaceInvoice(Campus $campus, Program $program, Semester $semester, string $number): StudentInvoice
    {
        $student = Student::factory()->forCampus($campus)->forProgram($program)
            ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
            ->create();

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

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('renders the workspace and resolves a visible invoice', function () {
    $user = grantAuditWorkspace(['view_finance_audit_workspace']);
    makeWorkspaceInvoice($this->campus, $this->program, $this->semester, 'INV-RES-001');

    actingAs($user)->get('/finance/audit?q=INV-RES-001')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Audit/Workspace')
            ->where('resolution.status', 'single')
            ->where('resolution.target.type', 'invoice')
            ->where('allowed_actions.export', false));
});

it('denies access without the permission', function () {
    $user = grantAuditWorkspace([]);

    actingAs($user)->get('/finance/audit')->assertForbidden();
});

it('does not reveal a cross-campus invoice number', function () {
    $user = grantAuditWorkspace(['view_finance_audit_workspace']);
    makeWorkspaceInvoice($this->otherCampus, $this->program, $this->semester, 'INV-OTHER-001');

    actingAs($user)->get('/finance/audit?q=INV-OTHER-001')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Audit/Workspace')
            ->where('resolution.status', 'empty'));
});

it('accepts cockpit semester drilldown params without redirecting away', function () {
    $user = grantAuditWorkspace(['view_finance_audit_workspace', 'view_finance_all_campus']);

    actingAs($user)->get('/finance/audit?finding_code=INV-5&scope=semester')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Audit/Workspace')
            ->where('filters.finding_code', 'INV-5')
            ->where('filters.scope', 'semester'));
});
