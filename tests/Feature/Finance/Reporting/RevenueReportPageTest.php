<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Batch/helpers.php';
require_once __DIR__.'/RevenueReportTestHelpers.php';

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true, 'start_date' => now()->subMonth()]);
    $this->user = User::factory()->create();
});

function revenueRows(TestResponse $response): Illuminate\Support\Collection
{
    return collect($response->original->getData()['page']['props']['rows']);
}

it('denies users without view_finance_revenue_report', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.revenue.index'))
        ->assertForbidden();
});

it('renders the revenue report for users with the permission', function () {
    grantFinance($this->user, ['view_finance_revenue_report'], $this->campus);
    $student = revStudent($this->campus, $this->semester, 'REV-HTTP-001');
    revBill($student, $this->semester, 2_000_000);

    $this->actingAs($this->user)
        ->get(route('finance.revenue.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Revenue/Index')
            ->has('rows')
            ->has('totals')
            ->has('breakdowns.by_campus')
            ->has('breakdowns.by_fee_type')
            ->has('unattributed')
            ->has('filter_options.semesters')
            ->has('filter_options.campuses')
            ->has('filter_options.fee_types')
            ->has('computed_at')
        );
});

it('rejects an out-of-range semester_ids filter with a validation error', function () {
    grantFinance($this->user, ['view_finance_revenue_report'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.revenue.index', ['semester_ids' => [999999]]))
        ->assertInvalid(['semester_ids.0']);
});

it('narrows rows to the requested campus_id filter', function () {
    grantFinance($this->user, ['view_finance_revenue_report'], $this->campus);

    $inScope = revStudent($this->campus, $this->semester, 'REV-HTTP-IN');
    revBill($inScope, $this->semester, 4_000_000);

    $otherCampus = Campus::factory()->create();
    $outOfScope = revStudent($otherCampus, $this->semester, 'REV-HTTP-OUT');
    revBill($outOfScope, $this->semester, 9_000_000);

    $response = $this->actingAs($this->user)
        ->get(route('finance.revenue.index', ['campus_id' => $this->campus->id]))
        ->assertOk();

    $row = revenueRows($response)->firstWhere('semester_id', $this->semester->id);

    expect($row['net_billed'])->toEqual(4_000_000.0);
});
