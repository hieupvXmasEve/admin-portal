<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
});

/** @return array<string, Illuminate\Routing\Route> */
function merchandiseRoutes(): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name !== null && str_starts_with($name, 'merchandise.')) {
            $routes[$name] = $route;
        }
    }

    return $routes;
}

it('gates every merchandise route with a can middleware and registers the slug', function () {
    $routes = merchandiseRoutes();

    expect($routes)->not->toBeEmpty();

    // A typo'd slug also 403s everyone (no Gate::before), so every gate slug
    // must additionally exist in the permission registry config.
    $registeredSlugs = collect(config('permission.access'))->flatten()->all();

    $ungated = [];
    $unregistered = [];

    foreach ($routes as $name => $route) {
        $slugs = collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => explode(',', substr($middleware, 4))[0]);

        if ($slugs->isEmpty()) {
            $ungated[] = $name;
        }

        foreach ($slugs as $slug) {
            if (! in_array($slug, $registeredSlugs, true)) {
                $unregistered[] = "{$name} => {$slug}";
            }
        }
    }

    expect($ungated)->toBe([]);
    expect($unregistered)->toBe([]);
});

it('rejects a staff user without any merchandise grant on every parameterless GET route', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();

    // Parameterized routes (show/update/archive/...) are covered end-to-end
    // by MerchandiseCrudTest and the cross-campus policy test; this sweeps
    // every route that needs no route-model binding.
    foreach (merchandiseRoutes() as $name => $route) {
        if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }

        $response = $this->actingAs($user)->get(route($name));
        expect($response->getStatusCode())->toBe(403, "Route {$name} expected 403, got {$response->getStatusCode()}");
    }
});
