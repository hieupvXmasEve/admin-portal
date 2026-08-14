<?php

declare(strict_types=1);

use App\Exports\StudentApplicationExport;
use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\ExportApplicationsAction;
use App\Modules\Admissions\Http\Requests\Admissions\ExportApplicationsRequest;
use App\Modules\Admissions\Http\Requests\Admissions\ListApplicationsRequest;
use App\Modules\Admissions\Models\ApplicationAcademicScore;
use App\Modules\Admissions\Queries\ListApplicationsQuery;
use App\Modules\Admissions\Support\StudentApplicationSortColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

function baseListFilters(array $overrides = []): array
{
    return array_merge([
        'search' => null, 'status' => null, 'intake' => null, 'per_page' => 15,
        'sort' => 'created_at', 'direction' => 'desc',
        'gender' => null, 'ethnicity' => null, 'religion' => null, 'crm_major' => null,
        'scholarship' => null, 'pathway_gateway' => null, 'graduation_year' => null,
        'gpa_type' => null, 'school' => null, 'province' => null, 'birth_place' => null,
        'english_test_type' => null, 'synced' => null,
        'gpa_min' => null, 'gpa_max' => null, 'overall_min' => null, 'overall_max' => null,
        'paid_min' => null, 'paid_max' => null,
    ], $overrides);
}

it('sort allow-list is real student_applications columns and identical on both FormRequests', function () {
    foreach (StudentApplicationSortColumns::ALLOWED as $column) {
        expect(Schema::hasColumn('student_applications', $column))->toBeTrue("`$column` is not a real column");
    }

    $listRule = (new ListApplicationsRequest)->rules()['sort'];
    $exportRule = (new ExportApplicationsRequest)->rules()['sort'];

    expect($listRule)->toBe($exportRule);
});

it('rejects an out-of-allow-list sort with 422 on both requests', function () {
    $listRequest = ListApplicationsRequest::create('/x', 'GET', ['sort' => 'not_a_column']);
    $validator = validator($listRequest->all(), $listRequest->rules());
    expect($validator->fails())->toBeTrue();

    $exportRequest = ExportApplicationsRequest::create('/x', 'GET', ['format' => 'xlsx', 'scope' => 'filtered', 'sort' => 'not_a_column']);
    $exportValidator = validator($exportRequest->all(), $exportRequest->rules());
    expect($exportValidator->fails())->toBeTrue();
});

it('returns zero rows for a null campus in both the list query and the export action', function () {
    Campus::factory()->create(['code' => 'HCM']);
    StudentApplication::factory()->pending()->create(['campus_code' => 'HCM']);

    $listResult = app(ListApplicationsQuery::class)->handle(baseListFilters(), null);
    expect($listResult->items())->toBeEmpty();

    Carbon::setTestNow(Carbon::parse('2026-06-28 10:00:00'));
    Excel::fake();

    ExportApplicationsAction::run([
        'format' => 'xlsx', 'search' => null, 'status' => null,
        'campus_code' => null, 'intake' => null, 'sort' => 'created_at', 'direction' => 'desc',
        'current_campus_code' => null,
    ]);

    // A null campus fails closed: the export's underlying query is empty, not
    // "every campus" — assert the actual row count, not just that a download happened.
    Excel::assertDownloaded(
        'student_applications_2026-06-28_10-00-00.xlsx',
        fn (StudentApplicationExport $export) => $export->query()->count() === 0,
    );

    Carbon::setTestNow();
});

it('filters by each group field returning only matching rows', function () {
    $campus = Campus::factory()->create();
    $match = StudentApplication::factory()->pending()->create([
        'campus_code' => $campus->code, 'gender' => 'female', 'province' => 'Hà Nội',
        'school' => 'THPT Chu Văn An', 'gpa' => 8.5, 'overall' => 7.0,
    ]);
    $miss = StudentApplication::factory()->pending()->create([
        'campus_code' => $campus->code, 'gender' => 'male', 'province' => 'Hồ Chí Minh',
        'school' => 'THPT Lê Quý Đôn', 'gpa' => 5.0, 'overall' => 5.0,
    ]);

    $byGender = app(ListApplicationsQuery::class)->handle(baseListFilters(['gender' => 'female']), $campus->code);
    expect(collect($byGender->items())->pluck('id')->all())->toBe([$match->id]);

    $byProvince = app(ListApplicationsQuery::class)->handle(baseListFilters(['province' => 'Hà Nội']), $campus->code);
    expect(collect($byProvince->items())->pluck('id')->all())->toBe([$match->id]);

    $bySchool = app(ListApplicationsQuery::class)->handle(baseListFilters(['school' => 'Chu Văn An']), $campus->code);
    expect(collect($bySchool->items())->pluck('id')->all())->toBe([$match->id]);

    expect($miss->id)->not->toBe($match->id);
});

