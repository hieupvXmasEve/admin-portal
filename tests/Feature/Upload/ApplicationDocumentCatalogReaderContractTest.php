<?php

declare(strict_types=1);

use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Admissions reads the document-type catalog through this contract rather
 * than importing ApplicationDocumentType, which would trip the
 * zero-tolerance cross_context_concrete_imports boundary rule.
 */
it('returns active types in catalog order', function (): void {
    ApplicationDocumentType::factory()->create(['code' => 'z_type', 'name' => 'Z', 'order' => 2, 'active' => true]);
    ApplicationDocumentType::factory()->create(['code' => 'a_type', 'name' => 'A', 'order' => 1, 'active' => true]);
    ApplicationDocumentType::factory()->create(['code' => 'inactive_type', 'name' => 'Inactive', 'order' => 0, 'active' => false]);

    $types = app(ApplicationDocumentCatalogReader::class)->activeOrdered();

    expect(array_column($types, 'code'))->toBe(['a_type', 'z_type']);
});

it('carries required and intRequired flags on each type', function (): void {
    ApplicationDocumentType::factory()->create([
        'code' => 'transcript', 'name' => 'Transcript', 'required' => true, 'int_required' => false,
    ]);
    ApplicationDocumentType::factory()->create([
        'code' => 'passport', 'name' => 'Passport', 'required' => false, 'int_required' => true,
    ]);

    $types = app(ApplicationDocumentCatalogReader::class)->activeOrdered();
    $byCode = collect($types)->keyBy('code');

    expect($byCode['transcript']->required)->toBeTrue()
        ->and($byCode['transcript']->intRequired)->toBeFalse()
        ->and($byCode['passport']->required)->toBeFalse()
        ->and($byCode['passport']->intRequired)->toBeTrue();
});

it('maps names by code, matching only the requested codes', function (): void {
    ApplicationDocumentType::factory()->create(['code' => 'a', 'name' => 'Alpha']);
    ApplicationDocumentType::factory()->create(['code' => 'b', 'name' => 'Beta']);
    ApplicationDocumentType::factory()->create(['code' => 'c', 'name' => 'Gamma']);

    $names = app(ApplicationDocumentCatalogReader::class)->namesByCode(['a', 'c']);

    expect($names)->toBe(['a' => 'Alpha', 'c' => 'Gamma']);
});

it('returns an empty map for an empty code list', function (): void {
    expect(app(ApplicationDocumentCatalogReader::class)->namesByCode([]))->toBe([]);
});
