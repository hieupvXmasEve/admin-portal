<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Shared\Contracts\Admissions\IntakeSemesterReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the semester id from the configured CRM intake code', function (): void {
    $semester = Semester::factory()->create();
    app(CrmMappingSettings::class)->setIntakeCode($semester->code);

    expect(app(IntakeSemesterReader::class)->currentIntakeSemesterId())->toBe($semester->id);
});

it('returns null when no intake mapping is configured', function (): void {
    expect(app(IntakeSemesterReader::class)->currentIntakeSemesterId())->toBeNull();
});