it('filters by every remaining select field (ListApplicationsQuery::SELECT_FILTERS)', function () {
    $campus = Campus::factory()->create();
    $selectFields = ['ethnicity', 'religion', 'crm_major', 'scholarship', 'pathway_gateway', 'graduation_year', 'gpa_type', 'english_test_type'];

    foreach ($selectFields as $field) {
        // graduation_year is a short column; keep values narrow enough for every field.
        $matchValue = $field === 'graduation_year' ? '2024' : 'match';
        $otherValue = $field === 'graduation_year' ? '2023' : 'other';

        $match = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, $field => $matchValue]);
        StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, $field => $otherValue]);

        $result = app(ListApplicationsQuery::class)->handle(baseListFilters([$field => $matchValue]), $campus->code);

        expect(collect($result->items())->pluck('id')->all())->toBe([$match->id], "field: $field");
    }
});

it('filters by synced / not_synced against last_synced_at', function () {
    $campus = Campus::factory()->create();
    $synced = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'last_synced_at' => now()]);
    $notSynced = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'last_synced_at' => null]);

    $syncedResult = app(ListApplicationsQuery::class)->handle(baseListFilters(['synced' => 'synced']), $campus->code);
    expect(collect($syncedResult->items())->pluck('id')->all())->toBe([$synced->id]);

    $notSyncedResult = app(ListApplicationsQuery::class)->handle(baseListFilters(['synced' => 'not_synced']), $campus->code);
    expect(collect($notSyncedResult->items())->pluck('id')->all())->toBe([$notSynced->id]);
});

it('filters by the crm_paid_amount range', function () {
    $campus = Campus::factory()->create();
    $inRange = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'crm_paid_amount' => 5_000_000]);
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'crm_paid_amount' => 1_000_000]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['paid_min' => 3_000_000]), $campus->code);

    expect(collect($result->items())->pluck('id')->all())->toBe([$inRange->id]);
});

it('composes campus scope, status, and a numeric range filter as an intersection', function () {
    $campus = Campus::factory()->create();
    $other = Campus::factory()->create();
    $target = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'gpa' => 9.0]);
    StudentApplication::factory()->rejected()->create(['campus_code' => $campus->code, 'gpa' => 9.0]); // wrong status
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'gpa' => 3.0]); // out of range
    StudentApplication::factory()->pending()->create(['campus_code' => $other->code, 'gpa' => 9.0]); // wrong campus

    $result = app(ListApplicationsQuery::class)->handle(
        baseListFilters(['status' => 'pending', 'gpa_min' => 8.0]),
        $campus->code,
    );

    expect(collect($result->items())->pluck('id')->all())->toBe([$target->id]);
});

it('escapes like wildcards so a literal percent does not match every row', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'school' => 'Any School']);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['school' => '%']), $campus->code);

    expect($result->items())->toBeEmpty();
});

it('excludes zero-value gpa/overall rows from a range filter (D12)', function () {
    $campus = Campus::factory()->create();
    $zero = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'gpa' => 0.0]);
    $real = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'gpa' => 6.0]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['gpa_min' => 0]), $campus->code);

    $ids = collect($result->items())->pluck('id')->all();
    expect($ids)->not->toContain($zero->id)
        ->and($ids)->toContain($real->id);
});

it('returns an empty result set when gpa_min exceeds gpa_max', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'gpa' => 7.0]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['gpa_min' => 8.0, 'gpa_max' => 5.0]), $campus->code);

    expect($result->items())->toBeEmpty();
});

it('a data-derived filter value matching nothing returns an empty set, not an error', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'province' => 'Hà Nội']);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['province' => 'Nonexistent Province']), $campus->code);

    expect($result->items())->toBeEmpty();
});

it('filterOptions does not leak a value that only exists at another campus', function () {
    $campus = Campus::factory()->create();
    $other = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'province' => 'Hà Nội']);
    StudentApplication::factory()->pending()->create(['campus_code' => $other->code, 'province' => 'Only At Other Campus']);

    $options = app(ListApplicationsQuery::class)->filterOptions($campus->code);

    expect($options['province']->all())->toContain('Hà Nội')
        ->and($options['province']->all())->not->toContain('Only At Other Campus');
});

it('filterOptions returns empty lists for a null campus rather than every campus', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'province' => 'Hà Nội']);

    $options = app(ListApplicationsQuery::class)->filterOptions(null);

    expect($options['province']->all())->toBe([]);
});

it('returns the full campus set when no filter parameters are given', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->count(3)->create(['campus_code' => $campus->code]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);

    expect($result->total())->toBe(3);
});

it('a stale status=all URL still returns every status (no deploy-skew break)', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);
    StudentApplication::factory()->rejected()->create(['campus_code' => $campus->code]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(['status' => 'all']), $campus->code);

    expect($result->total())->toBe(2);
});

