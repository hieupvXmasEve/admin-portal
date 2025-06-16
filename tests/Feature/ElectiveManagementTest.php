<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnit;
use App\Models\Unit;
use App\Models\CurriculumUnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectiveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CurriculumVersion $curriculumVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create campus and associate with user to bypass campus middleware
        $campus = \App\Models\Campus::factory()->create();

        // Set current campus in session to bypass campus selection middleware
        session(['current_campus_id' => $campus->id]);

        // Seed basic data
        $this->artisan('db:seed', ['--class' => 'ComprehensiveEducationSeeder']);

        $this->curriculumVersion = CurriculumVersion::first();
    }

    /** @test */
    public function it_can_get_available_electives_for_curriculum_version(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-versions/{$this->curriculumVersion->id}/available-electives");

        // Debug response if not OK
        if ($response->status() !== 200) {
            dump('Response Status: ' . $response->status());
            dump('Response Content: ' . $response->content());
        }

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'curriculum_version' => [
                        'id',
                        'specialization',
                        'program',
                    ],
                    'available_electives' => [
                        'same_program_other_specializations' => [
                            'label',
                            'count',
                        ],
                        'cross_program_electives' => [
                            'label',
                            'count',
                        ],
                        'general_electives' => [
                            'label',
                            'count',
                        ],
                    ],
                    'units' => [],
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page',
                    ],
                ]
            ]);
    }

    /** @test */
    public function it_can_search_available_electives(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-versions/{$this->curriculumVersion->id}/available-electives?search=Foundation");

        $response->assertOk();

        $units = $response->json('data.units');
        foreach ($units as $unit) {
            $this->assertTrue(
                stripos($unit['name'], 'Foundation') !== false ||
                    stripos($unit['code'], 'Foundation') !== false
            );
        }
    }

    /** @test */
    public function it_can_filter_electives_by_category(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-versions/{$this->curriculumVersion->id}/available-electives?category=same_program");

        $response->assertOk();
    }

    /** @test */
    public function it_can_get_elective_slots_for_curriculum_version(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-versions/{$this->curriculumVersion->id}/elective-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'curriculum_version' => [
                        'id',
                        'specialization',
                        'program',
                        'version_code',
                    ],
                    'elective_slots' => [
                        '*' => [
                            'id',
                            'year_level',
                            'semester_number',
                            'current_unit' => [
                                'id',
                                'code',
                                'name',
                                'credit_points',
                            ],
                            'can_be_changed',
                            'note',
                        ]
                    ],
                    'total_elective_slots',
                ]
            ]);
    }

    /** @test */
    public function it_can_update_elective_slot(): void
    {
        // Get an elective curriculum unit by type
        $electiveCurriculumUnit = CurriculumUnit::where('type', 'elective')->first();

        // Create a completely new unit for testing that's not assigned to any curriculum
        $newUnit = Unit::factory()->create([
            'code' => 'TEST001',
            'name' => 'Test Elective Unit',
            'credit_points' => 6
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/curriculum-units/{$electiveCurriculumUnit->id}/update-elective", [
                'unit_id' => $newUnit->id,
                'reason' => 'Testing elective update',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'curriculum_unit_id',
                    'old_unit' => [
                        'id',
                        'code',
                        'name',
                    ],
                    'new_unit' => [
                        'id',
                        'code',
                        'name',
                        'credit_points',
                    ],
                ]
            ]);

        // Verify the update in database
        $electiveCurriculumUnit->refresh();
        $this->assertEquals($newUnit->id, $electiveCurriculumUnit->unit_id);
        $this->assertStringContainsString('Testing elective update', $electiveCurriculumUnit->note);
    }

    /** @test */
    public function it_cannot_update_non_elective_slot(): void
    {
        // Get a non-elective curriculum unit by type
        $coreCurriculumUnit = CurriculumUnit::where('type', 'core')->first();

        $newUnit = Unit::where('id', '!=', $coreCurriculumUnit->unit_id)->first();

        $response = $this->actingAs($this->user)
            ->putJson("/api/curriculum-units/{$coreCurriculumUnit->id}/update-elective", [
                'unit_id' => $newUnit->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['curriculum_unit']);
    }

    /** @test */
    public function it_can_get_unit_details(): void
    {
        $unit = Unit::first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/units/{$unit->id}/details");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'credit_points',
                    'prerequisites',
                    'syllabus',
                    'used_in_specializations',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_elective_recommendations(): void
    {
        $electiveCurriculumUnit = CurriculumUnit::where('type', 'elective')->first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-units/{$electiveCurriculumUnit->id}/recommendations");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'curriculum_unit' => [
                        'id',
                        'year_level',
                        'semester_number',
                        'current_unit' => [
                            'id',
                            'code',
                            'name',
                        ],
                    ],
                    'recommendations' => [
                        'same_program_other_specializations' => [
                            'label',
                            'units',
                            'total_count',
                        ],
                        'other_programs' => [
                            'label',
                            'units',
                            'total_count',
                        ],
                        'unassigned_units' => [
                            'label',
                            'units',
                            'total_count',
                        ],
                    ],
                ]
            ]);
    }

    /** @test */
    public function it_cannot_get_recommendations_for_non_elective_unit(): void
    {
        $coreCurriculumUnit = CurriculumUnit::where('type', 'core')->first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/curriculum-units/{$coreCurriculumUnit->id}/recommendations");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['curriculum_unit']);
    }
}
