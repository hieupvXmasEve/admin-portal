<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Facilities\Models\Building;
use App\Modules\Facilities\Models\Room;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Academic reads room options for its class-session forms through this
 * Facilities contract rather than importing the Room model, which would trip
 * the zero-tolerance cross_context_concrete_imports boundary rule.
 */
beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->building = Building::factory()->create(['campus_id' => $this->campus->id]);

    $this->bookable = Room::factory()->forBuilding($this->building)->create([
        'name' => 'A-Bookable',
        'status' => Room::STATUS_AVAILABLE,
        'is_bookable' => true,
    ]);

    $this->underMaintenance = Room::factory()->forBuilding($this->building)->create([
        'name' => 'B-Maintenance',
        'status' => Room::STATUS_MAINTENANCE,
        'is_bookable' => true,
    ]);

    $this->notBookable = Room::factory()->forBuilding($this->building)->create([
        'name' => 'C-NotBookable',
        'status' => Room::STATUS_AVAILABLE,
        'is_bookable' => false,
    ]);
});

it('returns every room on the campus through forCampus, ordered by name', function (): void {
    $references = app(SpaceReferenceReader::class)->forCampus($this->campus->id);

    expect(array_column($references, 'name'))
        ->toBe(['A-Bookable', 'B-Maintenance', 'C-NotBookable']);
});

it('returns only bookable, available rooms through bookableForCampus', function (): void {
    $references = app(SpaceReferenceReader::class)->bookableForCampus($this->campus->id);

    expect($references)->toHaveCount(1)
        ->and($references[0]->id)->toBe($this->bookable->id)
        ->and($references[0]->isBookable)->toBeTrue()
        ->and($references[0]->status)->toBe(Room::STATUS_AVAILABLE);
});

it('carries the building so Academic can label a room without reading Facilities models', function (): void {
    $references = app(SpaceReferenceReader::class)->bookableForCampus($this->campus->id);

    expect($references[0]->building)->toMatchArray([
        'id' => $this->building->id,
        'name' => $this->building->name,
    ]);
});

it('excludes rooms belonging to another campus', function (): void {
    $otherCampus = Campus::factory()->create();
    Room::factory()->create([
        'campus_id' => $otherCampus->id,
        'status' => Room::STATUS_AVAILABLE,
        'is_bookable' => true,
    ]);

    expect(app(SpaceReferenceReader::class)->bookableForCampus($this->campus->id))
        ->toHaveCount(1);
});
