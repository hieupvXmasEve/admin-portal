<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\Unit;
use App\Services\StudentAcademicSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StudentAcademicSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentAcademicSummaryService $service;

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

        $this->service = new StudentAcademicSummaryService;

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
    }

    public function test_get_academic_summary_returns_complete_data_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overview', $result);
        $this->assertArrayHasKey('registrations', $result);
        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('attendance', $result);
        $this->assertArrayHasKey('gpa', $result);
        $this->assertArrayHasKey('graduation', $result);
    }

    public function test_get_academic_summary_with_caching(): void
    {
        Cache::flush();

        // First call should cache the result
        $result1 = $this->service->getAcademicSummary($this->student->id, [], true);

        // Second call should return cached result
        $result2 = $this->service->getAcademicSummary($this->student->id, [], true);

        $this->assertEquals($result1, $result2);

        // Verify cache exists
        $cacheKey = "student_academic_summary_{$this->student->id}_".md5(serialize([]));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_clear_academic_summary_cache(): void
    {
        // Cache some data
        $this->service->getAcademicSummary($this->student->id, [], true);

        $cacheKey = "student_academic_summary_{$this->student->id}_".md5(serialize([]));
        $this->assertTrue(Cache::has($cacheKey));

        // Clear cache
        $this->service->clearAcademicSummaryCache($this->student->id);

        // Verify cache is cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_get_course_scores_details_with_pagination(): void
    {
        // Create test assessment scores
        AssessmentComponentDetailScore::factory()->count(25)->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $result = $this->service->getCourseScoresDetails(
            $this->student,
            $this->courseOffering->id,
            0,
            10
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(10, $result['data']);
        $this->assertEquals(25, $result['pagination']['total']);
        $this->assertTrue($result['pagination']['has_more']);
    }

    public function test_get_course_scores_details_with_offset(): void
    {
        // Create test assessment scores
        AssessmentComponentDetailScore::factory()->count(15)->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $result = $this->service->getCourseScoresDetails(
            $this->student,
            $this->courseOffering->id,
            10,
            10
        );

        $this->assertIsArray($result);
        $this->assertCount(5, $result['data']); // Only 5 remaining after offset 10
        $this->assertEquals(15, $result['pagination']['total']);
        $this->assertFalse($result['pagination']['has_more']);
    }

    public function test_get_academic_summary_handles_missing_student(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->getAcademicSummary(99999, [], false);
    }

    public function test_get_academic_summary_with_filters(): void
    {
        $filters = [
            'registrations' => [
                'semester_id' => $this->semester->id,
                'status' => 'completed',
            ],
            'per_page' => 25,
        ];

        $result = $this->service->getAcademicSummary($this->student->id, $filters, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('registrations', $result);
    }

    public function test_overview_section_contains_required_fields(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $overview = $result['overview'];

        $this->assertArrayHasKey('student_info', $overview);
        $this->assertArrayHasKey('program_info', $overview);
        $this->assertArrayHasKey('academic_stats', $overview);
        $this->assertArrayHasKey('emergency_contact', $overview);
        $this->assertArrayHasKey('additional_info', $overview);

        // Check student info structure
        $studentInfo = $overview['student_info'];
        $this->assertArrayHasKey('student_id', $studentInfo);
        $this->assertArrayHasKey('full_name', $studentInfo);
        $this->assertArrayHasKey('email', $studentInfo);
        $this->assertArrayHasKey('status', $studentInfo);
    }

    public function test_registrations_section_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $registrations = $result['registrations'];

        $this->assertArrayHasKey('data', $registrations);
        $this->assertArrayHasKey('summary', $registrations);
        $this->assertArrayHasKey('pagination', $registrations);
        $this->assertArrayHasKey('filters', $registrations);

        // Check summary structure
        $summary = $registrations['summary'];
        $this->assertArrayHasKey('total_registrations', $summary);
        $this->assertArrayHasKey('completed', $summary);
        $this->assertArrayHasKey('active', $summary);
        $this->assertArrayHasKey('retakes', $summary);
    }

    public function test_scores_section_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $scores = $result['scores'];

        $this->assertArrayHasKey('data', $scores);
        $this->assertArrayHasKey('summary', $scores);

        // Check summary structure
        $summary = $scores['summary'];
        $this->assertArrayHasKey('total_courses', $summary);
        $this->assertArrayHasKey('total_assessments', $summary);
        $this->assertArrayHasKey('completed_assessments', $summary);
        $this->assertArrayHasKey('overall_average', $summary);
    }

    public function test_attendance_section_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $attendance = $result['attendance'];

        $this->assertArrayHasKey('data', $attendance);
        $this->assertArrayHasKey('summary', $attendance);

        // Check summary structure
        $summary = $attendance['summary'];
        $this->assertArrayHasKey('total_units', $summary);
        $this->assertArrayHasKey('total_sessions', $summary);
        $this->assertArrayHasKey('total_attended', $summary);
        $this->assertArrayHasKey('overall_percentage', $summary);
    }

    public function test_gpa_section_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $gpa = $result['gpa'];

        $this->assertArrayHasKey('data', $gpa);
        $this->assertArrayHasKey('summary', $gpa);

        // Check summary structure
        $summary = $gpa['summary'];
        $this->assertArrayHasKey('current_semester_gpa', $summary);
        $this->assertArrayHasKey('current_cumulative_gpa', $summary);
        $this->assertArrayHasKey('total_credit_hours_earned', $summary);
        $this->assertArrayHasKey('current_academic_standing', $summary);
    }

    public function test_graduation_section_structure(): void
    {
        $result = $this->service->getAcademicSummary($this->student->id, [], false);
        $graduation = $result['graduation'];

        $this->assertArrayHasKey('credit_summary', $graduation);
        $this->assertArrayHasKey('requirements', $graduation);
        $this->assertArrayHasKey('graduation_status', $graduation);
        $this->assertArrayHasKey('progress_timeline', $graduation);

        // Check credit summary structure
        $creditSummary = $graduation['credit_summary'];
        $this->assertArrayHasKey('total_required', $creditSummary);
        $this->assertArrayHasKey('total_earned', $creditSummary);
        $this->assertArrayHasKey('remaining', $creditSummary);
        $this->assertArrayHasKey('completion_percentage', $creditSummary);
    }

    public function test_service_handles_empty_data_gracefully(): void
    {
        // Test with student that has no academic data
        $emptyStudent = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'specialization_id' => $this->specialization->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $result = $this->service->getAcademicSummary($emptyStudent->id, [], false);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['registrations']['summary']['total_registrations']);
        $this->assertEquals(0, $result['scores']['summary']['total_courses']);
        $this->assertEquals(0, $result['attendance']['summary']['total_units']);
        $this->assertEquals(0, $result['gpa']['summary']['current_semester_gpa']);
        $this->assertEquals(0, $result['graduation']['credit_summary']['total_earned']);
    }
}
