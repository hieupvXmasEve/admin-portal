<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Lecture;
use App\Services\LectureExcelExportService;
use App\Services\LectureExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class LectureImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test campus
        Campus::factory()->create([
            'id' => 1,
            'code' => 'MAIN',
            'name' => 'Main Campus',
        ]);
    }

    public function test_can_import_lecturers_from_excel(): void
    {
        // Create test Excel file
        $filePath = $this->createTestExcelFile();

        // Test import service
        $importService = new LectureExcelImportService();
        $result = $importService->importLecturersFromExcel($filePath, ['duplicate_handling' => 'update']);

        // Verify results
        $this->assertTrue(isset($result['summary']));
        $this->assertEquals(1, $result['summary']['total_rows']);
        $this->assertEquals(1, $result['summary']['successful']);
        $this->assertEquals(0, $result['summary']['failed']);

        // Verify lecturer was created
        $this->assertDatabaseHas('lectures', [
            'employee_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@university.edu',
            'campus_id' => 1,
        ]);

        // Clean up
        unlink($filePath);
    }

    public function test_import_handles_duplicate_lecturers(): void
    {
        // Create existing lecturer
        Lecture::factory()->create([
            'employee_id' => 'EMP001',
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => 'original@university.edu',
            'campus_id' => 1,
        ]);

        // Create test Excel file with same employee ID
        $filePath = $this->createTestExcelFile();

        // Test import with update strategy
        $importService = new LectureExcelImportService();
        $result = $importService->importLecturersFromExcel($filePath, ['duplicate_handling' => 'update']);

        // Verify results
        $this->assertEquals(1, $result['summary']['total_rows']);
        $this->assertEquals(1, $result['summary']['successful']);
        $this->assertEquals(0, $result['summary']['failed']);

        // Verify lecturer was updated
        $lecturer = Lecture::where('employee_id', 'EMP001')->first();
        $this->assertEquals('John', $lecturer->first_name);
        $this->assertEquals('Doe', $lecturer->last_name);

        // Test import with skip strategy
        $result = $importService->importLecturersFromExcel($filePath, ['duplicate_handling' => 'skip']);
        $this->assertCount(1, $result['warnings']);

        // Test import with error strategy
        $result = $importService->importLecturersFromExcel($filePath, ['duplicate_handling' => 'error']);
        $this->assertEquals(1, $result['summary']['total_rows']);
        $this->assertEquals(0, $result['summary']['successful']);
        $this->assertEquals(1, $result['summary']['failed']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Lecturer with employee ID already exists', $result['errors'][0]['error']);

        // Clean up
        unlink($filePath);
    }

    public function test_import_validates_required_fields(): void
    {
        // Create Excel file with missing required fields
        $filePath = $this->createInvalidExcelFile();

        $importService = new LectureExcelImportService();
        $result = $importService->importLecturersFromExcel($filePath, []);

        // Verify validation errors
        $this->assertEquals(1, $result['summary']['total_rows']);
        $this->assertEquals(0, $result['summary']['successful']);
        $this->assertEquals(1, $result['summary']['failed']);
        $this->assertCount(1, $result['errors']);

        // Clean up
        unlink($filePath);
    }

    public function test_can_export_lecturers_to_excel(): void
    {
        // Create test lecturers
        Lecture::factory(3)->create(['campus_id' => 1]);

        $exportService = new LectureExcelExportService();
        $filePath = $exportService->exportLecturersToExcel();

        // Verify file exists
        $this->assertFileExists($filePath);

        // Verify Excel content
        $spreadsheet = IOFactory::load($filePath);
        $sheets = $spreadsheet->getAllSheets();

        // Check that we have the expected sheets
        $this->assertCount(3, $sheets);
        $this->assertEquals('Lecturers Summary', $sheets[0]->getTitle());
        $this->assertEquals('Lecturers Detailed', $sheets[1]->getTitle());
        $this->assertEquals('Campus Lecturer Overview', $sheets[2]->getTitle());

        // Verify summary sheet has data
        $summarySheet = $sheets[0];
        $data = $summarySheet->toArray();
        $this->assertCount(4, $data); // Header + 3 lecturers

        // Clean up
        unlink($filePath);
    }

    public function test_export_with_filters(): void
    {
        // Create test lecturers with different attributes
        Lecture::factory()->create([
            'campus_id' => 1,
            'employment_status' => 'active',
            'department' => 'Computer Science',
        ]);

        Lecture::factory()->create([
            'campus_id' => 1,
            'employment_status' => 'on_leave',
            'department' => 'Mathematics',
        ]);

        // Export with filters
        $exportService = new LectureExcelExportService();
        $filePath = $exportService->exportLecturersToExcel(['employment_status' => 'active']);

        // Verify file exists
        $this->assertFileExists($filePath);

        // Verify filtered data
        $spreadsheet = IOFactory::load($filePath);
        $summarySheet = $spreadsheet->getSheetByName('Lecturers Summary');
        $data = $summarySheet->toArray();

        // Should have header + 1 active lecturer
        $this->assertCount(2, $data);

        // Clean up
        unlink($filePath);
    }

    public function test_import_controller_endpoints(): void
    {
        $this->markTestSkipped('Controller tests would require authentication setup');

        // Note: These tests would require:
        // 1. User authentication
        // 2. Permission setup
        // 3. File upload testing
        // They can be implemented when the authentication system is properly set up in tests
    }

    private function createTestExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set minimal required headers
        $headers = [
            'Employee ID',
            'First Name',
            'Last Name',
            'Email',
            'Campus Code',
            'Academic Rank',
            'Hire Date',
            'Employment Type',
            'Employment Status',
        ];

        // Set headers
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Set minimal test data
        $testData = [
            'EMP001',
            'John',
            'Doe',
            'john.doe@university.edu',
            'MAIN',
            'lecturer',
            '2015-09-01',
            'full_time',
            'active',
        ];

        foreach ($testData as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $value);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'lecturer_test_');
        $tempFile .= '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function createInvalidExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = ['Employee ID', 'First Name', 'Last Name', 'Email', 'Campus Code', 'Academic Rank', 'Hire Date', 'Employment Type', 'Employment Status'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Set invalid data (missing required fields)
        $invalidData = ['EMP002', 'Jane', '', 'invalid-email', 'MAIN', 'lecturer', '2020-01-01', 'full_time', 'active']; // Missing last name and invalid email
        foreach ($invalidData as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $value);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'lecturer_invalid_');
        $tempFile .= '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
