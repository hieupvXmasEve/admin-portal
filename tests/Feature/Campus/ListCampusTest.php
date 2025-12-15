<?php

declare(strict_types=1);

namespace Tests\Feature\Campus;

use App\Constants\CampusRoutes;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('returns 200 when accessing index route', function () {
    $this->actingAs($this->user)
        ->withoutMiddleware(['can:view_campus'])
        ->get(route(CampusRoutes::INDEX))
        ->assertOk();
});

// it('renders correct inertia component', function () {
//     Campus::factory()->count(5)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//         );
// });

// it('passes campuses paginator to inertia component', function () {
//     Campus::factory()->count(10)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('campuses.data', 10)
//             ->has('campuses')
//         );
// });

// it('passes filters to inertia component', function () {
//     Campus::factory()->count(5)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, [
//             'search' => 'test',
//             'sort' => 'name',
//             'direction' => 'asc',
//             'per_page' => 20,
//         ]))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('filters', fn (AssertableInertia $filters) => $filters
//                 ->where('search', 'test')
//                 ->where('sort', 'name')
//                 ->where('direction', 'asc')
//                 ->where('per_page', 20)
//             )
//         );
// });

// it('filters campuses by search keyword', function () {
//     Campus::factory()->create(['name' => 'Hanoi Campus']);
//     Campus::factory()->create(['name' => 'Ho Chi Minh Campus']);
//     Campus::factory()->create(['name' => 'Da Nang Campus']);

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['search' => 'Hanoi']))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('campuses.data', 1)
//             ->where('campuses.data.0.name', 'Hanoi Campus')
//         );
// });

// it('sorts campuses by name when sort parameter is provided', function () {
//     Campus::factory()->create(['name' => 'Campus C']);
//     Campus::factory()->create(['name' => 'Campus A']);
//     Campus::factory()->create(['name' => 'Campus B']);

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['sort' => 'name', 'direction' => 'asc']))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->where('campuses.data.0.name', 'Campus A')
//             ->where('campuses.data.1.name', 'Campus B')
//             ->where('campuses.data.2.name', 'Campus C')
//         );
// });

// it('respects per_page parameter', function () {
//     Campus::factory()->count(25)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['per_page' => 10]))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('campuses.data', 10)
//             ->where('campuses.per_page', 10)
//             ->where('campuses.total', 25)
//         );
// });

// it('handles empty campuses list', function () {
//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('campuses.data', 0)
//             ->where('campuses.total', 0)
//         );
// });

// it('handles invalid per_page gracefully', function () {
//     // Note: FormRequest validation may not redirect on GET requests
//     // Instead, invalid values are filtered out or use defaults
//     Campus::factory()->count(5)->create();

//     $response = $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['per_page' => 999]));

//     // Should either redirect with errors or handle gracefully
//     expect($response->status())->toBeIn([200, 302]);
// });

// it('handles invalid sort column gracefully', function () {
//     Campus::factory()->count(5)->create();

//     $response = $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['sort' => 'invalid_column']));

//     // Should either redirect with errors or handle gracefully
//     expect($response->status())->toBeIn([200, 302]);
// });

// it('handles invalid direction gracefully', function () {
//     Campus::factory()->count(5)->create();

//     $response = $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, ['direction' => 'invalid']));

//     // Should either redirect with errors or handle gracefully
//     expect($response->status())->toBeIn([200, 302]);
// });

// it('validation passes with valid parameters', function () {
//     Campus::factory()->count(5)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX, [
//             'search' => 'test',
//             'sort' => 'name',
//             'direction' => 'asc',
//             'per_page' => 25,
//         ]))
//         ->assertSessionHasNoErrors()
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//         );
// });

// it('validation passes with null optional parameters', function () {
//     Campus::factory()->count(5)->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX))
//         ->assertSessionHasNoErrors()
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('filters', fn (AssertableInertia $filters) => $filters
//                 ->where('search', null)
//                 ->where('sort', null)
//                 ->where('direction', null)
//                 ->where('per_page', null)
//             )
//         );
// });

// it('includes buildings_count and users_count in campus data', function () {
//     $campus = Campus::factory()->create();

//     $this->actingAs($this->user)
//         ->withoutMiddleware(['can:view_campus'])
//         ->get(route(CampusRoutes::INDEX))
//         ->assertInertia(fn (AssertableInertia $page) => $page
//             ->component('campuses/Index')
//             ->has('campuses.data', 1)
//             ->has('campuses.data.0', fn (AssertableInertia $campusData) => $campusData
//                 ->has('buildings_count')
//                 ->has('users_count')
//             )
//         );
// });
