<?php

declare(strict_types=1);

namespace Tests\Unit\Services\V1\Student;

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Repositories\V1\Student\ClassSessionRepository;
use App\Services\V1\Student\TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TimetableServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TimetableService $timetableService;

    protected ClassSessionRepository $classSessionRepository;

    protected Student $student;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock repository
        $this->classSessionRepository = Mockery::mock(ClassSessionRepository::class);
        $this->timetableService = new TimetableService($this->classSessionRepository);

        // Create test data
        $this->createTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createTestData(): void
    {
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);

        $this->student = Student::factory()->create([
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);

        $this->semester = Semester::factory()->create([
            'is_active' => true,
            'name' => 'Spring 2024',
            'code' => 'SP24',
        ]);
    }

    public function test_get_student_timetable_returns_complete_structure(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->with($this->student, $this->semester, [])
            ->andReturn($mockClassSessions);

        // Clear cache to ensure fresh data
        Cache::flush();

        $result = $this->timetableService->getStudentTimetable($this->student, $this->semester->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('semester', $result);
        $this->assertArrayHasKey('weekly_schedule', $result);
        $this->assertArrayHasKey('schedule_summary', $result);
        $this->assertArrayHasKey('time_blocks', $result);
        $this->assertArrayHasKey('filters_applied', $result);

        // Check semester information
        $this->assertEquals($this->semester->id, $result['semester']['id']);
        $this->assertEquals('Spring 2024', $result['semester']['name']);
        $this->assertEquals('SP24', $result['semester']['code']);
    }

    public function test_get_weekly_timetable_returns_weekly_view(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->with($this->student, $this->semester, [])
            ->andReturn($mockClassSessions);

        Cache::flush();

        $result = $this->timetableService->getWeeklyTimetable($this->student, $this->semester->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('semester', $result);
        $this->assertArrayHasKey('weekly_schedule', $result);
        $this->assertArrayHasKey('schedule_summary', $result);
        $this->assertArrayNotHasKey('time_blocks', $result); // Should not include time blocks
    }

    public function test_weekly_schedule_has_all_days(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->andReturn($mockClassSessions);

        Cache::flush();

        $result = $this->timetableService->getWeeklyTimetable($this->student, $this->semester->id);
        $weeklySchedule = $result['weekly_schedule'];

        $expectedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($expectedDays as $day) {
            $this->assertArrayHasKey($day, $weeklySchedule);
            $this->assertArrayHasKey('day_name', $weeklySchedule[$day]);
            $this->assertArrayHasKey('sessions', $weeklySchedule[$day]);
            $this->assertEquals(ucfirst($day), $weeklySchedule[$day]['day_name']);
        }
    }

    public function test_get_class_session_detail_returns_complete_info(): void
    {
        $classSession = $this->createRealClassSession();

        $this->classSessionRepository->shouldReceive('isStudentEnrolledInSession')
            ->with($this->student, $classSession)
            ->andReturn(true);

        $result = $this->timetableService->getClassSessionDetail($classSession, $this->student);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('session', $result);
        $this->assertArrayHasKey('course', $result);
        $this->assertArrayHasKey('lecturer', $result);
        $this->assertArrayHasKey('room', $result);
        $this->assertArrayHasKey('attendance_info', $result);
        $this->assertArrayHasKey('upcoming_sessions', $result);

        // Check session details
        $this->assertEquals($classSession->id, $result['session']['id']);
        $this->assertEquals($classSession->day_of_week, $result['session']['day_of_week']);
        $this->assertEquals($classSession->start_time, $result['session']['start_time']);
        $this->assertEquals($classSession->end_time, $result['session']['end_time']);
    }

    public function test_get_class_session_detail_throws_exception_for_unenrolled_student(): void
    {
        $classSession = $this->createRealClassSession();

        $this->classSessionRepository->shouldReceive('isStudentEnrolledInSession')
            ->with($this->student, $classSession)
            ->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Student is not enrolled in this class session');

        $this->timetableService->getClassSessionDetail($classSession, $this->student);
    }

    public function test_get_filter_options_returns_available_filters(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->with($this->student, $this->semester)
            ->andReturn($mockClassSessions);

        $result = $this->timetableService->getFilterOptions($this->student, $this->semester->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('days_of_week', $result);
        $this->assertArrayHasKey('session_types', $result);
        $this->assertArrayHasKey('lecturers', $result);
        $this->assertArrayHasKey('buildings', $result);
        $this->assertArrayHasKey('time_slots', $result);
    }

    public function test_timetable_data_is_cached(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        // Should only be called once due to caching
        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->once()
            ->with($this->student, $this->semester, [])
            ->andReturn($mockClassSessions);

        Cache::flush();

        // First call should hit the repository
        $result1 = $this->timetableService->getStudentTimetable($this->student, $this->semester->id);

        // Second call should use cache
        $result2 = $this->timetableService->getStudentTimetable($this->student, $this->semester->id);

        $this->assertEquals($result1, $result2);
    }

    public function test_returns_empty_timetable_when_no_active_semester(): void
    {
        // Make sure no active semester exists
        Semester::query()->update(['is_active' => false]);

        $result = $this->timetableService->getStudentTimetable($this->student);

        $this->assertIsArray($result);
        $this->assertNull($result['semester']);
        $this->assertEmpty($result['weekly_schedule']);
        $this->assertEquals(0, $result['schedule_summary']['total_sessions_per_week']);
        $this->assertEquals(0, $result['schedule_summary']['unique_courses']);
        $this->assertEquals(0, $result['schedule_summary']['total_hours_per_week']);
    }

    public function test_schedule_summary_calculates_correctly(): void
    {
        $mockClassSessions = $this->createMockClassSessions();

        $this->classSessionRepository->shouldReceive('getStudentClassSessions')
            ->andReturn($mockClassSessions);

        Cache::flush();

        $result = $this->timetableService->getStudentTimetable($this->student, $this->semester->id);
        $summary = $result['schedule_summary'];

        $this->assertEquals(2, $summary['total_sessions_per_week']);
        $this->assertEquals(2, $summary['unique_courses']);
        $this->assertIsFloat($summary['total_hours_per_week']);
        $this->assertArrayHasKey('day_distribution', $summary);
        $this->assertArrayHasKey('session_type_distribution', $summary);
    }

    protected function createMockClassSessions(): Collection
    {
        // Create mock class sessions for testing
        $unit1 = Unit::factory()->create(['code' => 'CS101', 'name' => 'Introduction to Computer Science']);
        $unit2 = Unit::factory()->create(['code' => 'MATH101', 'name' => 'Calculus I']);

        $curriculumUnit1 = CurriculumUnit::factory()->create(['unit_id' => $unit1->id]);
        $curriculumUnit2 = CurriculumUnit::factory()->create(['unit_id' => $unit2->id]);

        $lecturer = Lecturer::factory()->create(['full_name' => 'Dr. Smith']);
        $room = Room::factory()->create(['code' => 'A101', 'building' => 'Building A']);

        $courseOffering1 = CourseOffering::factory()->create([
            'curriculum_unit_id' => $curriculumUnit1->id,
            'lecturer_id' => $lecturer->id,
        ]);

        $courseOffering2 = CourseOffering::factory()->create([
            'curriculum_unit_id' => $curriculumUnit2->id,
            'lecturer_id' => $lecturer->id,
        ]);

        $session1 = ClassSession::factory()->create([
            'course_offering_id' => $courseOffering1->id,
            'room_id' => $room->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'session_type' => 'lecture',
        ]);

        $session2 = ClassSession::factory()->create([
            'course_offering_id' => $courseOffering2->id,
            'room_id' => $room->id,
            'day_of_week' => 'wednesday',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_type' => 'tutorial',
        ]);

        return collect([$session1, $session2]);
    }

    protected function createRealClassSession(): ClassSession
    {
        $unit = Unit::factory()->create();
        $curriculumUnit = CurriculumUnit::factory()->create(['unit_id' => $unit->id]);
        $lecturer = Lecturer::factory()->create();
        $room = Room::factory()->create();

        $courseOffering = CourseOffering::factory()->create([
            'curriculum_unit_id' => $curriculumUnit->id,
            'lecturer_id' => $lecturer->id,
        ]);

        return ClassSession::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'room_id' => $room->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
    }
}
