<?php

declare(strict_types=1);

use App\Models\ApplicationGuardian;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Modules\Upload\Models\ApplicationDocument;
use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Services\Admissions\ApplicationBackfillService;
use App\Services\Admissions\BackfillReport;
use App\Services\Admissions\LegacyColumnDropGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const FILE_TYPES_FIXTURE = __DIR__.'/../../Fixtures/Admissions/file_types.csv';
const ADMISSION_FILES_FIXTURE = __DIR__.'/../../Fixtures/Admissions/admission_files.csv';

/**
 * Create an application and force exact legacy-column values (independent of the
 * factory, which no longer populates the legacy columns).
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $legacy
 */
function legacyApplication(array $attributes, array $legacy = []): StudentApplication
{
    $application = StudentApplication::factory()->create(array_merge([
        'status' => 'approved',
        'student_id' => null,
    ], $attributes));

    $defaults = [
        'parent_phone' => null,
        'parent_email' => null,
        'submitted_photo' => null,
        'submitted_cccd' => null,
        'submitted_ccta' => null,
        'submitted_tn_translate' => null,
        'submitted_hb_translate' => null,
        'submitted_other' => null,
        'submitted_insurance_card' => null,
        'submitted_exemption_gc' => null,
    ];

    DB::table('student_applications')->where('id', $application->id)->update(array_merge($defaults, $legacy));

    return $application->fresh();
}

function runBackfill(bool $dryRun): BackfillReport
{
    return app(ApplicationBackfillService::class)->run(FILE_TYPES_FIXTURE, ADMISSION_FILES_FIXTURE, $dryRun);
}

beforeEach(function (): void {
    // MATCH001 — converted, has parent data, present in the CRM export (3 files).
    $this->match1 = legacyApplication(
        ['student_code' => 'MATCH001', 'student_id' => Student::factory()->create([
            'intake' => 0,
            'intake_semester_id' => Semester::factory()->create()->id,
        ])->id],
        ['parent_phone' => '0900000001', 'parent_email' => 'parent1@example.com'],
    );

    // MATCH002 — unconverted, no parent data, present in the CRM export (1 file).
    $this->match2 = legacyApplication(['student_code' => 'MATCH002']);

    // LEGACY001 — unconverted, has parent data, NOT in the export; two submitted_* URLs.
    $this->legacy1 = legacyApplication(
        ['student_code' => 'LEGACY001'],
        [
            'parent_phone' => '0900000002',
            'parent_email' => 'parent2@example.com',
            'submitted_cccd' => 'https://drive.google.com/file/d/LEGACY_CCCD/preview',
            'submitted_hb_translate' => 'https://drive.google.com/file/d/LEGACY_HB/preview',
        ],
    );

    // NODOCS001 — unconverted, no parent data, no documents anywhere.
    $this->noDocs = legacyApplication(['student_code' => 'NODOCS001']);
});

it('reports planned changes on a dry-run without writing anything', function (): void {
    $report = runBackfill(dryRun: true);

    expect($report->applied)->toBeFalse()
        ->and($report->applicationsTotal)->toBe(4)
        ->and($report->catalogSynced)->toBe(4)
        ->and($report->guardiansCreated)->toBe(2)
        ->and($report->documentsFromCsv)->toBe(4)
        ->and($report->documentsFromLegacy)->toBe(2)
        ->and($report->statusToEnrolled)->toBe(1)
        ->and($report->statusToPending)->toBe(3);

    // Nothing persisted.
    expect(ApplicationDocumentType::count())->toBe(0)
        ->and(ApplicationGuardian::count())->toBe(0)
        ->and(ApplicationDocument::count())->toBe(0)
        ->and(StudentApplication::where('status', 'approved')->count())->toBe(4);
});

it('seeds the document-type catalog from the file-types CSV', function (): void {
    runBackfill(dryRun: false);

    expect(ApplicationDocumentType::count())->toBe(4);

    $transcript = ApplicationDocumentType::where('code', 'transcript')->first();
    expect($transcript)->not->toBeNull()
        ->and($transcript->required)->toBeTrue()
        ->and($transcript->int_required)->toBeTrue()
        ->and($transcript->name)->toBe('Bảng điểm/học bạ');
});

it('imports CRM-export documents for matched applications, multiple per type', function (): void {
    runBackfill(dryRun: false);

    $docs = $this->match1->documents()->orderBy('crm_file_id')->get();

    expect($docs)->toHaveCount(3);

    $idCard = $docs->firstWhere('crm_file_id', '9001');
    expect($idCard->file_type_code)->toBe('id_card_front')
        ->and($idCard->file_type_name)->toBe('CCCD (mặt trước)')
        ->and($idCard->link)->toBe('https://drive.google.com/file/d/GG_FRONT_1/preview')
        ->and($idCard->mime_type)->toBe('image/jpeg')
        ->and($idCard->size)->toBe(1000);

    $transcriptPages = $docs->where('file_type_code', 'transcript')->sortBy('page_index')->values();
    expect($transcriptPages)->toHaveCount(2)
        ->and($transcriptPages[0]->page_index)->toBe(0)
        ->and($transcriptPages[1]->page_index)->toBe(1);
});

