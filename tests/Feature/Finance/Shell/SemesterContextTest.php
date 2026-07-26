<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Support\SemesterContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceShell')) {
    function grantFinanceShell(array $codes): User
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
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('shares a semester prop with options and selection to semester-aware users', function () {
    $active = Semester::factory()->create(['is_active' => true]);
    Semester::factory()->create(['is_active' => false]);
    $user = grantFinanceShell(['view_finance_student_overview']);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('semester.selected_id', $active->id)
            ->has('semester.options'));
});

it('shares the semester prop to course offering users', function () {
    $active = Semester::factory()->create(['is_active' => true]);
    $user = grantFinanceShell(['view_course_offering']);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('semester.selected_id', $active->id)
            ->has('semester.options'));
});

it('omits the semester prop for users without semester-aware permissions', function () {
    Semester::factory()->create(['is_active' => true]);
    $user = grantFinanceShell([]);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('semester', null));
});

it('persists a selected semester into the session', function () {
    $other = Semester::factory()->create(['is_active' => false]);
    $user = grantFinanceShell(['view_finance_student_overview']);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post('/finance/semester-context', ['_token' => 'test-token', 'semester_id' => $other->id])
        ->assertRedirect();

    expect(session('current_semester_id'))->toBe($other->id);
});

it('persists all semesters as an explicit global selection', function () {
    Semester::factory()->create(['is_active' => true]);
    $user = grantFinanceShell(['view_course_offering']);

    actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post('/semester-context', ['_token' => 'test-token', 'semester_id' => null])
        ->assertRedirect();

    expect(session('current_semester_id'))->toBe(SemesterContextResolver::AllSemesters);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('semester.selected_id', null)
            ->has('semester.options'));
});
