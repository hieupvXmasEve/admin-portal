<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery;
use App\Modules\AI\Models\AiEvaluationCase;
use App\Modules\AI\Support\BusinessGlossary;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\QueryPlan;
use App\Modules\AI\Support\QueryPlanValidator;
use App\Modules\AI\Support\StaffMetricQuestionDataset;
use App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HN']);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId = null): array {
            if ($userId === $this->authorizedUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics', 'view_finance_reporting', 'view_academic_report'];
            }

            return [];
        });

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('exposes aggregate metric catalog and glossary without executable database details', function () {
    $catalog = app(MetricCatalog::class);
    $glossary = app(BusinessGlossary::class);

    expect($catalog->version())->toBe('metric-catalog:v1')
        ->and($catalog->toolSchemaVersion())->toBe('query_metrics:v1')
        ->and($catalog->metricKeys())->toContain(
            'academic_student_status_count',
            'academic_defer_count',
            'finance_collection_summary',
            'finance_fee_monitor_summary',
            'finance_dng_lifecycle_attention',
        );

    $collectionMetric = $catalog->metric('finance_collection_summary');
    $statusMetric = $catalog->metric('academic_student_status_count');

    expect($collectionMetric)->not->toBeNull()
        ->and($collectionMetric['source_query'])->toBe(ListCollectionProgressQuery::class)
        ->and($collectionMetric['output_shape'])->toBe('aggregate_summary')
        ->and($collectionMetric['required_permission'])->toBe('view_ai_metrics')
        ->and($collectionMetric['required_domain_permission'])->toBe('view_finance_reporting')
        ->and($collectionMetric['allowed_filters'])->toHaveKeys(['semester', 'program_id', 'fee_type', 'balance_state'])
        ->and($collectionMetric['allowed_group_by'])->toContain('program', 'fee_type', 'balance_state', 'aging_bucket')
        ->and($statusMetric['source_query'])->toBe(GetStudentStatusBySemesterQuery::class)
        ->and($statusMetric['required_domain_permission'])->toBe('view_academic_report')
        ->and($statusMetric['allowed_group_by'])->toContain('status', 'program', 'intake_semester');

    $encodedMetric = json_encode($collectionMetric, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($encodedMetric)
        ->not->toContain('select ')
        ->not->toContain(' from ')
        ->not->toContain('students.')
        ->not->toContain('student_ids');

    expect($glossary->metricForPhrase('nợ học phí'))->toBe('finance_collection_summary')
        ->and($glossary->metricForPhrase('bao nhiêu sinh viên defer'))->toBe('academic_defer_count')
        ->and($glossary->filterValueForPhrase('kỳ hiện tại'))->toMatchArray([
            'filter' => 'semester',
            'value' => 'current',
        ]);
});

it('validates and normalizes allowed plans with permission campus scope and source references', function () {
    $semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    $plan = QueryPlan::fromArray([
        'metric' => 'finance_collection_summary',
        'filters' => [
            'semester' => 'current',
            'fee_type' => 'tuition_term',
        ],
        'group_by' => ['program'],
        'options' => [
            'include_rows' => false,
            'limit' => 50,
        ],
    ]);

    $result = app(QueryPlanValidator::class)->validate($plan, $this->authorizedUser, $this->campus);

    expect($result->allowed())->toBeTrue()
        ->and($result->safeErrorCode())->toBeNull()
        ->and($result->toArray())->toMatchArray([
            'allowed' => true,
            'catalog_version' => 'metric-catalog:v1',
            'tool_schema_version' => 'query_metrics:v1',
            'metric' => 'finance_collection_summary',
            'group_by' => ['program'],
            'permission_result' => 'allowed',
            'campus_scope_snapshot' => [
                'campus_ids' => [$this->campus->id],
            ],
            'source_report' => 'finance.reporting.collection-progress',
            'source_reference_policy' => 'report_summary_with_filters',
        ])
        ->and($result->normalizedFilters())->toMatchArray([
            'semester_id' => $semester->id,
            'fee_type' => 'tuition_term',
        ])
        ->and($result->hiddenSections())->toBe([])
        ->and($result->maxRecordLimit())->toBe(500)
        ->and($result->estimatedRecordCount())->toBeLessThanOrEqual(500);
});

it('denies unsafe unsupported unauthorized and cross campus plans before execution', function () {
    $validator = app(QueryPlanValidator::class);

    $rawSqlResult = $validator->validate(QueryPlan::fromArray([
        'metric' => 'finance_collection_summary',
        'filters' => ['semester' => 'current'],
        'group_by' => [],
        'sql' => 'select * from students',
    ]), $this->authorizedUser, $this->campus);

    $unsupportedGroupResult = $validator->validate(QueryPlan::fromArray([
        'metric' => 'academic_defer_count',
        'filters' => ['semester' => 'current'],
        'group_by' => ['student'],
    ]), $this->authorizedUser, $this->campus);

    $unauthorizedResult = $validator->validate(QueryPlan::fromArray([
        'metric' => 'finance_collection_summary',
        'filters' => ['semester' => 'current'],
        'group_by' => [],
    ]), $this->unauthorizedUser, $this->campus);

    $crossCampusResult = $validator->validate(QueryPlan::fromArray([
        'metric' => 'finance_collection_summary',
        'filters' => [
            'semester' => 'current',
            'campus_id' => $this->otherCampus->id,
        ],
        'group_by' => [],
    ]), $this->authorizedUser, $this->campus);

    expect($rawSqlResult->allowed())->toBeFalse()
        ->and($rawSqlResult->safeErrorCode())->toBe('invalid_query_plan_schema')
        ->and($rawSqlResult->permissionResult())->toBe('not_evaluated')
        ->and($unsupportedGroupResult->allowed())->toBeFalse()
        ->and($unsupportedGroupResult->safeErrorCode())->toBe('unsupported_group_by')
        ->and($unauthorizedResult->allowed())->toBeFalse()
        ->and($unauthorizedResult->safeErrorCode())->toBe('forbidden_by_permission')
        ->and($unauthorizedResult->permissionResult())->toBe('denied')
        ->and($crossCampusResult->allowed())->toBeFalse()
        ->and($crossCampusResult->safeErrorCode())->toBe('forbidden_by_campus_scope');
});

it('seeds deterministic staff metric evaluation cases with expected tool calls and source references', function () {
    $cases = app(StaffMetricQuestionDataset::class)->seedBaselineCases();

    expect($cases)->toHaveCount(5)
        ->and(AiEvaluationCase::query()->where('dataset_version', 'metric-catalog-v1')->count())->toBe(5);

    $collectionCase = AiEvaluationCase::query()
        ->where('dataset_version', 'metric-catalog-v1')
        ->where('key', 'finance_collection_summary_by_program')
        ->firstOrFail();

    expect($collectionCase->question)->toContain('outstanding')
        ->and($collectionCase->expected_tool_calls)->toMatchArray([
            [
                'tool' => 'query_metrics',
                'metric' => 'finance_collection_summary',
                'filters' => ['semester' => 'current'],
                'group_by' => ['program'],
            ],
        ])
        ->and($collectionCase->expected_source_references[0])->toMatchArray([
            'source_report' => 'finance.reporting.collection-progress',
            'source_reference_policy' => 'report_summary_with_filters',
        ])
        ->and($collectionCase->expected_answer_properties)->toMatchArray([
            'must_cite_sources' => true,
            'must_include_filters' => true,
            'must_not_fabricate_numbers' => true,
        ]);
});