it('falls back to submitted_* URLs for applications absent from the export', function (): void {
    runBackfill(dryRun: false);

    $docs = $this->legacy1->documents()->get();

    expect($docs)->toHaveCount(2)
        ->and($docs->pluck('file_type_code')->sort()->values()->all())->toBe(['id_card_front', 'transcript'])
        ->and($docs->pluck('crm_file_id')->unique()->all())->toBe([null]);

    $cccd = $docs->firstWhere('file_type_code', 'id_card_front');
    expect($cccd->link)->toBe('https://drive.google.com/file/d/LEGACY_CCCD/preview');
});

it('moves the legacy parent pair into a single primary guardian', function (): void {
    runBackfill(dryRun: false);

    $guardians = $this->match1->guardians()->get();

    expect($guardians)->toHaveCount(1);

    $guardian = $guardians->first();
    expect($guardian->is_primary)->toBeTrue()
        ->and($guardian->full_name)->toBe(ApplicationBackfillService::LEGACY_GUARDIAN_NAME)
        ->and($guardian->phone)->toBe('0900000001')
        ->and($guardian->email)->toBe('parent1@example.com');

    // Applications without parent data get no guardian.
    expect($this->match2->guardians()->count())->toBe(0)
        ->and($this->noDocs->guardians()->count())->toBe(0);
});

it('maps converted applications to enrolled and the rest to pending', function (): void {
    runBackfill(dryRun: false);

    expect($this->match1->fresh()->status)->toBe(StudentApplication::STATUS_ENROLLED)
        ->and($this->match2->fresh()->status)->toBe(StudentApplication::STATUS_PENDING)
        ->and($this->legacy1->fresh()->status)->toBe(StudentApplication::STATUS_PENDING)
        ->and($this->noDocs->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('preserves an existing rejected status rather than flipping it to pending', function (): void {
    $rejected = legacyApplication([
        'student_code' => 'REJECTED01',
        'status' => StudentApplication::STATUS_REJECTED,
    ]);

    runBackfill(dryRun: false);

    expect($rejected->fresh()->status)->toBe(StudentApplication::STATUS_REJECTED);
});

it('preserves identity and contact data on the slim core', function (): void {
    $before = DB::table('student_applications')->where('id', $this->match1->id)
        ->first(['national_id', 'email', 'phone', 'address']);

    runBackfill(dryRun: false);

    $after = $this->match1->fresh();
    expect($after->national_id)->toBe($before->national_id)
        ->and($after->email)->toBe($before->email)
        ->and($after->phone)->toBe($before->phone)
        ->and($after->address)->toBe($before->address);
});

it('is idempotent — a second apply run changes nothing further', function (): void {
    runBackfill(dryRun: false);

    $guardiansBefore = ApplicationGuardian::count();
    $documentsBefore = ApplicationDocument::count();

    $second = runBackfill(dryRun: false);

    expect($second->guardiansCreated)->toBe(0)
        ->and($second->documentsFromCsv)->toBe(0)
        ->and($second->documentsFromLegacy)->toBe(0)
        ->and($second->statusToEnrolled)->toBe(0)
        ->and($second->statusToPending)->toBe(0)
        ->and(ApplicationGuardian::count())->toBe($guardiansBefore)
        ->and(ApplicationDocument::count())->toBe($documentsBefore);
});

it('reconciles counts: no parent or document data is lost', function (): void {
    $report = runBackfill(dryRun: false);

    // Every application that had parent data produced exactly one guardian.
    expect(ApplicationGuardian::count())->toBe($report->applicationsWithParentData)
        ->and($report->applicationsWithParentData)->toBe(2);

    // Every persisted document came from one of the two sources.
    expect(ApplicationDocument::count())->toBe($report->documentsCreated())
        ->and($report->documentsCreated())->toBe(6);
});

it('artisan command defaults to a read-only dry-run', function (): void {
    test()->artisan('applications:backfill', [
        '--file-types' => FILE_TYPES_FIXTURE,
        '--admission-files' => ADMISSION_FILES_FIXTURE,
    ])
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect(ApplicationGuardian::count())->toBe(0)
        ->and(ApplicationDocument::count())->toBe(0);
});

it('artisan command --apply commits the backfill', function (): void {
    test()->artisan('applications:backfill', [
        '--apply' => true,
        '--file-types' => FILE_TYPES_FIXTURE,
        '--admission-files' => ADMISSION_FILES_FIXTURE,
    ])->assertSuccessful();

    expect(ApplicationGuardian::count())->toBe(2)
        ->and(ApplicationDocument::count())->toBe(6)
        ->and($this->match1->fresh()->status)->toBe(StudentApplication::STATUS_ENROLLED);
});

it('the drop guard refuses while applications exist but the target tables are empty', function (): void {
    // Applications exist, no guardians/documents → backfill clearly not run.
    expect(fn () => LegacyColumnDropGuard::ensureBackfillComplete())
        ->toThrow(RuntimeException::class, 'backfill has not run');
});

it('the drop guard passes once the backfill has populated the target tables', function (): void {
    runBackfill(dryRun: false);

    LegacyColumnDropGuard::ensureBackfillComplete();

    // Reaching here without throwing is the assertion.
    expect(true)->toBeTrue();
});
