<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponent;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\GpaCalculation;
use App\Services\StudentAcademicSummaryService;
use App\Constants\StudentRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data
    $this->campus = Campus::factory()->create(['name' => 'Test Campus', 'code' => 'TC']);
    $this->program = Program::factory()->create(['name' => 'Test Program', 'code' => 'TP']);
    $this->specialization = Specialization::factory()->create([
        'program_id' => $this->program->id,
        'name' => 'Test Specialization',
        'code' => 'TS'
    ]);
    $this->curriculumVersion = CurriculumVersion::factory()->create([
        'program_id' => $this->program->id,
        'name' => 'Test Curriculum',
        'version' => '2024.1'
    ]);
    $this->semester = Semester::factory()->create([
        'name' => 'Semester 1 2024',
        'code' => 'S1-2024',
        'is_current' => true
    ]);
    
    $this->student = Student::factory()->create([
        'student_id' => 'TC202400001',
        'full_name' => 'Test Student',
        'email' => 'test.student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'specialization_id' => $this->specialization->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'status' => 'active',
        'academic_status' => 'active'
    ]);
    
    $this->user = User::factory()->create();
    
    // Create units and course offerings
    $this->unit1 = Unit::factory()->create(['name' => 'Test Unit 1', 'code' => 'TU101', 'credit_hours' => 3]);
    $this->unit2 = Unit::factory()->create(['name' => 'Test Unit 2', 'code' => 'TU102', 'credit_hours' => 4]);
    
    $this->courseOffering1 = CourseOffering::factory()->create([
        'unit_id' => $this->unit1->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id
    ]);
    $this->courseOffering2 = CourseOffering::factory()->create([
        'unit_id' => $this->unit2->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id
    ]);
    
    $this->service = new StudentAcademicSummaryService();
});

describe('StudentAcademicSummaryService', function () {
    it('can get comprehensive academic summary', function () {
        // Create test data
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A',
            'credit_hours' => 3
        ]);
        
        AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit1->id,
            'completion_status' => 'completed',
            'grade_status' => 'passing',
            'credit_hours_earned' => 3
        ]);
        
        GpaCalculation::factory()->create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'program_id' => $this->program->id,
            'gpa' => 3.5,
            'academic_standing' => 'good_standing'
        ]);
        
        $summary = $this->service->getAcademicSummary($this->student->id);
        
        expect($summary)->toHaveKeys(['overview', 'registrations', 'scores', 'attendance', 'gpa', 'graduation']);
        expect($summary['overview'])->toHaveKeys(['student_info', 'program_info', 'academic_stats']);
        expect($summary['overview']['student_info']['student_id'])->toBe('TC202400001');
        expect($summary['overview']['student_info']['full_name'])->toBe('Test Student');
        expect($summary['overview']['academic_stats']['total_registrations'])->toBe(1);
        expect($summary['overview']['academic_stats']['total_credits_earned'])->toBe(3);
    });
    
    it('can get student overview correctly', function () {
        $summary = $this->service->getAcademicSummary($this->student->id);
        $overview = $summary['overview'];
        
        expect($overview['student_info'])->toMatchArray([
            'id' => $this->student->id,
            'student_id' => 'TC202400001',
            'full_name' => 'Test Student',
            'email' => 'test.student@example.com',
            'status' => 'active',
            'academic_status' => 'active'
        ]);
        
        expect($overview['program_info']['campus']['name'])->toBe('Test Campus');
        expect($overview['program_info']['program']['name'])->toBe('Test Program');
        expect($overview['program_info']['specialization']['name'])->toBe('Test Specialization');
    });
    
    it('can get registrations data correctly', function () {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A',
            'credit_hours' => 3,
            'is_retake' => false,
            'attempt_number' => 1
        ]);
        
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering2->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'enrolled',
            'credit_hours' => 4,
            'is_retake' => true,
            'attempt_number' => 2
        ]);
        
        $summary = $this->service->getAcademicSummary($this->student->id);
        $registrations = $summary['registrations'];
        
        expect($registrations['data'])->toHaveCount(2);
        expect($registrations['summary']['total_registrations'])->toBe(2);
        expect($registrations['summary']['completed'])->toBe(1);
        expect($registrations['summary']['active'])->toBe(1);
        expect($registrations['summary']['retakes'])->toBe(1);
        
        $firstRegistration = $registrations['data']->first();
        expect($firstRegistration['course_name'])->toBe('Test Unit 2');
        expect($firstRegistration['registration_status'])->toBe('enrolled');
        expect($firstRegistration['is_retake'])->toBe(true);
        expect($firstRegistration['attempt_number'])->toBe(2);
    });
    
    it('can filter data by semester', function () {
        $anotherSemester = Semester::factory()->create(['name' => 'Semester 2 2024', 'code' => 'S2-2024']);
        
        // Create registrations in different semesters
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed'
        ]);
        
        $anotherCourseOffering = CourseOffering::factory()->create([
            'unit_id' => $this->unit2->id,
            'semester_id' => $anotherSemester->id,
            'campus_id' => $this->campus->id
        ]);
        
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $anotherCourseOffering->id,
            'semester_id' => $anotherSemester->id,
            'registration_status' => 'enrolled'
        ]);
        
        $filteredData = $this->service->filterBySemester($this->student->id, $this->semester->id);
        
        expect($filteredData['registrations'])->toHaveCount(1);
        expect($filteredData['semester_info']['name'])->toBe('Semester 1 2024');
        expect($filteredData['registrations']->first()['course_name'])->toBe('Test Unit 1');
    });
    
    it('can filter data by course offering', function () {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'B+',
            'credit_hours' => 3
        ]);
        
        $filteredData = $this->service->filterByCourseOffering($this->student->id, $this->courseOffering1->id);
        
        expect($filteredData['course_info']['name'])->toBe('Test Unit 1');
        expect($filteredData['course_info']['code'])->toBe('TU101');
        expect($filteredData['registration']['final_grade'])->toBe('B+');
        expect($filteredData['registration']['credit_hours'])->toBe(3);
    });
});

