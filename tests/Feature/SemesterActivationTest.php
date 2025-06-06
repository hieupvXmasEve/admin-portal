<?php

namespace Tests\Feature;

use App\Models\Semester;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Campus;
use App\Models\CampusUserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with permissions for testing
        $this->user = User::factory()->create();

        // Create permissions if they don't exist
        $viewPermission = Permission::firstOrCreate([
            'code' => 'view_semester'
        ], [
            'name' => 'View Semester',
            'code' => 'view_semester'
        ]);

        $editPermission = Permission::firstOrCreate([
            'code' => 'edit_semester'
        ], [
            'name' => 'Edit Semester',
            'code' => 'edit_semester'
        ]);

        // Create role and assign permissions
        $role = Role::firstOrCreate([
            'name' => 'admin',
            'code' => 'admin'
        ]);

        $role->permissions()->syncWithoutDetaching([$viewPermission->id, $editPermission->id]);

        // Create campus user role for permission checking
        $campus = \App\Models\Campus::factory()->create();
        \App\Models\CampusUserRole::create([
            'user_id' => $this->user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id
        ]);

        // Set current campus in session for permission checking
        session([
            'current_campus_id' => $campus->id,
            'permissions' => collect(['view_semester', 'edit_semester'])
        ]);

        $this->actingAs($this->user);
    }

    public function test_only_one_semester_can_be_active_at_a_time(): void
    {
        // Create two semesters
        $semester1 = Semester::factory()->create([
            'name' => 'Semester 1',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(100),
            'is_active' => false,
            'is_archived' => false,
        ]);

        $semester2 = Semester::factory()->create([
            'name' => 'Semester 2',
            'start_date' => Carbon::now()->addDays(110),
            'end_date' => Carbon::now()->addDays(200),
            'is_active' => false,
            'is_archived' => false,
        ]);

        // Activate first semester
        $this->assertTrue($semester1->activate());
        $this->assertTrue($semester1->fresh()->is_active);

        // Try to activate second semester - should fail
        $this->assertFalse($semester2->activate());
        $this->assertFalse($semester2->fresh()->is_active);
        $this->assertTrue($semester1->fresh()->is_active);
    }

    public function test_can_only_activate_next_upcoming_semester(): void
    {
        // Create three semesters with different start dates
        $nearestSemester = Semester::factory()->create([
            'name' => 'Nearest Semester',
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(50),
            'is_active' => false,
            'is_archived' => false,
        ]);

        $futureSemester = Semester::factory()->create([
            'name' => 'Future Semester',
            'start_date' => Carbon::now()->addDays(60),
            'end_date' => Carbon::now()->addDays(110),
            'is_active' => false,
            'is_archived' => false,
        ]);

        // Can activate the nearest semester
        $this->assertTrue($nearestSemester->canBeActivated());
        $this->assertTrue($nearestSemester->activate());

        // Cannot activate future semester when nearest is available
        $nearestSemester->update(['is_active' => false]); // Reset for test
        $this->assertFalse($futureSemester->canBeActivated());
        $this->assertFalse($futureSemester->activate());
    }

    public function test_cannot_activate_semester_that_has_started(): void
    {
        $pastSemester = Semester::factory()->create([
            'name' => 'Past Semester',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(50),
            'is_active' => false,
            'is_archived' => false,
        ]);

        $this->assertFalse($pastSemester->canBeActivated());
        $this->assertFalse($pastSemester->activate());

        $error = $pastSemester->getActivationError();
        $this->assertStringContainsString('Cannot activate a semester that has already started', $error);
    }

    public function test_cannot_activate_archived_semester(): void
    {
        $archivedSemester = Semester::factory()->create([
            'name' => 'Archived Semester',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(50),
            'is_active' => false,
            'is_archived' => true,
        ]);

        $this->assertFalse($archivedSemester->canBeActivated());
        $this->assertFalse($archivedSemester->activate());

        $error = $archivedSemester->getActivationError();
        $this->assertStringContainsString('Cannot activate an archived semester', $error);
    }

    public function test_semester_auto_deactivates_after_end_date(): void
    {
        $expiredSemester = Semester::factory()->create([
            'name' => 'Expired Semester',
            'start_date' => Carbon::now()->subDays(100),
            'end_date' => Carbon::now()->subDays(10),
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->assertTrue($expiredSemester->shouldBeDeactivated());

        // Test auto-deactivation
        $deactivatedCount = Semester::deactivateExpiredSemesters();
        $this->assertEquals(1, $deactivatedCount);
        $this->assertFalse($expiredSemester->fresh()->is_active);
    }

    public function test_get_next_active_semester(): void
    {
        // Create semesters with different start dates
        $semester1 = Semester::factory()->create([
            'start_date' => Carbon::now()->addDays(20),
            'is_archived' => false,
        ]);

        $semester2 = Semester::factory()->create([
            'start_date' => Carbon::now()->addDays(10), // This is closer
            'is_archived' => false,
        ]);

        $semester3 = Semester::factory()->create([
            'start_date' => Carbon::now()->addDays(30),
            'is_archived' => false,
        ]);

        $nextSemester = Semester::getNextActiveSemester();
        $this->assertEquals($semester2->id, $nextSemester->id);
    }

    public function test_activation_api_endpoint(): void
    {
        $semester = Semester::factory()->create([
            'name' => 'Test Semester',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(100),
            'is_active' => false,
            'is_archived' => false,
        ]);

        // Test successful activation
        $response = $this->postJson(route('semesters.activate', $semester));
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue($semester->fresh()->is_active);
    }

    public function test_activation_api_endpoint_with_invalid_semester(): void
    {
        $archivedSemester = Semester::factory()->create([
            'name' => 'Archived Semester',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(100),
            'is_active' => false,
            'is_archived' => true,
        ]);

        // Test failed activation
        $response = $this->postJson(route('semesters.activate', $archivedSemester));
        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertFalse($archivedSemester->fresh()->is_active);
    }

    public function test_deactivation_api_endpoint(): void
    {
        $semester = Semester::factory()->create([
            'name' => 'Active Semester',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(100),
            'is_active' => true,
            'is_archived' => false,
        ]);

        // Test deactivation
        $response = $this->postJson(route('semesters.deactivate', $semester));
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse($semester->fresh()->is_active);
    }

    public function test_console_command_deactivates_expired_semesters(): void
    {
        // Create expired active semester
        $expiredSemester = Semester::factory()->create([
            'name' => 'Expired Semester',
            'start_date' => Carbon::now()->subDays(100),
            'end_date' => Carbon::now()->subDays(10),
            'is_active' => true,
        ]);

        // Create current active semester (should not be deactivated)
        $currentSemester = Semester::factory()->create([
            'name' => 'Current Semester',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(50),
            'is_active' => true,
        ]);

        // Run the console command
        $this->artisan('semester:deactivate-expired')
            ->expectsOutputToContain('Checking for expired semesters as of:')
            ->expectsQuestion('Do you want to deactivate these expired semesters?', 'yes')
            ->expectsOutput('Successfully deactivated 1 expired semester(s).')
            ->assertExitCode(0);

        // Check results
        $this->assertFalse($expiredSemester->fresh()->is_active);
        $this->assertTrue($currentSemester->fresh()->is_active);
    }

    public function test_prevents_changing_active_status_during_semester_period(): void
    {
        // Create a currently running semester
        $semester = Semester::factory()->create([
            'start_date' => Carbon::now()->subDays(5), // Started 5 days ago
            'end_date' => Carbon::now()->addDays(10),   // Ends in 10 days
            'is_active' => true,
        ]);

        $this->assertFalse($semester->canChangeActiveStatus());
        $this->assertEquals('Cannot change active status during the semester period.', $semester->getActiveStatusChangeError());
    }

    public function test_allows_changing_active_status_before_semester_starts(): void
    {
        // Create a future semester
        $semester = Semester::factory()->create([
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(50),
            'is_active' => false,
        ]);

        $this->assertTrue($semester->canChangeActiveStatus());
        $this->assertNull($semester->getActiveStatusChangeError());
    }

    public function test_allows_changing_active_status_after_semester_ends(): void
    {
        // Create a past semester
        $semester = Semester::factory()->create([
            'start_date' => Carbon::now()->subDays(50),
            'end_date' => Carbon::now()->subDays(10), // Ended 10 days ago
            'is_active' => false,
        ]);

        $this->assertTrue($semester->canChangeActiveStatus());
        $this->assertNull($semester->getActiveStatusChangeError());
    }

    public function test_prevents_api_activation_during_semester_period(): void
    {
        // Create a currently running semester
        $semester = Semester::factory()->create([
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(10),
            'is_active' => false,
        ]);

        $response = $this->postJson(route('semesters.activate', $semester));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot change active status during the semester period.',
            ]);
    }

    public function test_prevents_api_deactivation_during_semester_period(): void
    {
        // Create a currently running semester
        $semester = Semester::factory()->create([
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(10),
            'is_active' => true,
        ]);

        $response = $this->postJson(route('semesters.deactivate', $semester));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot change active status during the semester period.',
            ]);
    }
}
