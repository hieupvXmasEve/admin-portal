<?php

declare(strict_types=1);

use App\Models\ApplicationDocument;
use App\Models\Campus;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Services\ApplicationDocumentTypeSyncService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

const DOC_PERMISSIONS = [
    'view_student_application',
    'edit_student_application',
];

beforeEach(function () {
    Cache::flush();

    $campus = Campus::factory()->create();
    $this->campus = $campus;

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(DOC_PERMISSIONS);
    $this->app->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->app->singleton('campus', fn () => $campus);
    session(['current_campus_id' => $campus->id]);

    $this->staff = User::factory()->create(['type' => UserType::STAFF]);
});

function documentApplication(Campus $campus, array $overrides = []): StudentApplication
{
    return StudentApplication::factory()->pending()->create(array_merge([
        'campus_code' => $campus->code,
        'student_code' => 'S'.fake()->unique()->numerify('#######'),
    ], $overrides));
}

/**
 * The document checklist prop returned to the staff show screen.
 *
 * @return array{groups: array<int, array<string, mixed>>, missing_required: array<int, string>}
 */
function fetchChecklist(StudentApplication $application, User $staff): array
{
    $checklist = null;

    test()->actingAs($staff)
        ->get(route('student-applications.show', $application))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$checklist) {
            $checklist = $page->toArray()['props']['documentChecklist'];
        });

    return $checklist;
}

/** Find a checklist group by its document-type code. */
function group(array $checklist, string $code): ?array
{
    foreach ($checklist['groups'] as $group) {
        if ($group['code'] === $code) {
            return $group;
        }
    }

    return null;
}

it('lists an application\'s documents grouped by type on the show screen', function () {
    $type = ApplicationDocumentType::factory()->create(['code' => 'id_card', 'name' => 'ID card']);
    $application = documentApplication($this->campus);
    ApplicationDocument::factory()->forApplication($application)->ofType($type)->create([
        'original_name' => 'front.jpg',
    ]);

    $checklist = fetchChecklist($application, $this->staff);
    $idCard = group($checklist, 'id_card');

    expect($idCard)->not->toBeNull();
    expect($idCard['name'])->toBe('ID card');
    expect($idCard['documents'])->toHaveCount(1);
    expect($idCard['documents'][0]['original_name'])->toBe('front.jpg');
});

it('groups multiple files under the same type, ordered by page index', function () {
    $type = ApplicationDocumentType::factory()->create(['code' => 'transcript', 'name' => 'Transcript']);
    $application = documentApplication($this->campus);

    ApplicationDocument::factory()->forApplication($application)->ofType($type)->create([
        'original_name' => 'page-2.pdf',
        'page_index' => 1,
    ]);
    ApplicationDocument::factory()->forApplication($application)->ofType($type)->create([
        'original_name' => 'page-1.pdf',
        'page_index' => 0,
    ]);

    $checklist = fetchChecklist($application, $this->staff);
    $transcript = group($checklist, 'transcript');

    expect($transcript['documents'])->toHaveCount(2);
    expect(array_column($transcript['documents'], 'original_name'))->toBe(['page-1.pdf', 'page-2.pdf']);
});

it('flags a required document type with no file as missing', function () {
    ApplicationDocumentType::factory()->required()->create(['code' => 'cccd', 'name' => 'CCCD']);
    ApplicationDocumentType::factory()->create(['code' => 'optional_doc', 'name' => 'Optional']);
    $application = documentApplication($this->campus);

    $checklist = fetchChecklist($application, $this->staff);

    expect($checklist['missing_required'])->toContain('cccd');
    expect($checklist['missing_required'])->not->toContain('optional_doc');
    expect(group($checklist, 'cccd')['is_missing'])->toBeTrue();
});

it('clears the missing flag once a required document is present', function () {
    $type = ApplicationDocumentType::factory()->required()->create(['code' => 'cccd', 'name' => 'CCCD']);
    $application = documentApplication($this->campus);
    ApplicationDocument::factory()->forApplication($application)->ofType($type)->create();

    $checklist = fetchChecklist($application, $this->staff);

    expect($checklist['missing_required'])->not->toContain('cccd');
    expect(group($checklist, 'cccd')['is_missing'])->toBeFalse();
});

it('does not require an international-only document for a domestic applicant', function () {
    ApplicationDocumentType::factory()->internationalRequired()->create(['code' => 'passport', 'name' => 'Passport']);
    $application = documentApplication($this->campus, ['is_international_applicant' => false]);

    $checklist = fetchChecklist($application, $this->staff);

    expect($checklist['missing_required'])->not->toContain('passport');
    expect(group($checklist, 'passport')['required'])->toBeFalse();
});

it('requires an international-only document for an international applicant', function () {
    ApplicationDocumentType::factory()->internationalRequired()->create(['code' => 'passport', 'name' => 'Passport']);
    $application = documentApplication($this->campus, ['is_international_applicant' => true]);

    $checklist = fetchChecklist($application, $this->staff);

    expect($checklist['missing_required'])->toContain('passport');
    expect(group($checklist, 'passport')['required'])->toBeTrue();
});

it('omits inactive catalog types from the checklist', function () {
    ApplicationDocumentType::factory()->inactive()->required()->create(['code' => 'retired', 'name' => 'Retired']);
    $application = documentApplication($this->campus);

    $checklist = fetchChecklist($application, $this->staff);

    expect(group($checklist, 'retired'))->toBeNull();
    expect($checklist['missing_required'])->not->toContain('retired');
});

it('still lists a document whose type is not in the catalog without marking it required', function () {
    $application = documentApplication($this->campus);
    ApplicationDocument::factory()->forApplication($application)->create([
        'file_type_code' => 'unknown_code',
        'file_type_name' => 'Unknown',
    ]);

    $checklist = fetchChecklist($application, $this->staff);
    $unknown = group($checklist, 'unknown_code');

    expect($unknown)->not->toBeNull();
    expect($unknown['documents'])->toHaveCount(1);
    expect($unknown['required'])->toBeFalse();
    expect($unknown['is_missing'])->toBeFalse();
});

it('allows multiple documents to share a type at the database level', function () {
    $application = documentApplication($this->campus);

    ApplicationDocument::factory()->forApplication($application)->create(['file_type_code' => 'transcript']);
    ApplicationDocument::factory()->forApplication($application)->create(['file_type_code' => 'transcript']);

    expect($application->documents()->where('file_type_code', 'transcript')->count())->toBe(2);
});

it('syncs the document-type catalog from the CRM idempotently by code', function () {
    $service = app(ApplicationDocumentTypeSyncService::class);

    $catalog = [
        ['code' => 'cccd', 'name' => 'CCCD', 'type' => 'identity', 'required' => true, 'int_required' => false, 'active' => true, 'order' => 1],
        ['code' => 'passport', 'name' => 'Passport', 'type' => 'identity', 'required' => false, 'int_required' => true, 'active' => true, 'order' => 2],
    ];

    $service->sync($catalog);
    expect(ApplicationDocumentType::count())->toBe(2);

    // Re-syncing the same codes with changed attributes updates in place, no dupes.
    $service->sync([
        ['code' => 'cccd', 'name' => 'Citizen ID', 'required' => true, 'active' => false, 'order' => 5],
    ]);

    expect(ApplicationDocumentType::count())->toBe(2);
    $cccd = ApplicationDocumentType::where('code', 'cccd')->first();
    expect($cccd->name)->toBe('Citizen ID');
    expect($cccd->active)->toBeFalse();
    expect($cccd->order)->toBe(5);
});
