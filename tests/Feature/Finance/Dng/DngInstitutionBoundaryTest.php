<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngCampusMapping;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the current campus through the Institution contract', function (): void {
    $campus = Campus::factory()->create();
    DngCampusMapping::query()->create([
        'campus_id' => $campus->id,
        'provider_code' => 'FAUHN',
    ]);
    app()->instance('campus', $campus);

    $campusReferences = Mockery::mock(CampusReferenceReader::class);
    $campusReferences->shouldReceive('find')
        ->once()
        ->with($campus->id)
        ->andReturn(new CampusReference($campus->id, $campus->name, $campus->code));

    $resolver = new DngCampusCodeResolver($campusReferences);

    expect($resolver->requireCurrentCampusCode())->toBe('FAUHN');
});

it('keeps DNG Institution reads behind neutral contracts', function (): void {
    $resolver = file_get_contents(
        base_path('app/Modules/Finance/Dng/Services/DngCampusCodeResolver.php'),
    );
    $payments = file_get_contents(
        base_path('app/Modules/Finance/Dng/Services/DngPaymentService.php'),
    );

    expect($resolver)->toContain('CampusReferenceReader')
        ->and($resolver)->not->toContain('App\Models\Campus')
        ->and($payments)->toContain('DepartmentReferenceReader')
        ->and($payments)->not->toContain('App\Models\Department');
});
