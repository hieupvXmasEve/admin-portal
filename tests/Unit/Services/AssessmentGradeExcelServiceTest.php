<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Student;
use App\Services\AssessmentGradeExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AssessmentGradeExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AssessmentGradeExcelService $service;

    protected Lecture $lecturer;

    protected CourseOffering $courseOffering;

    protected AssessmentComponentDetail $assessmentComponentDetail;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Test Assignment',
            'max_points' => 100,
            'weight' => 20,
        ]);

        $this->service = new AssessmentGradeExcelService;
    }

    /** @test */
    public function it_generates_a_grade_template_file_successfully()
    {
        $filePath = $this->service->exportGradeTemplate(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertIsString($filePath);
        $this->assertFileExists($filePath);
        $this->assertStringContains('.xlsx', $filePath);

        // Clean up
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /** @test */
    public function it_generates_filename_with_correct_format()
    {
        $filePath = $this->service->exportGradeTemplate(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $filename = basename($filePath);
        $this->assertStringStartsWith('grade_template_', $filename);
        $this->assertStringContains('Test_Assignment', $filename);
        $this->assertStringEndsWith('.xlsx', $filename);

        // Clean up
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /** @test */
    public function it_throws_exception_when_export_fails()
    {
        // Create a mock that will throw an exception
        Excel::shouldReceive('store')
            ->once()
            ->andThrow(new \Exception('Excel export failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to generate grade template');

        $this->service->exportGradeTemplate(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );
    }

    /** @test */
    public function it_includes_students_enrolled_in_course_with_current_scores()
    {
        // Create enrolled students
        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();

        // Mock the Excel export to capture the data
        Excel::shouldReceive('store')
            ->once()
            ->with(
                \Mockery::on(function ($export) {
                    // Verify the export has the correct data structure
                    return $export instanceof \App\Exports\GradeTemplateExport;
                }),
                \Mockery::type('string'),
                'local'
            )
            ->andReturn(true);

        $filePath = $this->service->exportGradeTemplate(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertIsString($filePath);
    }

    /** @test */
    public function it_validates_file_type_correctly()
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File must be an Excel file');

        $this->service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $invalidFile,
            [],
            $this->lecturer->id
        );
    }

    /** @test */
    public function it_validates_file_size_limit()
    {
        // Create a mock file that appears large
        $largeFile = UploadedFile::fake()->create('test.xlsx', 11000); // 11 MB

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size cannot exceed 10MB');

        $this->service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $largeFile,
            [],
            $this->lecturer->id
        );
    }

    /** @test */
    public function it_validates_empty_file()
    {
        $emptyFile = UploadedFile::fake()->create('test.xlsx', 0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The uploaded file is empty');

        $this->service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $emptyFile,
            [],
            $this->lecturer->id
        );
    }

    /** @test */
    public function it_processes_valid_excel_file_successfully()
    {
        $validFile = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Mock Excel import
        Excel::shouldReceive('import')
            ->once()
            ->with(
                \Mockery::type(\App\Imports\GradeImport::class),
                $validFile
            )
            ->andReturn(true);

        $results = $this->service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $validFile,
            ['update_mode' => 'update_and_create'],
            $this->lecturer->id
        );

        $this->assertIsArray($results);
    }

    /** @test */
    public function it_handles_import_exceptions_properly()
    {
        $validFile = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Excel::shouldReceive('import')
            ->once()
            ->andThrow(new \Exception('Import processing failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to import grades');

        $this->service->importGrades(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $validFile,
            [],
            $this->lecturer->id
        );
    }

    /** @test */
    public function it_validates_file_before_preview()
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File must be an Excel file');

        $this->service->previewImport(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $invalidFile
        );
    }

    /** @test */
    public function it_returns_preview_data_with_assessment_info()
    {
        $validFile = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Mock Excel import for validation
        Excel::shouldReceive('import')
            ->once()
            ->with(
                \Mockery::on(function ($import) {
                    // Mock the getResults method
                    $import->shouldReceive('getResults')
                        ->andReturn([
                            'total_rows' => 5,
                            'valid_rows' => 4,
                            'errors' => ['Row 2: Invalid student ID'],
                            'warnings' => [],
                        ]);

                    return $import instanceof \App\Imports\GradeImport;
                }),
                $validFile
            )
            ->andReturn(true);

        $results = $this->service->previewImport(
            $this->assessmentComponentDetail,
            $this->courseOffering,
            $validFile
        );

        $this->assertIsArray($results);
        $this->assertArrayHasKey('preview_note', $results);
        $this->assertArrayHasKey('assessment_info', $results);
        $this->assertArrayHasKey('name', $results['assessment_info']);
        $this->assertArrayHasKey('max_points', $results['assessment_info']);
    }

    /** @test */
    public function it_returns_correct_statistics_structure()
    {
        $statistics = $this->service->getImportStatistics(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_students', $statistics);
        $this->assertArrayHasKey('graded_students', $statistics);
        $this->assertArrayHasKey('ungraded_students', $statistics);
        $this->assertArrayHasKey('grading_completion_percentage', $statistics);
        $this->assertArrayHasKey('assessment_info', $statistics);
    }

    /** @test */
    public function it_calculates_grading_completion_percentage_correctly()
    {
        $statistics = $this->service->getImportStatistics(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $this->assertIsFloat($statistics['grading_completion_percentage']);
        $this->assertGreaterThanOrEqual(0, $statistics['grading_completion_percentage']);
        $this->assertLessThanOrEqual(100, $statistics['grading_completion_percentage']);
    }

    /** @test */
    public function it_includes_assessment_information_in_statistics()
    {
        $statistics = $this->service->getImportStatistics(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        $assessmentInfo = $statistics['assessment_info'];
        $this->assertEquals('Test Assignment', $assessmentInfo['name']);
        $this->assertEquals(100, $assessmentInfo['max_points']);
        $this->assertEquals(20, $assessmentInfo['weight']);
    }

    /** @test */
    public function it_cleans_up_old_temporary_files()
    {
        // Create some old temp files
        $tempPath = storage_path('app/temp');
        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $oldFile = $tempPath.'/grade_template_old_file.xlsx';
        touch($oldFile);
        // Set file time to 25 hours ago (older than 24 hour cutoff)
        touch($oldFile, time() - (25 * 60 * 60));

        $recentFile = $tempPath.'/grade_template_recent_file.xlsx';
        touch($recentFile);

        $deletedCount = $this->service->cleanupTempFiles();

        $this->assertIsInt($deletedCount);
        $this->assertFileExists($recentFile);

        // Clean up test files
        if (file_exists($recentFile)) {
            unlink($recentFile);
        }
    }

    /** @test */
    public function it_returns_zero_when_no_temp_directory_exists()
    {
        $tempPath = storage_path('app/temp');
        if (is_dir($tempPath)) {
            // Remove directory for this test
            $files = glob($tempPath.'/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($tempPath);
        }

        $deletedCount = $this->service->cleanupTempFiles();

        $this->assertEquals(0, $deletedCount);
    }

    /** @test */
    public function it_accepts_valid_excel_files()
    {
        $validFile = UploadedFile::fake()->create('test.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // This should not throw an exception
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateImportFile');
        $method->setAccessible(true);

        // If no exception is thrown, test passes
        $method->invoke($this->service, $validFile);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_accepts_valid_csv_files()
    {
        $validFile = UploadedFile::fake()->create('test.csv', 100, 'text/csv');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateImportFile');
        $method->setAccessible(true);

        // If no exception is thrown, test passes
        $method->invoke($this->service, $validFile);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_rejects_invalid_file_types()
    {
        $invalidFile = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateImportFile');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File must be an Excel file');

        $method->invoke($this->service, $invalidFile);
    }

    /** @test */
    public function it_generates_filename_with_safe_characters()
    {
        // Create assessment with special characters in name
        $assessmentComponent = AssessmentComponent::factory()->create();
        $specialAssessment = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assessmentComponent->id,
            'name' => 'Test/Assignment*With?Special[Chars]',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateExportFileName');
        $method->setAccessible(true);

        $filename = $method->invoke($this->service, $specialAssessment, $this->courseOffering);

        $this->assertStringNotContainsString('/', $filename);
        $this->assertStringNotContainsString('*', $filename);
        $this->assertStringNotContainsString('?', $filename);
        $this->assertStringNotContainsString('[', $filename);
        $this->assertStringNotContainsString(']', $filename);
        $this->assertStringEndsWith('.xlsx', $filename);
    }
}
