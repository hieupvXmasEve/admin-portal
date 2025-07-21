<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAcademicSummaryControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_show_requires_authentication(): void
    {
        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertRedirect(route('login'));
    }

    public function test_show_requires_permission(): void
    {
        // Create user without permission
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(403);
    }

    public function test_show_returns_academic_summary_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('students/AcademicSummary')
                ->has('student')
                ->has('academicSummary')
                ->has('academicSummary.overview')
                ->has('academicSummary.registrations')
                ->has('academicSummary.scores')
                ->has('academicSummary.attendance')
                ->has('academicSummary.gpa')
                ->has('academicSummary.graduation')
        );
    }

    public function test_show_includes_student_relationships(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('student.campus')
                ->has('student.program')
                ->has('student.specialization')
                ->has('student.curriculumVersion')
        );
    }

    public function test_filter_by_semester_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER, $this->student), [
                'semester_id' => $this->semester->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_filter_by_semester_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER, $this->student), [
                'semester_id' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['semester_id']);
    }

    public function test_filter_by_semester_returns_filtered_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER, $this->student), [
                'semester_id' => $this->semester->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }

    public function test_filter_by_course_offering_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING, $this->student), [
                'course_offering_id' => $this->courseOffering->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_filter_by_course_offering_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING, $this->student), [
                'course_offering_id' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['course_offering_id']);
    }

    public function test_filter_by_course_offering_returns_filtered_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING, $this->student), [
                'course_offering_id' => $this->courseOffering->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }

    public function test_get_attendance_details_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_ATTENDANCE_DETAILS, $this->student), [
                'unit_id' => $this->unit->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_get_attendance_details_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_ATTENDANCE_DETAILS, $this->student), [
                'unit_id' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['unit_id']);
    }

    public function test_get_attendance_details_returns_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_ATTENDANCE_DETAILS, $this->student), [
                'unit_id' => $this->unit->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }

    public function test_get_score_details_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SCORE_DETAILS, $this->student), [
                'course_offering_id' => $this->courseOffering->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_get_score_details_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SCORE_DETAILS, $this->student), [
                'course_offering_id' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['course_offering_id']);
    }

    public function test_get_score_details_returns_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SCORE_DETAILS, $this->student), [
                'course_offering_id' => $this->courseOffering->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }

    public function test_get_course_scores_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_COURSE_SCORES, [
                'student' => $this->student,
                'courseOfferingId' => $this->courseOffering->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_get_course_scores_validates_pagination_parameters(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_COURSE_SCORES, [
                'student' => $this->student,
                'courseOfferingId' => $this->courseOffering->id,
            ]), [
                'offset' => -1,
                'limit' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['offset', 'limit']);
    }

    public function test_get_course_scores_returns_paginated_data(): void
    {
        // Create test assessment scores
        AssessmentComponentDetailScore::factory()->count(15)->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_COURSE_SCORES, [
                'student' => $this->student,
                'courseOfferingId' => $this->courseOffering->id,
            ]), [
                'offset' => 0,
                'limit' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'pagination' => [
                    'total',
                    'offset',
                    'limit',
                    'has_more',
                ],
            ],
        ]);
    }

    public function test_academic_summary_handles_nonexistent_student(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, 99999));

        $response->assertStatus(404);
    }

    public function test_academic_summary_with_comprehensive_data(): void
    {
        // Create comprehensive test data
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
        ]);

        AssessmentComponentDetailScore::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('students/AcademicSummary')
                ->where('academicSummary.registrations.summary.total_registrations', 1)
                ->where('academicSummary.scores.summary.total_courses', 1)
        );
    }
}
