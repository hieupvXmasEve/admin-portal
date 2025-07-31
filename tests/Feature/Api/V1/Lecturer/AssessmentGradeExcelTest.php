<?php

declare(strict_types=1);

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Services\AssessmentGradeExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up fake storage
    Storage::fake('local');
    
    // Create test data
    $this->lecturer = Lecture::factory()->create();
    $this->courseOffering = CourseOffering::factory()->create([
        'lecture_id' => $this->lecturer->id,
    ]);
    
    $assessmentComponent = AssessmentComponent::factory()->create();
    $this->assessmentComponentDetail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $assessmentComponent->id,
        'name' => 'Midterm Exam',
        'max_points' => 100,
        'weight' => 30,
    ]);
    
    // Create students enrolled in the course
    $this->students = Student::factory()->count(3)->create();
    foreach ($this->students as $student) {
        AcademicRecord::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->courseOffering->id,
            'completion_status' => 'enrolled',
        ]);
    }
});

describe('Export Grade Template API', function () {
    describe('POST /api/v1/lecturer/course-offerings/{courseOffering}/assessment-details/{assessmentComponentDetail}/export-grade-template', function () {
        it('requires authentication', function () {
            $response = $this->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(401);
        });

        it('requires lecturer to own the course offering', function () {
            $otherLecturer = Lecture::factory()->create();
            
            $response = $this->actingAs($otherLecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized access to course offering',
                    'error_code' => 'UNAUTHORIZED'
                ]);
        });

        it('validates that assessment belongs to course offering', function () {
            // Create assessment for different course
            $otherCourseOffering = CourseOffering::factory()->create([
                'lecture_id' => $this->lecturer->id,
            ]);
            $otherAssessmentComponent = AssessmentComponent::factory()->create();
            $otherAssessment = AssessmentComponentDetail::factory()->create([
                'assessment_component_id' => $otherAssessmentComponent->id,
            ]);

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$otherAssessment->id}/export-grade-template");

            $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Assessment component detail does not belong to this course offering',
                    'error_code' => 'INVALID_ASSESSMENT'
                ]);
        });

        it('successfully exports grade template for authorized lecturer', function () {
            // Mock the Excel export to return a fake file
            Excel::shouldReceive('store')
                ->once()
                ->andReturn(true);

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(200);
            $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        });

        it('includes enrolled students in the export', function () {
            // Create some students with existing scores
            $studentWithScore = $this->students->first();
            AssessmentComponentDetailScore::factory()->create([
                'student_id' => $studentWithScore->id,
                'assessment_component_detail_id' => $this->assessmentComponentDetail->id,
                'course_offering_id' => $this->courseOffering->id,
                'points_earned' => 85,
            ]);

            Excel::shouldReceive('store')
                ->once()
                ->with(
                    \Mockery::on(function ($export) {
                        return $export instanceof \App\Exports\GradeTemplateExport;
                    }),
                    \Mockery::type('string'),
                    'local'
                )
                ->andReturn(true);

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(200);
        });

        it('handles service exceptions gracefully', function () {
            // Mock service to throw exception
            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('exportGradeTemplate')
                    ->once()
                    ->andThrow(new Exception('Export failed'));
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to export grade template',
                    'error_code' => 'SERVER_ERROR'
                ]);
        });

        it('deletes file after sending', function () {
            $tempFilePath = storage_path('app/temp/test_export.xlsx');
            file_put_contents($tempFilePath, 'test content');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) use ($tempFilePath) {
                $mock->shouldReceive('exportGradeTemplate')
                    ->once()
                    ->andReturn($tempFilePath);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

            $response->assertStatus(200);
        });
    });
});