it('searching a student code returns the same row from the list query and the export action', function () {
    $campus = Campus::factory()->create();
    $target = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'student_code' => 'S9998887']);
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'student_code' => 'S0000001']);

    $listResult = app(ListApplicationsQuery::class)->handle(baseListFilters(['search' => 'S9998887']), $campus->code);
    expect(collect($listResult->items())->pluck('id')->all())->toBe([$target->id]);

    $query = StudentApplication::query()->where('campus_code', $campus->code);
    app(ListApplicationsQuery::class)->scopeFilters($query, baseListFilters(['search' => 'S9998887']));
    expect($query->pluck('id')->all())->toBe([$target->id]);
});

it('export honors the same advanced filters as the list, with no scope param', function () {
    $campus = Campus::factory()->create();
    $match = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'province' => 'Hà Nội']);
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code, 'province' => 'Hồ Chí Minh']);

    expect((new ExportApplicationsRequest)->rules())->not->toHaveKey('scope');

    $query = StudentApplication::query()->where('campus_code', $campus->code);
    app(ListApplicationsQuery::class)->scopeFilters($query, baseListFilters(['province' => 'Hà Nội']));

    expect($query->pluck('id')->all())->toBe([$match->id]);
});

it('row payload contains every ship-list field and none of the excluded ones', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);
    $row = $result->items()[0];

    $shipList = [
        'crm_campus', 'crm_major', 'province', 'school', 'gpa', 'gpa_type', 'last_synced_at',
        'pathway_gateway', 'nationality', 'crm_paid_amount', 'id_card_place_of_issue', 'scholarship',
        'religion', 'permanent_address', 'graduation_year', 'birth_place', 'uu_dai_gc',
        'gender', 'ethnicity', 'address', 'english_test_type', 'overall',
        // Full CRM parity, added 2026-08-14: previously deliberately excluded
        // for near-zero fill, now shown per explicit user request.
        'new_province', 'new_street', 'new_ward',
        'birth_day', 'birth_month', 'birth_year', 'exam_date',
        'listening', 'reading', 'writing', 'speaking',
        'registration_form', 'academic_scores',
        'father_guardian_name', 'father_guardian_phone', 'mother_guardian_name', 'mother_guardian_phone',
    ];
    foreach ($shipList as $field) {
        expect($row)->toHaveKey($field);
    }

    $excluded = ['intended_specialization', 'sut_id', 'study_link_status'];
    foreach ($excluded as $field) {
        expect($row)->not->toHaveKey($field);
    }
});

it('row payload carries father and mother guardians separately, not just the primary', function () {
    $campus = Campus::factory()->create();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);
    ApplicationGuardian::factory()->forApplication($application)->create([
        'relationship' => 'mother', 'full_name' => 'Mother Name', 'phone' => '0900000001', 'is_primary' => true,
    ]);
    ApplicationGuardian::factory()->forApplication($application)->create([
        'relationship' => 'father', 'full_name' => 'Father Name', 'phone' => '0900000002', 'is_primary' => false,
    ]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);
    $row = $result->items()[0];

    expect($row['primary_guardian_name'])->toBe('Mother Name')
        ->and($row['mother_guardian_name'])->toBe('Mother Name')
        ->and($row['mother_guardian_phone'])->toBe('0900000001')
        ->and($row['father_guardian_name'])->toBe('Father Name')
        ->and($row['father_guardian_phone'])->toBe('0900000002');
});

it('row payload carries academic scores keyed by subject_code, absent when not reported', function () {
    $campus = Campus::factory()->create();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);
    ApplicationAcademicScore::factory()->forApplication($application)->create(['subject_code' => 'toan', 'score' => 8.5]);
    ApplicationAcademicScore::factory()->forApplication($application)->create(['subject_code' => 'van', 'score' => 7.0]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);
    $row = $result->items()[0];

    expect((float) $row['academic_scores']['toan'])->toBe(8.5)
        ->and((float) $row['academic_scores']['van'])->toBe(7.0)
        ->and($row['academic_scores'])->not->toHaveKey('ly');
});

it('row payload carries the primary guardian, ignoring non-primary guardians', function () {
    $campus = Campus::factory()->create();
    $application = StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);
    ApplicationGuardian::factory()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Trần Thị Mai',
        'relationship' => 'mother',
        'phone' => '0911222333',
        'is_primary' => false,
    ]);
    ApplicationGuardian::factory()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Phạm Đăng Khánh',
        'relationship' => 'father',
        'phone' => '0933667888',
        'is_primary' => true,
    ]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);
    $row = $result->items()[0];

    expect($row['primary_guardian_name'])->toBe('Phạm Đăng Khánh')
        ->and($row['primary_guardian_relationship'])->toBe('father')
        ->and($row['primary_guardian_phone'])->toBe('0933667888');
});

it('row payload has null guardian fields when the application has no guardians', function () {
    $campus = Campus::factory()->create();
    StudentApplication::factory()->pending()->create(['campus_code' => $campus->code]);

    $result = app(ListApplicationsQuery::class)->handle(baseListFilters(), $campus->code);
    $row = $result->items()[0];

    expect($row['primary_guardian_name'])->toBeNull()
        ->and($row['primary_guardian_relationship'])->toBeNull()
        ->and($row['primary_guardian_phone'])->toBeNull();
});
