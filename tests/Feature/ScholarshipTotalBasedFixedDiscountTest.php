<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

const SCHOLARSHIP_TOTAL_TEST_CSRF = 'scholarship-total-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    $campus = Campus::factory()->create();

    session([
        '_token' => SCHOLARSHIP_TOTAL_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);

    $this->user = User::factory()->create();
});

function scholarshipTotalBasedPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'FIXED_TOTAL',
        'name' => 'Fixed Total Scholarship',
        'description' => 'Calculated from total amount and terms.',
        'type' => 'fixed_amount',
        'amount' => 1,
        'total_amount' => 175000000,
        'total_terms' => 9,
        'valid_from' => '2026-01-01',
        'valid_until' => '2027-01-01',
        'is_active' => true,
    ], $overrides);
}

it('creates a fixed scholarship from total amount and total terms', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.create'))
        ->post(route('scholarships.store'), scholarshipTotalBasedPayload());

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $scholarship = ScholarshipDefinition::query()->where('code', 'FIXED_TOTAL')->firstOrFail();

    expect((int) $scholarship->amount)->toBe(19445000)
        ->and((float) $scholarship->total_amount)->toBe(175000000.0)
        ->and($scholarship->total_terms)->toBe(9);
});

it('keeps exact thousand calculated amounts unchanged', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.create'))
        ->post(route('scholarships.store'), scholarshipTotalBasedPayload([
            'code' => 'FIXED_EXACT',
            'total_amount' => 45000000,
            'total_terms' => 9,
        ]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $scholarship = ScholarshipDefinition::query()->where('code', 'FIXED_EXACT')->firstOrFail();

    expect((int) $scholarship->amount)->toBe(5000000);
});

it('rejects fixed scholarships without required calculation fields', function () {
    $payload = scholarshipTotalBasedPayload([
        'code' => 'FIXED_MISSING_TOTALS',
    ]);
    unset($payload['total_amount'], $payload['total_terms']);

    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.create'))
        ->post(route('scholarships.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['total_amount', 'total_terms']);

    expect(ScholarshipDefinition::query()->where('code', 'FIXED_MISSING_TOTALS')->exists())->toBeFalse();
});

it('rejects fixed scholarships with non-positive total terms', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.create'))
        ->post(route('scholarships.store'), scholarshipTotalBasedPayload([
            'code' => 'FIXED_ZERO_TERMS',
            'total_terms' => 0,
        ]));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['total_terms']);

    expect(ScholarshipDefinition::query()->where('code', 'FIXED_ZERO_TERMS')->exists())->toBeFalse();
});

it('updates a fixed scholarship and overrides stale submitted amount', function () {
    $scholarship = ScholarshipDefinition::query()->create([
        'code' => 'FIXED_UPDATE',
        'name' => 'Fixed Update Scholarship',
        'description' => null,
        'type' => 'fixed_amount',
        'amount' => 5000000,
        'total_amount' => 45000000,
        'total_terms' => 9,
        'valid_from' => '2026-01-01',
        'valid_until' => '2027-01-01',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.edit', $scholarship))
        ->put(route('scholarships.update', $scholarship), scholarshipTotalBasedPayload([
            'code' => 'FIXED_UPDATE',
            'name' => 'Fixed Update Scholarship',
            'amount' => 123,
            'total_amount' => 175000000,
            'total_terms' => 9,
        ]));

    $response->assertRedirect(route('scholarships.show', $scholarship));
    $response->assertSessionHasNoErrors();

    $scholarship->refresh();

    expect((int) $scholarship->amount)->toBe(19445000)
        ->and((float) $scholarship->total_amount)->toBe(175000000.0)
        ->and($scholarship->total_terms)->toBe(9);
});

it('clears total calculation fields when a scholarship becomes percentage based', function () {
    $scholarship = ScholarshipDefinition::query()->create([
        'code' => 'FIXED_TO_PERCENT',
        'name' => 'Fixed To Percent Scholarship',
        'description' => null,
        'type' => 'fixed_amount',
        'amount' => 5000000,
        'total_amount' => 45000000,
        'total_terms' => 9,
        'valid_from' => '2026-01-01',
        'valid_until' => '2027-01-01',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-CSRF-TOKEN', SCHOLARSHIP_TOTAL_TEST_CSRF)
        ->from(route('scholarships.edit', $scholarship))
        ->put(route('scholarships.update', $scholarship), scholarshipTotalBasedPayload([
            'code' => 'FIXED_TO_PERCENT',
            'name' => 'Fixed To Percent Scholarship',
            'type' => 'percentage',
            'amount' => 50,
            'total_amount' => 175000000,
            'total_terms' => 9,
        ]));

    $response->assertRedirect(route('scholarships.show', $scholarship));
    $response->assertSessionHasNoErrors();

    $scholarship->refresh();

    expect((int) $scholarship->amount)->toBe(50)
        ->and($scholarship->total_amount)->toBeNull()
        ->and($scholarship->total_terms)->toBeNull();
});
