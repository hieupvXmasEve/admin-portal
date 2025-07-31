<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Student;
use App\Services\AssessmentGradeExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcelGradeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Lecture $lecturer;
    protected CourseOffering $courseOffering;
    protected AssessmentComponentDetail $assessmentComponentDetail;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->lecturer = Lecture::factory()->create();
        $this->courseOffering = CourseOffering::factory()->create([
            'lecture_id' => $this->lecturer->id
        ]);
        
        $assessmentComponent = AssessmentComponent::factory()->create();
        $this->assessmentComponentDetail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assessmentComponent->id,
            'max_points' => 100
        ]);
        
        $this->student = Student::factory()->create();
        
        // Set up storage for testing
        Storage::fake('local');
    }

    /** @test */
    public function it_can_export_grade_template()
    {
        $this->actingAs($this->lecturer, 'sanctum');

        $response = $this->get(route('api.v1.lecturer.assessments.export-grade-template', [
            'courseOffering' => $this->courseOffering->id,
            'assessmentComponentDetail' => $this->assessmentComponentDetail->id
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_validates_excel_file_upload()
    {
        $this->actingAs($this->lecturer, 'sanctum');

        // Test with invalid file type
        $invalidFile = UploadedFile::fake()->create('grades.txt', 100);

        $response = $this->post(route('api.v1.lecturer.assessments.import-grades', [
            'courseOffering' => $this->courseOffering->id,
            'assessmentComponentDetail' => $this->assessmentComponentDetail->id
        ]), [
            'file' => $invalidFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_validates_file_size_limit()
    {
        $this->actingAs($this->lecturer, 'sanctum');

        // Test with file too large (over 10MB)
        $largeFile = UploadedFile::fake()->create('grades.xlsx', 11000); // 11MB

        $response = $this->post(route('api.v1.lecturer.assessments.import-grades', [
            'courseOffering' => $this->courseOffering->id,
            'assessmentComponentDetail' => $this->assessmentComponentDetail->id
        ]), [
            'file' => $largeFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->get(route('api.v1.lecturer.assessments.export-grade-template', [
            'courseOffering' => $this->courseOffering->id,
            'assessmentComponentDetail' => $this->assessmentComponentDetail->id
        ]));

        $response->assertStatus(401);
    }

    /** @test */
    public function it_checks_lecturer_authorization()
    {
        $otherLecturer = Lecture::factory()->create();
        $this->actingAs($otherLecturer, 'sanctum');

        $response = $this->get(route('api.v1.lecturer.assessments.export-grade-template', [
            'courseOffering' => $this->courseOffering->id,
            'assessmentComponentDetail' => $this->assessmentComponentDetail->id
        ]));

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'UNAUTHORIZED'
        ]);
    }

    /** @test */
    public function excel_service_can_generate_template()
    {
        $service = new AssessmentGradeExcelService();
        
        $filePath = $service->exportGradeTemplate(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertFileExists($filePath);
        $this->assertStringContainsString('.xlsx', $filePath);
        
        // Clean up
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /** @test */
    public function excel_service_validates_import_file()
    {
        $service = new AssessmentGradeExcelService();
        
        // Test with invalid file
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 100);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File must be an Excel file');
        
        $service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $invalidFile,
            [],
            $this->lecturer->id
        );
    }

    /** @test */
    public function it_provides_import_statistics()
    {
        $service = new AssessmentGradeExcelService();
        
        $statistics = $service->getImportStatistics(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertArrayHasKey('total_students', $statistics);
        $this->assertArrayHasKey('graded_students', $statistics);
        $this->assertArrayHasKey('ungraded_students', $statistics);
        $this->assertArrayHasKey('grading_completion_percentage', $statistics);
        $this->assertArrayHasKey('assessment_info', $statistics);
    }
}