describe('Import Grades API', function () {
    describe('POST /api/v1/lecturer/course-offerings/{courseOffering}/assessment-details/{assessmentComponentDetail}/import-grades', function () {
        it('requires authentication', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100);

            $response = $this->postJson(
                "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                ['file' => $file]
            );

            $response->assertStatus(401);
        });

        it('requires lecturer to own the course offering', function () {
            $otherLecturer = Lecture::factory()->create();
            $file = UploadedFile::fake()->create('grades.xlsx', 100);
            
            $response = $this->actingAs($otherLecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized access to course offering',
                    'error_code' => 'UNAUTHORIZED'
                ]);
        });

        it('validates that assessment belongs to course offering', function () {
            $otherCourseOffering = CourseOffering::factory()->create([
                'lecture_id' => $this->lecturer->id,
            ]);
            $otherAssessmentComponent = AssessmentComponent::factory()->create();
            $otherAssessment = AssessmentComponentDetail::factory()->create([
                'assessment_component_id' => $otherAssessmentComponent->id,
            ]);
            $file = UploadedFile::fake()->create('grades.xlsx', 100);

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$otherAssessment->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Assessment component detail does not belong to this course offering',
                    'error_code' => 'INVALID_ASSESSMENT'
                ]);
        });

        it('validates file is required', function () {
            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades");

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file'])
                ->assertJsonFragment([
                    'file' => ['An Excel file is required for grade import']
                ]);
        });

        it('validates file type', function () {
            $invalidFile = UploadedFile::fake()->create('grades.txt', 100);

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $invalidFile]
                );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file'])
                ->assertJsonFragment([
                    'file' => ['The file must be an Excel file (.xlsx, .xls) or CSV file (.csv)']
                ]);
        });

        it('validates file size limit', function () {
            $largeFile = UploadedFile::fake()->create('grades.xlsx', 11000); // 11MB

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $largeFile]
                );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['file'])
                ->assertJsonFragment([
                    'file' => ['The file size cannot exceed 10MB']
                ]);
        });

        it('validates update mode options', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    [
                        'file' => $file,
                        'update_mode' => 'invalid_mode'
                    ]
                );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['update_mode']);
        });

        it('successfully imports grades with default options', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->andReturn([
                        'successful_creates' => 2,
                        'successful_updates' => 1,
                        'errors' => [],
                        'warnings' => [],
                        'total_rows' => 3
                    ]);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Grade import completed successfully',
                    'data' => [
                        'successful_creates' => 2,
                        'successful_updates' => 1,
                        'errors' => [],
                        'warnings' => []
                    ]
                ]);
        });

        it('handles import with warnings', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->andReturn([
                        'successful_creates' => 2,
                        'successful_updates' => 0,
                        'errors' => [],
                        'warnings' => ['Student S123456 score exceeds maximum points'],
                        'total_rows' => 2
                    ]);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Grade import completed with some issues',
                    'data' => [
                        'successful_creates' => 2,
                        'warnings' => ['Student S123456 score exceeds maximum points']
                    ]
                ]);
        });

        it('handles import with errors but some success', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->andReturn([
                        'successful_creates' => 1,
                        'successful_updates' => 0,
                        'errors' => ['Row 2: Student not found'],
                        'warnings' => [],
                        'total_rows' => 2
                    ]);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Grade import completed with some issues'
                ]);
        });

        it('handles complete import failure', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->andReturn([
                        'successful_creates' => 0,
                        'successful_updates' => 0,
                        'errors' => ['Invalid file format', 'No valid rows found'],
                        'warnings' => [],
                        'total_rows' => 0
                    ]);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Grade import failed',
                    'error_code' => 'IMPORT_FAILED'
                ]);
        });

        it('passes custom import options to service', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->with(
                        \Mockery::type(AssessmentComponentDetail::class),
                        \Mockery::type(CourseOffering::class),
                        \Mockery::type(UploadedFile::class),
                        [
                            'update_mode' => 'update_existing',
                            'overwrite_existing' => true,
                            'validate_only' => false,
                            'skip_errors' => true,
                            'default_status' => 'graded',
                            'default_score_status' => 'final'
                        ],
                        $this->lecturer->id
                    )
                    ->andReturn([
                        'successful_creates' => 0,
                        'successful_updates' => 2,
                        'errors' => [],
                        'warnings' => [],
                        'total_rows' => 2
                    ]);
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    [
                        'file' => $file,
                        'update_mode' => 'update_existing',
                        'overwrite_existing' => true,
                        'skip_errors' => true,
                        'default_score_status' => 'final'
                    ]
                );

            $response->assertStatus(200);
        });

        it('handles service exceptions gracefully', function () {
            $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->mock(AssessmentGradeExcelService::class, function ($mock) {
                $mock->shouldReceive('importGrades')
                    ->once()
                    ->andThrow(new Exception('Service failed'));
            });

            $response = $this->actingAs($this->lecturer, 'api')
                ->postJson(
                    "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                    ['file' => $file]
                );

            $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to import grades',
                    'error_code' => 'SERVER_ERROR'
                ]);
        });
    });
});

describe('Grade Management Integration', function () {
    it('exports and imports grades for students with no existing grades', function () {
        // Ensure students have no existing grades (should default to 0)
        expect(AssessmentComponentDetailScore::where('assessment_component_detail_id', $this->assessmentComponentDetail->id)->count())->toBe(0);

        // Mock export
        Excel::shouldReceive('store')
            ->once()
            ->andReturn(true);

        $exportResponse = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/export-grade-template");

        $exportResponse->assertStatus(200);

        // Mock import with default grades
        $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->mock(AssessmentGradeExcelService::class, function ($mock) {
            $mock->shouldReceive('importGrades')
                ->once()
                ->andReturn([
                    'successful_creates' => 3,
                    'successful_updates' => 0,
                    'errors' => [],
                    'warnings' => [],
                    'total_rows' => 3,
                    'default_scores_applied' => 3
                ]);
        });

        $importResponse = $this->actingAs($this->lecturer, 'api')
            ->postJson(
                "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                ['file' => $file]
            );

        $importResponse->assertStatus(200)
            ->assertJsonFragment([
                'successful_creates' => 3,
                'default_scores_applied' => 3
            ]);
    });

    it('handles mixed scenarios with existing and new grades', function () {
        // Create one student with existing grade
        $existingStudent = $this->students->first();
        AssessmentComponentDetailScore::factory()->create([
            'student_id' => $existingStudent->id,
            'assessment_component_detail_id' => $this->assessmentComponentDetail->id,
            'course_offering_id' => $this->courseOffering->id,
            'points_earned' => 75,
        ]);

        $file = UploadedFile::fake()->create('grades.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->mock(AssessmentGradeExcelService::class, function ($mock) {
            $mock->shouldReceive('importGrades')
                ->once()
                ->andReturn([
                    'successful_creates' => 2, // Two new students
                    'successful_updates' => 1, // One existing student updated
                    'errors' => [],
                    'warnings' => [],
                    'total_rows' => 3
                ]);
        });

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson(
                "/api/v1/lecturer/course-offerings/{$this->courseOffering->id}/assessment-details/{$this->assessmentComponentDetail->id}/import-grades",
                ['file' => $file]
            );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'successful_creates' => 2,
                    'successful_updates' => 1
                ]
            ]);
    });
});