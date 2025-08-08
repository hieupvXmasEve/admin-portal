<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Constants\StudentRoutes;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudentAcademicSummaryTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $user;

    private Student $student;

    private Campus $campus;

    private Program $program;

    private Specialization $specialization;

    private CurriculumVersion $curriculumVersion;

    private Semester $semester;

    private Unit $unit;

    private CourseOffering $courseOffering;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->campus = Campus::factory()->create();
        $this->program = Program::factory()->create();
        $this->specialization = Specialization::factory()->create();
        $this->curriculumVersion = CurriculumVersion::factory()->create([
            'program_id' => $this->program->id,
            'specialization_id' => $this->specialization->id,
        ]);
        $this->semester = Semester::factory()->create();
        $this->unit = Unit::factory()->create();
        $this->courseOffering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
        ]);

        $this->student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'specialization_id' => $this->specialization->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        // Create user with permissions
        $this->user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'view_student_summary']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);
    }

    public function test_academic_summary_page_loads_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee('Academic Summary')
                ->assertSee($this->student->full_name)
                ->assertSee($this->student->student_id);
        });
    }

    public function test_tab_navigation_works(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee('Overview')
                ->assertSee('Registrations')
                ->assertSee('Scores')
                ->assertSee('Attendance')
                ->assertSee('GPA')
                ->assertSee('Graduation');

            // Test clicking on different tabs
            $browser->click('[data-value="registrations"]')
                ->waitFor('[data-testid="registrations-tab"]', 5)
                ->assertVisible('[data-testid="registrations-tab"]');

            $browser->click('[data-value="scores"]')
                ->waitFor('[data-testid="scores-tab"]', 5)
                ->assertVisible('[data-testid="scores-tab"]');

            $browser->click('[data-value="attendance"]')
                ->waitFor('[data-testid="attendance-tab"]', 5)
                ->assertVisible('[data-testid="attendance-tab"]');

            $browser->click('[data-value="gpa"]')
                ->waitFor('[data-testid="gpa-tab"]', 5)
                ->assertVisible('[data-testid="gpa-tab"]');

            $browser->click('[data-value="graduation"]')
                ->waitFor('[data-testid="graduation-tab"]', 5)
                ->assertVisible('[data-testid="graduation-tab"]');

            // Return to overview
            $browser->click('[data-value="overview"]')
                ->waitFor('[data-testid="overview-tab"]', 5)
                ->assertVisible('[data-testid="overview-tab"]');
        });
    }

    public function test_overview_tab_displays_student_information(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee($this->student->student_id)
                ->assertSee($this->student->full_name)
                ->assertSee($this->student->email)
                ->assertSee($this->program->name)
                ->assertSee($this->specialization->name)
                ->assertSee($this->campus->name);
        });
    }

    public function test_registrations_tab_shows_course_data(): void
    {
        // Create test registration data
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="registrations"]')
                ->waitFor('[data-testid="registrations-tab"]', 5)
                ->assertSee('Total Registrations')
                ->assertSee('Completed')
                ->assertSee('Active')
                ->assertSee('Retakes');
        });
    }

    public function test_scores_tab_shows_assessment_data(): void
    {
        // Create test score data
        AssessmentComponentDetailScore::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'percentage_score' => 85.5,
            'letter_grade' => 'A',
            'status' => 'graded',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="scores"]')
                ->waitFor('[data-testid="scores-tab"]', 5)
                ->assertSee('Total Courses')
                ->assertSee('Total Assessments')
                ->assertSee('Completed')
                ->assertSee('Overall Average');
        });
    }

    public function test_attendance_tab_shows_attendance_data(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="attendance"]')
                ->waitFor('[data-testid="attendance-tab"]', 5)
                ->assertSee('Total Units')
                ->assertSee('Total Sessions')
                ->assertSee('Attended')
                ->assertSee('Overall Rate');
        });
    }

    public function test_gpa_tab_shows_gpa_information(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="gpa"]')
                ->waitFor('[data-testid="gpa-tab"]', 5)
                ->assertSee('Current GPA')
                ->assertSee('Cumulative GPA')
                ->assertSee('Credits Earned')
                ->assertSee('Academic Transcript');
        });
    }

    public function test_graduation_tab_shows_graduation_progress(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="graduation"]')
                ->waitFor('[data-testid="graduation-tab"]', 5)
                ->assertSee('Credits Earned')
                ->assertSee('Completion')
                ->assertSee('Credits Remaining')
                ->assertSee('Graduation Requirements');
        });
    }

    public function test_filtering_functionality_in_registrations_tab(): void
    {
        // Create multiple registrations for testing
        CourseRegistration::factory()->count(3)->create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="registrations"]')
                ->waitFor('[data-testid="registrations-tab"]', 5)
                ->assertSee('Filters');

            // Test search functionality
            if ($browser->element('input[placeholder*="Search"]')) {
                $browser->type('input[placeholder*="Search"]', 'test')
                    ->pause(1000); // Wait for search to process
            }

            // Test clear filters if available
            if ($browser->element('button:contains("Clear")')) {
                $browser->click('button:contains("Clear")')
                    ->pause(1000);
            }
        });
    }

    public function test_expandable_sections_in_scores_tab(): void
    {
        // Create test data with multiple scores
        AssessmentComponentDetailScore::factory()->count(5)->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->click('[data-value="scores"]')
                ->waitFor('[data-testid="scores-tab"]', 5);

            // Look for expand/collapse buttons
            if ($browser->element('button:contains("Expand")')) {
                $browser->click('button:contains("Expand")')
                    ->pause(1000)
                    ->assertSee('Collapse');
            }
        });
    }

    public function test_responsive_design_on_mobile(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone size
                ->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee('Academic Summary')
                ->assertSee($this->student->full_name);

            // Test tab navigation on mobile
            $browser->click('[data-value="registrations"]')
                ->waitFor('[data-testid="registrations-tab"]', 5)
                ->assertVisible('[data-testid="registrations-tab"]');
        });
    }

    public function test_export_functionality(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee('Export');

            // Test export button if available
            if ($browser->element('button:contains("Export")')) {
                $browser->click('button:contains("Export")')
                    ->pause(1000);
            }
        });
    }

    public function test_back_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student))
                ->assertSee('Back to Student');

            // Test back button
            if ($browser->element('button:contains("Back")')) {
                $browser->click('button:contains("Back")')
                    ->pause(2000);
            }
        });
    }

    public function test_loading_states(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

            // Check for loading indicators during tab switches
            $browser->click('[data-value="scores"]')
                ->pause(500); // Brief pause to catch loading state

            $browser->click('[data-value="attendance"]')
                ->pause(500);

            $browser->waitFor('[data-testid="attendance-tab"]', 5)
                ->assertVisible('[data-testid="attendance-tab"]');
        });
    }

    public function test_error_handling_for_empty_data(): void
    {
        // Test with student that has no academic data
        $emptyStudent = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'specialization_id' => $this->specialization->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->browse(function (Browser $browser) use ($emptyStudent) {
            $browser->loginAs($this->user)
                ->visit(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $emptyStudent))
                ->assertSee('Academic Summary')
                ->click('[data-value="registrations"]')
                ->waitFor('[data-testid="registrations-tab"]', 5)
                ->assertSee('0'); // Should show zero registrations

            $browser->click('[data-value="scores"]')
                ->waitFor('[data-testid="scores-tab"]', 5)
                ->assertSee('0'); // Should show zero courses

            $browser->click('[data-value="attendance"]')
                ->waitFor('[data-testid="attendance-tab"]', 5)
                ->assertSee('0'); // Should show zero units
        });
    }
}
