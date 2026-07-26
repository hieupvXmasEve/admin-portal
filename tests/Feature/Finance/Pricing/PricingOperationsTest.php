<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id, '_token' => 'pricing-ops-csrf']);
    app()->singleton('campus', fn () => $this->campus);

    $this->grantPricing = function (array $permissions): void {
        $mock = Mockery::mock(CampusPermissionReader::class);
        $mock->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
        app()->instance(CampusPermissionReader::class, $mock);
    };
});

it('forbids pricing operations index without view permission', function (): void {
    ($this->grantPricing)(['view_finance_charges']);

    actingAs($this->user)
        ->get(route('finance.pricing-operations.index'))
        ->assertForbidden();
});

it('lists pricing rules and shows coverage warnings for catalog types without usable active rules', function (): void {
    ($this->grantPricing)(['view_finance_pricing_operations']);

    // Currently effective active rule for retake only — exam_resit still uncovered.
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake',
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    // Active but not yet effective — still counts as uncovered for intake today.
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:future',
        'description' => 'Future only',
        'is_active' => true,
        'effective_from' => now()->addWeek(),
    ]);

    $response = actingAs($this->user)->get(route('finance.pricing-operations.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/PricingOperations/Index')
        ->has('rules.data', 2)
        ->has('coverage_warnings')
        ->where('coverage_warnings', function ($warnings): bool {
            $types = collect($warnings)->pluck('obligation_type')->all();

            return in_array(FinanceCharge::TYPE_EXAM_RESIT_FEE, $types, true)
                && ! in_array(FinanceCharge::TYPE_RETAKE_FEE, $types, true);
        })
    );
});

it('creates an immutable pricing rule version and can activate or deactivate it', function (): void {
    ($this->grantPricing)([
        'view_finance_pricing_operations',
        'manage_finance_pricing_operations',
    ]);

    actingAs($this->user)
        ->post(route('finance.pricing-operations.store'), [
            '_token' => 'pricing-ops-csrf',
            'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
            'amount' => 1_600_000,
            'currency' => 'VND',
            'rule_version' => 'retake_fee:v2',
            'description' => 'New retake price',
            'facts_match_json' => '',
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ])
        ->assertRedirect();

    $item = FinancePricingCatalogItem::query()->where('rule_version', 'retake_fee:v2')->firstOrFail();

    expect((float) $item->amount)->toBe(1_600_000.0)
        ->and($item->is_active)->toBeTrue()
        ->and($item->obligation_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE);

    // Historical amount is not editable via any update route — only is_active toggles.
    actingAs($this->user)
        ->post(route('finance.pricing-operations.deactivate', $item), ['_token' => 'pricing-ops-csrf'])
        ->assertRedirect();

    expect($item->refresh()->is_active)->toBeFalse()
        ->and((float) $item->amount)->toBe(1_600_000.0);

    actingAs($this->user)
        ->post(route('finance.pricing-operations.activate', $item), ['_token' => 'pricing-ops-csrf'])
        ->assertRedirect();

    expect($item->refresh()->is_active)->toBeTrue();
});

it('rejects creating a pricing rule for types that do not use the catalog', function (): void {
    ($this->grantPricing)([
        'view_finance_pricing_operations',
        'manage_finance_pricing_operations',
    ]);

    actingAs($this->user)
        ->from(route('finance.pricing-operations.index'))
        ->post(route('finance.pricing-operations.store'), [
            '_token' => 'pricing-ops-csrf',
            'obligation_type' => FinanceCharge::TYPE_MANUAL_FEE,
            'amount' => 100_000,
            'currency' => 'VND',
            'rule_version' => 'manual_fee:v1',
        ])
        ->assertSessionHasErrors('obligation_type');
});

it('seeds baseline pricing rows idempotently and clears coverage warnings', function (): void {
    ($this->grantPricing)(['view_finance_pricing_operations']);

    $this->artisan('finance:seed-pricing-catalog')
        ->assertSuccessful();

    expect(FinancePricingCatalogItem::query()->count())->toBe(2);

    $this->artisan('finance:seed-pricing-catalog')
        ->assertSuccessful();

    expect(FinancePricingCatalogItem::query()->count())->toBe(2);

    actingAs($this->user)
        ->get(route('finance.pricing-operations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/PricingOperations/Index')
            ->where('coverage_warnings', [])
        );
});

it('prevents debit intake when catalog-priced type has no active rule, then succeeds after seeding', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    $intake = new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-PRICING-OPS-001',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Retake without pricing rule',
        ],
    );

    $contract = app(FinanceIntakeContract::class);

    expect(fn () => $contract->request($intake))
        ->toThrow(RuntimeException::class, 'No active Finance pricing catalog item');

    expect(FinanceObligation::query()->count())->toBe(0);

    $this->artisan('finance:seed-pricing-catalog')->assertSuccessful();

    $result = $contract->request($intake);

    $obligation = FinanceObligation::query()->findOrFail($result->finance_obligation_id);

    expect((float) $obligation->amount)->toBe(1_500_000.0)
        ->and($obligation->pricing_rule_version)->toBe('retake_fee:v1')
        ->and($obligation->pricing_snapshot['catalog_rule_version'])->toBe('retake_fee:v1');
});

it('stamps the selected active effective rule version into accepted obligations', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    // Older inactive version
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 500_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v0',
        'description' => 'Retired',
        'is_active' => false,
        'effective_from' => now()->subYear(),
    ]);

    // Older active version still effective
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 700_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Older active',
        'is_active' => true,
        'effective_from' => now()->subMonths(2),
    ]);

    // Newer active version wins via orderByDesc(effective_from)
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 800_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v2',
        'description' => 'Current',
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'exam_resit_attempt',
        source_ref: 'RESIT-PRICING-OPS-001',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_EXAM_RESIT_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Resit with active catalog version',
        ],
    ));

    $obligation = FinanceObligation::query()->findOrFail($result->finance_obligation_id);

    expect((float) $obligation->amount)->toBe(800_000.0)
        ->and($obligation->pricing_rule_version)->toBe('exam_resit_fee:v2');
});

it('accepts raw facts_match JSON on create', function (): void {
    ($this->grantPricing)([
        'view_finance_pricing_operations',
        'manage_finance_pricing_operations',
    ]);

    actingAs($this->user)
        ->post(route('finance.pricing-operations.store'), [
            '_token' => 'pricing-ops-csrf',
            'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
            'amount' => 1_700_000,
            'currency' => 'VND',
            'rule_version' => 'retake_fee:facts-v1',
            'facts_match_json' => '{"campus_code":"HN"}',
            'is_active' => true,
        ])
        ->assertRedirect();

    $item = FinancePricingCatalogItem::query()->where('rule_version', 'retake_fee:facts-v1')->firstOrFail();

    expect($item->facts_match)->toBe(['campus_code' => 'HN']);
});
