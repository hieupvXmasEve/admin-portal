<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\CurriculumVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleElectiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_api_route_exists(): void
    {
        $user = User::factory()->create();

        // Seed basic data
        $this->artisan('db:seed', ['--class' => 'ComprehensiveEducationSeeder']);

        $curriculumVersion = CurriculumVersion::first();

        $response = $this->actingAs($user)
            ->getJson("/api/curriculum-versions/{$curriculumVersion->id}/available-electives");

        echo "Response Status: " . $response->status() . "\n";
        echo "Response Content: " . $response->content() . "\n";

        // Just check if route exists
        $this->assertTrue($response->status() !== 404, 'Route should exist');
    }
}
