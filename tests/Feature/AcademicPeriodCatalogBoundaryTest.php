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
