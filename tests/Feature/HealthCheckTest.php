<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Health Check Endpoints', function () {
    test('api health endpoint returns success', function () {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'version' => '1.0.0'
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version'
            ]);
    });

    test('nginx health endpoint returns success', function () {
        $response = $this->get('/health');

        $response->assertStatus(200);
    });

    test('application homepage is accessible', function () {
        // Create a user for authentication
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(302); // Should redirect to dashboard
    });

    test('dashboard is accessible for authenticated users', function () {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    });
});

describe('Database Connectivity', function () {
    test('database connection is working', function () {
        // Test basic database connectivity
        expect(\DB::connection()->getPdo())->not->toBeNull();
    });

    test('migrations are up to date', function () {
        // Check if we can query the migrations table
        $migrations = \DB::table('migrations')->count();
        expect($migrations)->toBeGreaterThan(0);
    });

    test('can create and retrieve test data', function () {
        $user = \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');

        // Verify data persists
        $retrievedUser = \App\Models\User::find($user->id);
        expect($retrievedUser->name)->toBe('Test User');
    });
});

describe('Environment Configuration', function () {
    test('app environment is properly configured', function () {
        expect(config('app.env'))->toBeIn(['testing', 'local']);
        expect(config('app.debug'))->toBeTrue();
    });

    test('database configuration is correct', function () {
        $dbConfig = config('database.connections.' . config('database.default'));
        
        expect($dbConfig['driver'])->toBeIn(['mysql', 'sqlite']);
        expect($dbConfig['database'])->not->toBeEmpty();
    });

    test('cache configuration is appropriate for testing', function () {
        $cacheDriver = config('cache.default');
        expect($cacheDriver)->toBeIn(['array', 'file', 'redis']);
    });
});
