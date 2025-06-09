<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Docker Environment Integration', function () {
    test('mysql database connection is working', function () {
        // Skip if not using MySQL
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Test requires MySQL database');
        }

        $connection = DB::connection();
        expect($connection->getPdo())->not->toBeNull();
        
        // Test database name
        $databaseName = $connection->getDatabaseName();
        expect($databaseName)->toContain('swinburne');
    });

    test('can perform database transactions', function () {
        DB::beginTransaction();
        
        $user = \App\Models\User::factory()->create(['name' => 'Transaction Test']);
        expect(\App\Models\User::where('name', 'Transaction Test')->count())->toBe(1);
        
        DB::rollBack();
        
        expect(\App\Models\User::where('name', 'Transaction Test')->count())->toBe(0);
    });

    test('redis connection is available when configured', function () {
        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('Test requires Redis cache driver');
        }

        Cache::put('test_key', 'test_value', 60);
        expect(Cache::get('test_key'))->toBe('test_value');
        Cache::forget('test_key');
    });

    test('queue system is functional', function () {
        Queue::fake();
        
        // Test that we can dispatch jobs
        \Illuminate\Foundation\Bus\DispatchesJobs::dispatch(new class {
            public function handle() {
                // Test job
            }
        });
        
        Queue::assertPushed(function ($job) {
            return true;
        });
    });

    test('file storage is writable', function () {
        $testFile = 'test_file.txt';
        $testContent = 'Docker integration test content';
        
        \Storage::disk('local')->put($testFile, $testContent);
        
        expect(\Storage::disk('local')->exists($testFile))->toBeTrue();
        expect(\Storage::disk('local')->get($testFile))->toBe($testContent);
        
        \Storage::disk('local')->delete($testFile);
    });

    test('application can handle concurrent requests', function () {
        $user = \App\Models\User::factory()->create();
        
        // Simulate multiple concurrent requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->actingAs($user)->get('/dashboard');
        }
        
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    });
});

describe('Performance and Resource Usage', function () {
    test('database queries are efficient', function () {
        DB::enableQueryLog();
        
        // Create test data
        $users = \App\Models\User::factory(10)->create();
        
        // Clear query log
        DB::flushQueryLog();
        
        // Perform a query that should be efficient
        $retrievedUsers = \App\Models\User::all();
        
        $queries = DB::getQueryLog();
        
        // Should only require one query
        expect(count($queries))->toBeLessThanOrEqual(1);
        expect($retrievedUsers->count())->toBe(10);
    });

    test('memory usage is reasonable', function () {
        $initialMemory = memory_get_usage();
        
        // Create some test data
        $users = \App\Models\User::factory(100)->create();
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 50MB)
        expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024);
    });

    test('response times are acceptable', function () {
        $user = \App\Models\User::factory()->create();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user)->get('/dashboard');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Response should be under 1 second
        expect($responseTime)->toBeLessThan(1000);
    });
});

describe('Security and Configuration', function () {
    test('debug mode is properly configured', function () {
        $isDebug = config('app.debug');
        $environment = config('app.env');
        
        if ($environment === 'production') {
            expect($isDebug)->toBeFalse();
        } else {
            expect($isDebug)->toBeTrue();
        }
    });

    test('csrf protection is enabled', function () {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);
        
        // Should fail without CSRF token
        $response->assertStatus(419);
    });

    test('sensitive data is not exposed', function () {
        $response = $this->get('/api/health');
        
        $content = $response->getContent();
        
        // Should not contain sensitive information
        expect($content)->not->toContain('password');
        expect($content)->not->toContain('secret');
        expect($content)->not->toContain('key');
    });
});

describe('Application Features', function () {
    test('ziggy routes are generated', function () {
        // Check if Ziggy routes are available
        $response = $this->get('/');
        
        // Should not get a 500 error related to missing routes
        expect($response->status())->not->toBe(500);
    });

    test('inertia responses work correctly', function () {
        $user = \App\Models\User::factory()->create();
        
        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->get('/dashboard');
        
        $response->assertStatus(200)
            ->assertHeader('X-Inertia', 'true');
    });

    test('api endpoints are accessible', function () {
        $response = $this->get('/api/health');
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    });
});
