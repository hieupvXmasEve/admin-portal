<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Academic\Catalog\Models\CampusPeriodSchedule;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Support\SemesterContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves one current academic period independently of campus schedules', function (): void {
    $period = Semester::factory()->create([
        'code' => '2026-FALL',
        'is_active' => true,
        'is_archived' => false,
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'enrollment_start_date' => '2026-06-01',
        'enrollment_end_date' => '2026-07-31',
    ]);
    $firstCampus = Campus::factory()->create();
    $secondCampus = Campus::factory()->create();

    CampusPeriodSchedule::create([
        'campus_id' => $firstCampus->id,
        'semester_id' => $period->id,
        'operating_start_date' => '2026-08-08',
        'operating_end_date' => '2027-01-07',
        'registration_start_date' => '2026-06-08',
        'registration_end_date' => '2026-08-07',
    ]);

    $reader = app(AcademicPeriodReader::class);
    $currentPeriod = $reader->current();
    $firstSchedule = $reader->scheduleForCampus($period->id, $firstCampus->id);
    $secondSchedule = $reader->scheduleForCampus($period->id, $secondCampus->id);

    expect($currentPeriod)
        ->not->toBeNull()
        ->id->toBe($period->id)
        ->code->toBe('2026-FALL')
        ->and($firstSchedule->academic_period_id)->toBe($period->id)
        ->and($firstSchedule->operating_start_date->toDateString())->toBe('2026-08-08')
        ->and($firstSchedule->registration_end_date->toDateString())->toBe('2026-08-07')
        ->and($secondSchedule->academic_period_id)->toBe($period->id)
        ->and($secondSchedule->operating_start_date->toDateString())->toBe('2026-08-01')
        ->and($secondSchedule->registration_end_date->toDateString())->toBe('2026-07-31');
});

it('keeps the selected academic period as filtering state rather than current-period state', function (): void {
    $current = Semester::factory()->create(['is_active' => true, 'is_archived' => false]);
    $selected = Semester::factory()->create(['is_active' => false, 'is_archived' => false]);

    session([SemesterContextResolver::SessionKey => $selected->id]);

    expect(SemesterContextResolver::selectedId())->toBe($selected->id)
        ->and(app(AcademicPeriodReader::class)->current()?->id)->toBe($current->id);
});

it('keeps semester and selected-period routes on the Catalog owner', function (): void {
    $routes = app('router')->getRoutes();

    $contracts = [
        ['semesters.index', 'semesters', ['GET', 'HEAD'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@index', ['web', 'auth', 'can:view_semester']],
        ['semesters.store', 'semesters', ['POST'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@store', ['web', 'auth', 'can:create_semester']],
        ['semesters.update', 'semesters/{semester}', ['PUT'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@update', ['web', 'auth', 'can:edit_semester']],
        ['semesters.destroy', 'semesters/{semester}', ['DELETE'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@destroy', ['web', 'auth', 'can:delete_semester']],
        ['api.semesters.update', 'api/semesters/{semester}', ['PUT'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@apiUpdate', ['web', 'auth', 'can:edit_semester']],
        ['semesters.activate', 'api/semesters/{semester}/activate', ['POST'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@activate', ['web', 'auth', 'can:edit_semester']],
        ['semesters.deactivate', 'api/semesters/{semester}/deactivate', ['POST'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@deactivate', ['web', 'auth', 'can:edit_semester']],
        ['semesters.campus-schedules.upsert', 'api/semesters/{semester}/campus-schedules', ['PUT'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@upsertCampusSchedule', ['web', 'auth', 'can:edit_semester']],
        ['semesters.activation-statuses', 'api/semesters/activation-statuses', ['GET', 'HEAD'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\AcademicPeriodController@activationStatuses', ['web', 'auth', 'can:view_semester']],
        ['semester-context.update', 'semester-context', ['POST'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\SelectedAcademicPeriodController@update', ['web', 'auth', 'verified']],
        ['finance.semester-context.update', 'finance/semester-context', ['POST'], 'App\\Modules\\Academic\\Catalog\\Http\\Web\\SelectedAcademicPeriodController@update', ['web', 'auth']],
        ['semesters.enrollment.show', 'semesters/{semester}/enrollment', ['GET', 'HEAD'], 'App\\Http\\Controllers\\Web\\SemesterEnrollmentController@show', ['web', 'auth', 'can:edit_semester']],
    ];

    foreach ($contracts as [$name, $uri, $methods, $action, $middleware]) {
        $route = $routes->getByName($name);

        expect($route)
            ->not->toBeNull()
            ->and($route?->uri())->toBe($uri)
            ->and($route?->methods())->toContain(...$methods)
            ->and($route?->getActionName())->toBe($action)
            ->and($route?->gatherMiddleware())->toContain(...$middleware);
    }
});
