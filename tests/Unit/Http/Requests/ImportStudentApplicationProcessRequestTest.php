<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ImportStudentApplicationProcessRequest;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class ImportStudentApplicationProcessRequestTest extends TestCase
{
    public function test_handles_json_column_mapping()
    {
        // Create a mock request with JSON column mapping
        $request = new ImportStudentApplicationProcessRequest();

        // Simulate form data with JSON column mapping
        $data = [
            'column_mapping' => json_encode([
                'student_code' => 'student_code',
                'full_name' => 'full_name',
                'email' => 'email',
                'phone' => 'phone'
            ]),
            'options' => [
                'update_existing' => true,
                'skip_invalid' => false
            ]
        ];

        // Set the data on the request
        $request->merge($data);

        // Call prepareForValidation to trigger the JSON decoding
        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Check that the column_mapping was properly decoded
        $columnMapping = $request->input('column_mapping');

        $this->assertIsArray($columnMapping);
        $this->assertArrayHasKey('student_code', $columnMapping);
        $this->assertArrayHasKey('full_name', $columnMapping);
        $this->assertEquals('student_code', $columnMapping['student_code']);
        $this->assertEquals('full_name', $columnMapping['full_name']);
    }

    public function test_handles_array_column_mapping()
    {
        // Create a mock request with array column mapping
        $request = new ImportStudentApplicationProcessRequest();

        // Simulate form data with array column mapping
        $data = [
            'column_mapping' => [
                'student_code' => 'student_code',
                'full_name' => 'full_name',
                'email' => 'email'
            ]
        ];

        // Set the data on the request
        $request->merge($data);

        // Call prepareForValidation
        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Check that the column_mapping remains an array
        $columnMapping = $request->input('column_mapping');

        $this->assertIsArray($columnMapping);
        $this->assertEquals('student_code', $columnMapping['student_code']);
        $this->assertEquals('full_name', $columnMapping['full_name']);
    }

    public function test_handles_invalid_json_column_mapping()
    {
        // Create a mock request with invalid JSON
        $request = new ImportStudentApplicationProcessRequest();

        // Simulate form data with invalid JSON column mapping
        $data = [
            'column_mapping' => 'invalid json string'
        ];

        // Set the data on the request
        $request->merge($data);

        // Call prepareForValidation
        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        // Check that the invalid JSON remains as string (will be caught by validation)
        $columnMapping = $request->input('column_mapping');

        $this->assertIsString($columnMapping);
        $this->assertEquals('invalid json string', $columnMapping);
    }
}
