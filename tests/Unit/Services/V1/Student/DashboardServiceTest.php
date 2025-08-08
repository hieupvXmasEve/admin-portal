<?php

declare(strict_types=1);

namespace Tests\Unit\Services\V1\Student;

use App\Models\AcademicHold;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Services\V1\Student\CreditProgressService;
use App\Services\V1\Student\DashboardService;
use App\Services\V1\Student\GPACalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $dashboardService;

    protected GPACalculationService $gpaService;

    protected CreditProgressService $creditProgressService;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock services
        $this->gpaService = Mockery::mock(GPACalculationService::class);
        $this->creditProgressService = Mockery::mock(CreditProgressService::class);

        $this->dashboardService = new DashboardService(
            $this->gpaService,
            $this->creditProgressService
        );

        // Create test student
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);

        $this->student = Student::factory()->create([
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_dashboard_data_returns_complete_structure(): void
    {
        // Mock service responses
        $this->gpaService->shouldReceive('calculateCurrentGPA')
            ->with($this->student)
            ->andReturn(['gpa' => 3.5, 'quality_points' => 105]);

        $this->gpaService->shouldReceive('getGPATrend')
            ->with($this->student)
            ->andReturn(['trend_data' => []]);

        $this->gpaService->shouldReceive('getGradeDistribution')
            ->with($this->student)
            ->andReturn(['distribution' => []]);

        $this->gpaService->shouldReceive('getAcademicStanding')
            ->with($this->student)
            ->andReturn(['standing' => 'good']);

        $this->creditProgressService->shouldReceive('getCreditProgress')
            ->with($this->student)
            ->andReturn(['overall_progress' => ['completion_percentage' => 75]]);

        // Clear cache to ensure fresh data
        Cache::forget("dashboard:student:{$this->student->id}");

        $result = $this->dashboardService->getDashboardData($this->student);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('current_semester', $result);
        $this->assertArrayHasKey('gpa_data', $result);
        $this->assertArrayHasKey('credit_progress', $result);
        $this->assertArrayHasKey('academic_holds', $result);
        $this->assertArrayHasKey('upcoming_assessments', $result);
        $this->assertArrayHasKey('enrollment_status', $result);
        $this->assertArrayHasKey('quick_stats', $result);
    }

    public function test_get_current_semester_data_with_active_semester(): void
    {
        $semester = Semester::factory()->create([
            'is_active' => true,
            'name' => 'Spring 2024',
            'code' => 'SP24',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'semester_id' => $semester->id,
            'status' => 'in_progress',
            'semester_number' => 3,
        ]);

        $result = $this->dashboardService->getCurrentSemesterData($this->student);

        $this->assertIsArray($result);
        $this->assertEquals($semester->id, $result['semester']['id']);
        $this->assertEquals('Spring 2024', $result['semester']['name']);
        $this->assertEquals('SP24', $result['semester']['code']);
        $this->assertEquals('in_progress', $result['enrollment']['status']);
        $this->assertEquals(3, $result['enrollment']['semester_number']);
    }

    public function test_get_current_semester_data_without_active_semester(): void
    {
        // Ensure no active semester exists
        Semester::query()->update(['is_active' => false]);

        $result = $this->dashboardService->getCurrentSemesterData($this->student);

        $this->assertIsArray($result);
        $this->assertNull($result['semester']);
        $this->assertNull($result['enrollment']);
        $this->assertEquals(0, $result['registered_courses']);
        $this->assertEquals(0, $result['total_credits']);
    }

    public function test_get_gpa_data_uses_gpa_service(): void
    {
        $expectedGPAData = [
            'current_gpa' => ['gpa' => 3.5],
            'gpa_trend' => ['trend_data' => []],
            'grade_distribution' => ['distribution' => []],
            'academic_standing' => ['standing' => 'good'],
        ];

        $this->gpaService->shouldReceive('calculateCurrentGPA')
            ->with($this->student)
            ->andReturn($expectedGPAData['current_gpa']);

        $this->gpaService->shouldReceive('getGPATrend')
            ->with($this->student)
            ->andReturn($expectedGPAData['gpa_trend']);

        $this->gpaService->shouldReceive('getGradeDistribution')
            ->with($this->student)
            ->andReturn($expectedGPAData['grade_distribution']);

        $this->gpaService->shouldReceive('getAcademicStanding')
            ->with($this->student)
            ->andReturn($expectedGPAData['academic_standing']);

        $result = $this->dashboardService->getGPAData($this->student);

        $this->assertEquals($expectedGPAData, $result);
    }

    public function test_get_credit_progress_uses_credit_progress_service(): void
    {
        $expectedProgress = [
            'overall_progress' => ['completion_percentage' => 75],
            'category_progress' => [],
            'remaining_requirements' => [],
        ];

        $this->creditProgressService->shouldReceive('getCreditProgress')
            ->with($this->student)
            ->andReturn($expectedProgress);

        $result = $this->dashboardService->getCreditProgress($this->student);

        $this->assertEquals($expectedProgress, $result);
    }

    public function test_get_academic_holds_returns_structured_data(): void
    {
        // Create test academic holds
        AcademicHold::factory()->create([
            'student_id' => $this->student->id,
            'status' => 'active',
            'hold_type' => 'financial',
            'hold_category' => 'registration',
            'title' => 'Outstanding Tuition',
            'priority' => 'high',
        ]);

        AcademicHold::factory()->create([
            'student_id' => $this->student->id,
            'status' => 'active',
            'hold_type' => 'academic',
            'hold_category' => 'graduation',
            'title' => 'Missing Transcript',
            'priority' => 'medium',
        ]);

        $result = $this->dashboardService->getAcademicHolds($this->student);

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['total_holds']);
        $this->assertEquals(1, $result['blocking_registration']);
        $this->assertEquals(1, $result['blocking_graduation']);
        $this->assertCount(2, $result['holds']);

        $firstHold = $result['holds'][0];
        $this->assertArrayHasKey('id', $firstHold);
        $this->assertArrayHasKey('type', $firstHold);
        $this->assertArrayHasKey('category', $firstHold);
        $this->assertArrayHasKey('title', $firstHold);
        $this->assertArrayHasKey('priority', $firstHold);
    }

    public function test_dashboard_data_is_cached(): void
    {
        // Mock services for first call
        $this->gpaService->shouldReceive('calculateCurrentGPA')->once()->andReturn(['gpa' => 3.5]);
        $this->gpaService->shouldReceive('getGPATrend')->once()->andReturn(['trend_data' => []]);
        $this->gpaService->shouldReceive('getGradeDistribution')->once()->andReturn(['distribution' => []]);
        $this->gpaService->shouldReceive('getAcademicStanding')->once()->andReturn(['standing' => 'good']);
        $this->creditProgressService->shouldReceive('getCreditProgress')->once()->andReturn(['overall_progress' => []]);

        // Clear cache first
        Cache::forget("dashboard:student:{$this->student->id}");

        // First call should hit the services
        $result1 = $this->dashboardService->getDashboardData($this->student);

        // Second call should use cache (services shouldn't be called again)
        $result2 = $this->dashboardService->getDashboardData($this->student);

        $this->assertEquals($result1, $result2);
    }
}
