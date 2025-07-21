<?php

namespace Tests\Unit\Services\AcademicLifecycleSeeder;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AcademicLifecycleSeeder\Processors\GroupAssignmentProcessor;
use App\Services\AcademicLifecycleSeeder\SeederConfiguration;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\Semester;
use App\Models\CurriculumUnit;
use App\Models\Unit;
use App\Models\CurriculumVersion;
use App\Models\Lecture;

/**
 * Test the critical round-robin distribution algorithm
 * 
 * This tests the core requirement that students are distributed evenly
 * across course sections with maximum 1 student difference between sections.
 */
class GroupAssignmentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private GroupAssignmentProcessor $processor;
    private SeederConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processor = new GroupAssignmentProcessor();
        $this->configuration = new SeederConfiguration([
            'maxStudentsPerSection' => 55,
        ]);
    }

    /** @test */
    public function it_splits_large_courses_into_sections()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(60); // Over the limit

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(1, $result['sections'], 'Should create 1 additional section (original + 1 new = 2 total)');
        $this->assertEquals(60, $result['students_reassigned'], 'All 60 students should be redistributed');
    }

    /** @test */
    public function it_distributes_students_evenly_with_round_robin()
    {
        // Arrange: Create course with 100 students (should split into 2 sections)
        $courseOffering = $this->createCourseOfferingWithStudents(100);

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(1, $result['sections'], 'Should create 1 additional section');

        // Verify even distribution
        $originalCount = CourseRegistration::where('course_offering_id', $courseOffering->id)->count();
        $newSectionCount = CourseRegistration::where('course_offering_id', $result['sections']->first()->id)->count();

        // With 100 students split into 2 sections: 50 each
        $this->assertEquals(50, $originalCount, 'Original section should have 50 students');
        $this->assertEquals(50, $newSectionCount, 'New section should have 50 students');
        
        // Verify maximum difference is 0 (perfect distribution)
        $difference = abs($originalCount - $newSectionCount);
        $this->assertLessThanOrEqual(1, $difference, 'Difference between sections should be at most 1');
    }

    /** @test */
    public function it_handles_uneven_distribution_correctly()
    {
        // Arrange: Create course with 101 students (should split into 2 sections: 51 and 50)
        $courseOffering = $this->createCourseOfferingWithStudents(101);

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $originalCount = CourseRegistration::where('course_offering_id', $courseOffering->id)->count();
        $newSectionCount = CourseRegistration::where('course_offering_id', $result['sections']->first()->id)->count();

        // With 101 students: one section gets 51, other gets 50
        $this->assertTrue(
            ($originalCount === 51 && $newSectionCount === 50) || 
            ($originalCount === 50 && $newSectionCount === 51),
            'Sections should have 50 and 51 students respectively'
        );

        // Verify maximum difference is exactly 1
        $difference = abs($originalCount - $newSectionCount);
        $this->assertEquals(1, $difference, 'Difference should be exactly 1 for uneven distribution');
    }

    /** @test */
    public function it_creates_three_sections_for_large_courses()
    {
        // Arrange: Create course with 150 students (should split into 3 sections: 50 each)
        $courseOffering = $this->createCourseOfferingWithStudents(150);

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(2, $result['sections'], 'Should create 2 additional sections (3 total)');

        // Get all sections
        $allSections = collect([$courseOffering])->merge($result['sections']);
        
        // Verify each section has exactly 50 students
        foreach ($allSections as $section) {
            $count = CourseRegistration::where('course_offering_id', $section->id)->count();
            $this->assertEquals(50, $count, "Each section should have exactly 50 students");
        }
    }

    /** @test */
    public function it_validates_even_distribution()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(77); // Should create 2 sections: 39 and 38

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);
        $allSections = collect([$courseOffering])->merge($result['sections']);

        // Assert - the validateEvenDistribution method should pass
        $this->assertTrue($this->processor->validateEvenDistribution($allSections));
    }

    /** @test */
    public function it_assigns_correct_section_codes()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(100);

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $courseOffering->refresh();
        $this->assertEquals('A', $courseOffering->section_code, 'Original section should be labeled A');
        $this->assertEquals('B', $result['sections']->first()->section_code, 'New section should be labeled B');
    }

    /** @test */
    public function it_does_not_split_small_courses()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(30); // Under the limit

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(0, $result['sections'], 'Should not create additional sections for small courses');
        $this->assertEquals(0, $result['students_reassigned'], 'No students should be reassigned');
    }

    /** @test */
    public function it_handles_edge_case_at_exact_limit()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(55); // Exactly at the limit

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(0, $result['sections'], 'Should not split course at exact limit');
    }

    /** @test */
    public function it_handles_edge_case_just_over_limit()
    {
        // Arrange
        $courseOffering = $this->createCourseOfferingWithStudents(56); // Just over the limit

        // Act
        $result = $this->processor->splitCourseWithRoundRobin($courseOffering, 55);

        // Assert
        $this->assertCount(1, $result['sections'], 'Should create 1 additional section');
        
        // Verify distribution: 28 and 28
        $originalCount = CourseRegistration::where('course_offering_id', $courseOffering->id)->count();
        $newSectionCount = CourseRegistration::where('course_offering_id', $result['sections']->first()->id)->count();
        
        $this->assertEquals(28, $originalCount, 'Each section should have 28 students');
        $this->assertEquals(28, $newSectionCount, 'Each section should have 28 students');
    }

    /**
     * Helper method to create a course offering with specified number of students
     */
    private function createCourseOfferingWithStudents(int $studentCount): CourseOffering
    {
        // Create necessary related models
        $curriculumVersion = CurriculumVersion::factory()->create();
        $unit = Unit::factory()->create();
        $curriculumUnit = CurriculumUnit::factory()->create([
            'curriculum_version_id' => $curriculumVersion->id,
            'unit_id' => $unit->id,
        ]);
        $semester = Semester::factory()->create();
        $lecturer = Lecture::factory()->create();

        $courseOffering = CourseOffering::factory()->create([
            'curriculum_unit_id' => $curriculumUnit->id,
            'semester_id' => $semester->id,
            'lecture_id' => $lecturer->id,
            'max_capacity' => $studentCount + 10, // Ensure capacity is sufficient
        ]);

        // Create students and register them for the course
        for ($i = 0; $i < $studentCount; $i++) {
            $student = Student::factory()->create([
                'curriculum_version_id' => $curriculumVersion->id,
            ]);

            CourseRegistration::factory()->create([
                'student_id' => $student->id,
                'course_offering_id' => $courseOffering->id,
                'semester_id' => $semester->id,
            ]);
        }

        return $courseOffering;
    }
}
