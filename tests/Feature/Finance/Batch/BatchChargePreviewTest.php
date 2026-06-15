<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('returns a preview token and lines for an authorized major preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);
});

it('forbids an EGC preview without generate_egc_finance_charges', function () {
    grantFinance($this->user, ['create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'egc',
            'semester_id' => $this->semester->id,
        ])
        ->assertForbidden();
});