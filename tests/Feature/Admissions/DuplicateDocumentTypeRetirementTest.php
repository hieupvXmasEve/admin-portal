<?php

declare(strict_types=1);

use App\Models\StudentApplication;
use App\Modules\Admissions\Queries\GetApplicantDocumentChecklistQuery;
use App\Modules\Admissions\Queries\ListApplicationsQuery;
use App\Modules\Upload\Models\ApplicationDocument;
use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Services\ApplicationDocumentTypeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedFullCatalog(): void
{
    ApplicationDocumentType::factory()->required()->create(['code' => 'id_card_front', 'order' => 1]);
    ApplicationDocumentType::factory()->required()->create(['code' => 'id_card_back', 'order' => 2]);
    ApplicationDocumentType::factory()->create(['code' => 'diploma', 'order' => 3]);
    ApplicationDocumentType::factory()->required()->create(['code' => 'transcript', 'order' => 4]);
    ApplicationDocumentType::factory()->required()->create(['code' => 'transcript_1', 'order' => 5]);
    ApplicationDocumentType::factory()->create(['code' => 'english_certificate', 'order' => 6]);
    ApplicationDocumentType::factory()->create(['code' => 'english_certificare', 'order' => 7]);
    ApplicationDocumentType::factory()->create(['code' => 'other_achievements', 'order' => 8]);
    ApplicationDocumentType::factory()->create(['code' => 'other_achievements_1', 'order' => 9]);
    ApplicationDocumentType::factory()->create(['code' => 'other_achievements_2', 'order' => 10]);
    ApplicationDocumentType::factory()->create(['code' => 'scholarship_certificate', 'order' => 11]);
}

it('lists the exact active catalog after retirement', function () {
    seedFullCatalog();
    ApplicationDocumentType::query()
        ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
        ->update(['active' => false]);

    $active = ApplicationDocumentType::query()->activeOrdered()->pluck('code')->all();

    expect($active)->toBe([
        'id_card_front',
        'id_card_back',
        'diploma',
        'transcript',
        'english_certificate',
        'other_achievements',
        'other_achievements_1',
        'scholarship_certificate',
    ]);
});

it('excludes retired codes but keeps other_achievements_1 in ListApplicationsQuery filters', function () {
    seedFullCatalog();
    ApplicationDocumentType::query()
        ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
        ->update(['active' => false]);

    $codes = app(ListApplicationsQuery::class)->filters(null)['document_types']->pluck('code')->all();

    foreach (ApplicationDocumentType::RETIRED_CODES as $retired) {
        expect($codes)->not->toContain($retired);
    }
    expect($codes)->toContain('other_achievements_1');
});

it('keeps a retired code inactive even when the sync payload marks it active', function () {
    seedFullCatalog();

    app(ApplicationDocumentTypeSyncService::class)->sync([
        ['code' => 'transcript_1', 'active' => true, 'required' => true],
    ]);

    expect(ApplicationDocumentType::query()->where('code', 'transcript_1')->value('active'))->toBeFalse();
});

it('places a retired-type document in the uncatalogued tail as not required and not missing', function () {
    seedFullCatalog();
    ApplicationDocumentType::query()
        ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
        ->update(['active' => false]);

    $application = StudentApplication::factory()->create();
    ApplicationDocument::factory()->forApplication($application)->create([
        'file_type_code' => 'transcript_1',
        'file_type_name' => 'Học bạ THPT',
    ]);

    $result = app(GetApplicantDocumentChecklistQuery::class)->handle($application);
    $group = collect($result['groups'])->firstWhere('code', 'transcript_1');

    expect($group)->not->toBeNull()
        ->and($group['required'])->toBeFalse()
        ->and($group['is_missing'])->toBeFalse();
});

it('flips an application from incomplete to complete once transcript_1 is retired', function () {
    seedFullCatalog();

    $application = StudentApplication::factory()->create();
    ApplicationDocument::factory()->forApplication($application)->create(['file_type_code' => 'id_card_front']);
    ApplicationDocument::factory()->forApplication($application)->create(['file_type_code' => 'id_card_back']);
    ApplicationDocument::factory()->forApplication($application)->create(['file_type_code' => 'transcript']);

    $before = app(GetApplicantDocumentChecklistQuery::class)->handle($application);
    expect($before['missing_required'])->toContain('transcript_1');

    ApplicationDocumentType::query()
        ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
        ->update(['active' => false]);

    $after = app(GetApplicantDocumentChecklistQuery::class)->handle($application);
    expect($after['missing_required'])->not->toContain('transcript_1')
        ->and($after['missing_required'])->toBe([]);
});
