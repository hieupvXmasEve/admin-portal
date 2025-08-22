<?php

namespace Tests\Unit\Services;

use App\Services\StudentApplicationImportService;
use Tests\TestCase;

class StudentApplicationImportServiceTest extends TestCase
{
    protected StudentApplicationImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudentApplicationImportService();
    }

    public function test_detects_column_mapping_with_underscore_headers()
    {
        // Test headers that match the Excel file mentioned in the issue
        $headers = ['student_code', 'full_name', 'email', 'phone'];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('detectColumnMapping');
        $method->setAccessible(true);

        $mapping = $method->invoke($this->service, $headers);

        // Verify that all headers are properly mapped
        $this->assertArrayHasKey('student_code', $mapping);
        $this->assertArrayHasKey('full_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        $this->assertArrayHasKey('phone', $mapping);

        // Verify the mapping values
        $this->assertEquals('student_code', $mapping['student_code']);
        $this->assertEquals('full_name', $mapping['full_name']);
        $this->assertEquals('email', $mapping['email']);
        $this->assertEquals('phone', $mapping['phone']);
    }

    public function test_detects_column_mapping_with_space_headers()
    {
        // Test headers with spaces
        $headers = ['Student Code', 'Full Name', 'Email Address', 'Phone Number'];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('detectColumnMapping');
        $method->setAccessible(true);

        $mapping = $method->invoke($this->service, $headers);

        // Verify that all headers are properly mapped
        $this->assertArrayHasKey('Student Code', $mapping);
        $this->assertArrayHasKey('Full Name', $mapping);
        $this->assertArrayHasKey('Email Address', $mapping);
        $this->assertArrayHasKey('Phone Number', $mapping);

        // Verify the mapping values
        $this->assertEquals('student_code', $mapping['Student Code']);
        $this->assertEquals('full_name', $mapping['Full Name']);
        $this->assertEquals('email', $mapping['Email Address']);
        $this->assertEquals('phone', $mapping['Phone Number']);
    }

    public function test_detects_column_mapping_mixed_case()
    {
        // Test mixed case headers
        $headers = ['STUDENT_CODE', 'Full_Name', 'EMAIL', 'phone'];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('detectColumnMapping');
        $method->setAccessible(true);

        $mapping = $method->invoke($this->service, $headers);

        // Verify that case doesn't matter
        $this->assertArrayHasKey('STUDENT_CODE', $mapping);
        $this->assertArrayHasKey('Full_Name', $mapping);
        $this->assertArrayHasKey('EMAIL', $mapping);
        $this->assertArrayHasKey('phone', $mapping);

        // Verify the mapping values
        $this->assertEquals('student_code', $mapping['STUDENT_CODE']);
        $this->assertEquals('full_name', $mapping['Full_Name']);
        $this->assertEquals('email', $mapping['EMAIL']);
        $this->assertEquals('phone', $mapping['phone']);
    }

    public function test_ignores_unmappable_columns()
    {
        // Test with some unmappable columns
        $headers = ['student_code', 'full_name', 'unknown_column', 'email', 'random_field'];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('detectColumnMapping');
        $method->setAccessible(true);

        $mapping = $method->invoke($this->service, $headers);

        // Verify that known columns are mapped
        $this->assertArrayHasKey('student_code', $mapping);
        $this->assertArrayHasKey('full_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);

        // Verify that unknown columns are not mapped
        $this->assertArrayNotHasKey('unknown_column', $mapping);
        $this->assertArrayNotHasKey('random_field', $mapping);

        // Verify only 3 mappings exist
        $this->assertCount(3, $mapping);
    }
}
