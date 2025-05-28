<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Campus;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        Campus::factory()->create(['code' => 'CA', 'name' => 'Campus A']);
        Campus::factory()->create(['code' => 'CB', 'name' => 'Campus B']);

        Role::factory()->create(['name' => 'Super Admin']);
        Role::factory()->create(['name' => 'Giám Đốc Đào Tạo']);

        // Create a test user with permissions
        $user = User::factory()->create();

        // Mock session permissions for authorization
        session(['permissions' => ['view_user', 'add_user', 'edit_user', 'import_user', 'export_user']]);

        $this->actingAs($user);
    }

    public function test_user_can_access_import_form()
    {
        $response = $this->get('/users/import');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('users/Import')
                ->has('maxFileSize')
                ->has('allowedExtensions')
                ->has('availableFormats')
        );
    }

    public function test_user_can_download_templates()
    {
        $formats = ['simple', 'detailed', 'relationship'];

        foreach ($formats as $format) {
            $response = $this->get("/users/templates/{$format}");
            $response->assertStatus(200);
            $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_file_upload_validation()
    {
        // Test invalid file type
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->post('/users/import/upload', [
            'file' => $invalidFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_file_upload_success()
    {
        Storage::fake('local');

        // Create a fake Excel file
        $file = UploadedFile::fake()->create('users.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Mock the import service to avoid actual Excel processing
        $this->mock(\App\Services\UserExcelImportService::class, function ($mock) {
            $mock->shouldReceive('previewImportData')
                ->once()
                ->andReturn([
                    'format' => 'simple',
                    'sheets' => [
                        [
                            'name' => 'Users',
                            'headers' => ['Name', 'Email'],
                            'data' => [['John Doe', 'john@example.com']],
                            'total_rows' => 1
                        ]
                    ],
                    'estimated_users' => 1
                ]);
        });

        $response = $this->post('/users/import/upload', [
            'file' => $file
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
    }
}