describe('StudentAcademicSummaryController', function () {
    beforeEach(function () {
        // Mock permission for testing
        $this->actingAs($this->user);

        // Mock the Gate to allow access
        Gate::define('view_student_summary', fn () => true);
    });

    it('can display academic summary page', function () {
        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('students/AcademicSummary')
                ->has('student')
                ->has('academicSummary')
                ->where('student.id', $this->student->id)
                ->where('student.student_id', 'TC202400001')
                ->has('academicSummary.overview')
                ->has('academicSummary.overview.student_info')
                ->has('academicSummary.overview.program_info')
                ->has('academicSummary.overview.academic_stats')
        );
    });

    it('displays comprehensive overview data', function () {
        // Create additional test data
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A',
            'credit_hours' => 3
        ]);

        AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit1->id,
            'completion_status' => 'completed',
            'grade_status' => 'passing',
            'credit_hours_earned' => 3
        ]);

        GpaCalculation::factory()->create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'program_id' => $this->program->id,
            'gpa' => 3.5,
            'cumulative_gpa' => 3.4,
            'academic_standing' => 'good_standing',
            'is_current' => true
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('students/AcademicSummary')
                ->where('academicSummary.overview.student_info.full_name', 'Test Student')
                ->where('academicSummary.overview.student_info.student_id', 'TC202400001')
                ->where('academicSummary.overview.student_info.email', 'test.student@example.com')
                ->where('academicSummary.overview.program_info.campus.name', 'Test Campus')
                ->where('academicSummary.overview.program_info.program.name', 'Test Program')
                ->where('academicSummary.overview.program_info.specialization.name', 'Test Specialization')
                ->where('academicSummary.overview.academic_stats.total_registrations', 1)
                ->where('academicSummary.overview.academic_stats.completed_courses', 1)
                ->where('academicSummary.overview.academic_stats.total_credits_earned', 3)
                ->where('academicSummary.overview.academic_stats.current_gpa', 3.5)
                ->where('academicSummary.overview.academic_stats.cumulative_gpa', 3.4)
                ->where('academicSummary.overview.academic_stats.academic_standing', 'good_standing')
        );
    });

    it('requires permission to access academic summary', function () {
        // Redefine gate to deny access
        Gate::define('view_student_summary', fn () => false);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(403);
    });

    it('can filter by semester via API', function () {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed'
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER, [
            'student' => $this->student,
            'semester_id' => $this->semester->id
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'semester_info' => [
                    'name' => 'Semester 1 2024'
                ]
            ]
        ]);
    });

    it('validates semester_id when filtering by semester', function () {
        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_SEMESTER, [
            'student' => $this->student,
            'semester_id' => 99999 // Non-existent semester
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['semester_id']);
    });

    it('can filter by course offering via API', function () {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A'
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING, [
            'student' => $this->student,
            'course_offering_id' => $this->courseOffering1->id
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'course_info' => [
                    'name' => 'Test Unit 1',
                    'code' => 'TU101'
                ]
            ]
        ]);
    });

    it('validates course_offering_id when filtering by course offering', function () {
        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_FILTER_BY_COURSE_OFFERING, [
            'student' => $this->student,
            'course_offering_id' => 99999 // Non-existent course offering
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['course_offering_id']);
    });

    it('can get attendance details via API', function () {
        // Create class session and attendance
        $classSession = ClassSession::factory()->create([
            'course_offering_id' => $this->courseOffering1->id,
            'session_date' => now()->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00'
        ]);

        Attendance::factory()->create([
            'class_session_id' => $classSession->id,
            'student_id' => $this->student->id,
            'status' => 'present',
            'check_in_time' => now()
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_ATTENDANCE_DETAILS, [
            'student' => $this->student,
            'unit_id' => $this->unit1->id
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'unit_info' => [
                    'name' => 'Test Unit 1',
                    'code' => 'TU101'
                ],
                'summary' => [
                    'total_sessions' => 1,
                    'present' => 1,
                    'attendance_percentage' => 100.0
                ]
            ]
        ]);
    });

    it('can get score details via API', function () {
        // Create assessment structure
        $assessmentComponent = AssessmentComponent::factory()->create([
            'name' => 'Assignment',
            'type' => 'assignment',
            'weight' => 30
        ]);

        $assessmentDetail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assessmentComponent->id,
            'name' => 'Assignment 1',
            'due_date' => now()->addDays(7),
            'max_points' => 100
        ]);

        AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $assessmentDetail->id,
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'points_earned' => 85,
            'percentage_score' => 85.0,
            'letter_grade' => 'B+',
            'status' => 'graded'
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SCORE_DETAILS, [
            'student' => $this->student,
            'course_offering_id' => $this->courseOffering1->id
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'course_info' => [
                    'name' => 'Test Unit 1',
                    'code' => 'TU101'
                ],
                'overall_summary' => [
                    'total_components' => 1,
                    'overall_average' => 85.0
                ]
            ]
        ]);
    });

    it('can access all tabs with proper data structure', function () {
        // Create comprehensive test data for all tabs
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'completed',
            'final_grade' => 'A',
            'credit_hours' => 3
        ]);

        AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering1->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit1->id,
            'completion_status' => 'completed',
            'grade_status' => 'passing',
            'credit_hours_earned' => 3
        ]);

        GpaCalculation::factory()->create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'program_id' => $this->program->id,
            'gpa' => 3.5,
            'cumulative_gpa' => 3.4,
            'academic_standing' => 'good_standing',
            'is_current' => true
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $this->student));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('students/AcademicSummary')
                // Overview tab data
                ->has('academicSummary.overview')
                ->has('academicSummary.overview.student_info')
                ->has('academicSummary.overview.program_info')
                ->has('academicSummary.overview.academic_stats')
                // Registrations tab data
                ->has('academicSummary.registrations')
                ->has('academicSummary.registrations.data')
                ->has('academicSummary.registrations.summary')
                // Scores tab data
                ->has('academicSummary.scores')
                ->has('academicSummary.scores.data')
                ->has('academicSummary.scores.summary')
                // Attendance tab data
                ->has('academicSummary.attendance')
                ->has('academicSummary.attendance.data')
                ->has('academicSummary.attendance.summary')
                // GPA tab data
                ->has('academicSummary.gpa')
                ->has('academicSummary.gpa.data')
                ->has('academicSummary.gpa.summary')
                // Graduation tab data
                ->has('academicSummary.graduation')
                ->has('academicSummary.graduation.credit_summary')
                ->has('academicSummary.graduation.requirements')
                ->has('academicSummary.graduation.graduation_status')
                ->has('academicSummary.graduation.progress_timeline')
        );
    });

    it('handles empty data gracefully for all tabs', function () {
        // Test with student that has no academic data
        $emptyStudent = Student::factory()->create([
            'student_id' => 'TC202400002',
            'full_name' => 'Empty Student',
            'email' => 'empty.student@example.com',
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'specialization_id' => $this->specialization->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $response = $this->get(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $emptyStudent));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('students/AcademicSummary')
                ->where('academicSummary.overview.academic_stats.total_registrations', 0)
                ->where('academicSummary.overview.academic_stats.completed_courses', 0)
                ->where('academicSummary.overview.academic_stats.total_credits_earned', 0)
                ->where('academicSummary.registrations.summary.total_registrations', 0)
                ->where('academicSummary.scores.summary.total_courses', 0)
                ->where('academicSummary.attendance.summary.total_units', 0)
                ->where('academicSummary.gpa.summary.current_semester_gpa', 0)
                ->where('academicSummary.graduation.credit_summary.total_earned', 0)
        );
    });
});
