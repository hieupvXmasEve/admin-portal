<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('redirects guests away from the schedule management page', function (): void {
    get(route('schedules.index'))->assertRedirect(route('login'));
});

it('renders the schedule management page for authenticated users', function (): void {
    actingAs(User::factory()->create());
    session(['current_campus_id' => Campus::factory()->create()->id]);

    get(route('schedules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ClassSchedule/Index'));
});
