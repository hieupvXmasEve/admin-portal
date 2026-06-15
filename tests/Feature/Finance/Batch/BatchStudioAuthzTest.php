<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
});

it('forbids the hub without view_finance_batch_studio', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.hub'))
        ->assertForbidden();
});

it('renders the hub with only the job tiles the operator may run', function () {
    grantFinance($this->user, [
        'view_finance_batch_studio',
        'create_finance_charges',
    ], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.hub'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/BatchStudio/Hub', false)
            ->where('jobs.charge_generation', true)
            ->where('jobs.dng_push', false)
            ->where('jobs.reminder', false)
        );
});

it('renders the charge-generation wizard page for an authorized operator', function () {
    grantFinance($this->user, ['view_finance_batch_studio', 'create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.batch-studio.charges'))
        ->assertInertia(fn (Assert $page) => $page->component('Finance/BatchStudio/ChargeGeneration', false));
});