<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\Syllabus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentGradingTest extends TestCase
{
    use RefreshDatabase;

    private Lecture $lecturer;
    private CourseOffering $courseOffering;
    private Student $student;
    private AssessmentComponent $assessmentComponent;
    private AssessmentComponentDetail $assessmentDetail;
    private AssessmentComponentDetailScore $score;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->lecturer = Lecture::factory()->create();
        $this->courseOffering = CourseOffering::factory()->create([
            'lecture_id' => $this->lecturer->id
        ]);

        $syllabus = Syllabus::factory()->create();
        $this->courseOffering->update(['syllabus_id' => $syllabus->id]);

        $this->student = Student::factory()->create();

        // Enroll student in course
        CourseRegistration::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => $this->student->id
        ]);

        $this->assessmentComponent = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30.0
        ]);

        $this->assessmentDetail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $this->assessmentComponent->id,
            'weight' => 100.0,
            'max_points' => 100
        ]);

        $this->score = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $this->assessmentDetail->id,
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => $this->student->id,
            'points_earned' => null,
            'percentage_score' => null,
            'score_status' => 'draft'
        ]);
    }

    public function test_can_get_grading_data_by_student(): void
    {
        $response = $this->actingAs($this->lecturer)
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/grade/student/{$this->student->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'student' => [
                        'id',
                        'student_id',
                        'name',
                        'first_name',
                        'last_name',
                        'email'
                    ],
                    'assessments' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'weight',
                            'details' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'weight',
                                    'score'
                                ]
                            ]
                        ]
                    ],
                    'summary' => [
                        'total_assessments',
                        'completed_assessments',
                        'pending_assessments',
                        'overall_percentage'
                    ]
                ]
            ]);
    }

    public function test_can_get_grading_data_by_component(): void
    {
        $response = $this->actingAs($this->lecturer)
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/grade/component/{$this->assessmentComponent->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'assessment_component' => [
                        'id',
                        'name',
                        'type',
                        'weight'
                    ],
                    'details' => [
                        '*' => [
                            'id',
                            'name',
                            'weight',
                            'student_scores' => [
                                '*' => [
                                    'student' => [
                                        'id',
                                        'name',
                                        'student_id'
                                    ],
                                    'score'
                                ]
                            ],
                            'statistics'
                        ]
                    ],
                    'statistics'
                ]
            ]);
    }

    public function test_can_update_individual_grade(): void
    {
        $updateData = [
            'points_earned' => 85,
            'percentage_score' => 85.0,
            'letter_grade' => 'B+',
            'score_status' => 'final',
            'instructor_feedback' => 'Good work!'
        ];

        $response = $this->actingAs($this->lecturer)
            ->putJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/scores/{$this->score->id}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.points_earned', 85)
            ->assertJsonPath('data.percentage_score', 85.0)
            ->assertJsonPath('data.letter_grade', 'B+')
            ->assertJsonPath('data.score_status', 'final')
            ->assertJsonPath('data.instructor_feedback', 'Good work!');

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'points_earned' => 85,
            'percentage_score' => 85.0,
            'letter_grade' => 'B+',
            'score_status' => 'final',
            'instructor_feedback' => 'Good work!',
            'graded_by_lecture_id' => $this->lecturer->id
        ]);
    }

    public function test_can_bulk_update_grades(): void
    {
        // Create another score for bulk testing
        $secondScore = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $this->assessmentDetail->id,
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => Student::factory()->create()->id,
            'points_earned' => null,
            'percentage_score' => null,
            'score_status' => 'draft'
        ]);

        $bulkData = [
            'scores' => [
                [
                    'id' => $this->score->id,
                    'points_earned' => 90,
                    'percentage_score' => 90.0,
                    'score_status' => 'final'
                ],
                [
                    'id' => $secondScore->id,
                    'points_earned' => 75,
                    'percentage_score' => 75.0,
                    'score_status' => 'final'
                ]
            ]
        ];

        $response = $this->actingAs($this->lecturer)
            ->postJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/scores/bulk-update", $bulkData);

        $response->assertOk()
            ->assertJsonPath('data.total_updated', 2);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'points_earned' => 90,
            'percentage_score' => 90.0,
            'score_status' => 'final'
        ]);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $secondScore->id,
            'points_earned' => 75,
            'percentage_score' => 75.0,
            'score_status' => 'final'
        ]);
    }

    public function test_validates_grade_update_constraints(): void
    {
        $invalidData = [
            'points_earned' => 150, // Exceeds max_points (100)
            'percentage_score' => 150.0 // Exceeds 100%
        ];

        $response = $this->actingAs($this->lecturer)
            ->putJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/scores/{$this->score->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['points_earned', 'percentage_score']);
    }

    public function test_unauthorized_lecturer_cannot_access_grades(): void
    {
        $unauthorizedLecturer = Lecture::factory()->create();

        $response = $this->actingAs($unauthorizedLecturer)
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/grade/student/{$this->student->id}");

        $response->assertStatus(403);
    }
}
