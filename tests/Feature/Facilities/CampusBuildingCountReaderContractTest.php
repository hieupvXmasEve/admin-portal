<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Facilities\Models\Building;
use App\Shared\Contracts\Academic\CampusBuildingCountReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Institution reads building counts through this contract rather than
 * importing the Building model, which would trip the zero-tolerance
 * cross_context_concrete_imports boundary rule.
 */
it('counts buildings per campus', function (): void {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();

    Building::factory()->count(2)->forCampus($campusA)->create();
    Building::factory()->count(1)->forCampus($campusB)->create();

    $counts = app(CampusBuildingCountReader::class)->countByCampusIds([$campusA->id, $campusB->id]);

    expect($counts)->toBe([
        $campusA->id => 2,
        $campusB->id => 1,
    ]);
});

it('omits a campus with no buildings from the result', function (): void {
    $campus = Campus::factory()->create();

    $counts = app(CampusBuildingCountReader::class)->countByCampusIds([$campus->id]);

    expect($counts)->toBe([]);
});

it('returns an empty array for an empty campus id list', function (): void {
    expect(app(CampusBuildingCountReader::class)->countByCampusIds([]))->toBe([]);
});
