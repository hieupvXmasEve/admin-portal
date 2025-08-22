<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Web\StudentApplicationController;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentApplicationControllerImportTest extends TestCase
{
    public function test_controller_has_import_methods()
    {
        // Create mock services
        $studentApplicationService = $this->createMock(\App\Services\StudentApplicationService::class);
        $importService = $this->createMock(\App\Services\StudentApplicationImportService::class);
        
        $controller = new StudentApplicationController($studentApplicationService, $importService);
        
        // Test that all import-related methods exist
        $this->assertTrue(method_exists($controller, 'showImportForm'));
        $this->assertTrue(method_exists($controller, 'previewImport'));
        $this->assertTrue(method_exists($controller, 'processImport'));
        $this->assertTrue(method_exists($controller, 'downloadTemplate'));
    }

    public function test_import_routes_are_defined()
    {
        // Test that routes exist and are accessible
        $routes = [
            'student-applications.import',
            'student-applications.import.preview',
            'student-applications.import.process',
            'student-applications.import.template',
        ];

        foreach ($routes as $routeName) {
            $this->assertTrue(
                app('router')->getRoutes()->hasNamedRoute($routeName),
                "Route {$routeName} should be defined"
            );
        }
    }
}