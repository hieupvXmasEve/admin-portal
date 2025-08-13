<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected EmailTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTemplateService();
    }

    public function test_complete_template_lifecycle(): void
    {
        // Create a template
        $templateData = [
            'name' => 'welcome_student',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome to {{institution_name}}, {{student_name}}!',
            'html_content' => '
                <h1>Welcome to {{institution_name}}!</h1>
                <p>Dear {{student_name}},</p>
                <p>Your student ID is: {{student_id}}</p>
                <p>Login at: {{login_url}}</p>
            ',
            'text_content' => 'Welcome to {{institution_name}}! Dear {{student_name}}, your student ID is: {{student_id}}. Login at: {{login_url}}',
            'description' => 'Welcome email for new students',
        ];

        $template = $this->service->createTemplate($templateData);

        // Verify template was created correctly
        $this->assertInstanceOf(EmailTemplate::class, $template);
        $this->assertEquals('welcome_student', $template->name);
        $this->assertEquals(EmailTemplate::TYPE_WELCOME, $template->type);
        $this->assertTrue($template->is_active);
        $this->assertEquals(1, $template->version);
        $this->assertEquals(['institution_name', 'student_name', 'student_id', 'login_url'], array_values($template->variables));

        // Render the template
        $variables = [
            'institution_name' => 'Swinburne University',
            'student_name' => 'John Doe',
            'student_id' => 'S12345678',
            'login_url' => 'https://portal.swinburne.edu.au',
        ];

        $rendered = $this->service->renderTemplate($template->id, $variables);

        $this->assertEquals('Welcome to Swinburne University, John Doe!', $rendered['subject']);
        $this->assertStringContainsString('Welcome to Swinburne University!', $rendered['html']);
        $this->assertStringContainsString('Dear John Doe,', $rendered['html']);
        $this->assertStringContainsString('S12345678', $rendered['html']);
        $this->assertStringContainsString('https://portal.swinburne.edu.au', $rendered['html']);

        // Create a new version
        $newVersionData = [
            'subject' => 'Welcome to {{institution_name}}, {{student_name}}! 🎉',
            'html_content' => '
                <div style="background: #f0f0f0; padding: 20px;">
                    <h1>🎉 Welcome to {{institution_name}}!</h1>
                    <p>Dear {{student_name}},</p>
                    <p>We are excited to have you join us!</p>
                    <p>Your student ID is: <strong>{{student_id}}</strong></p>
                    <p><a href="{{login_url}}">Click here to login</a></p>
                </div>
            ',
            'description' => 'Updated welcome email with better styling',
        ];

        $newVersion = $this->service->createNewVersion($template->id, $newVersionData);

        // Verify new version
        $this->assertEquals(2, $newVersion->version);
        $this->assertEquals($template->id, $newVersion->parent_id);
        $this->assertTrue($newVersion->is_active);

        // Verify original version is deactivated
        $template->refresh();
        $this->assertFalse($template->is_active);

        // Test rendering by name (should get the latest active version)
        $renderedByName = $this->service->renderTemplateByName('welcome_student', $variables);
        $this->assertEquals('Welcome to Swinburne University, John Doe! 🎉', $renderedByName['subject']);

        // Test search functionality
        $searchResults = $this->service->searchTemplates('welcome');
        $this->assertCount(2, $searchResults); // Both versions should be found

        // Test filtering by type
        $welcomeTemplates = $this->service->getTemplatesByType(EmailTemplate::TYPE_WELCOME);
        $this->assertCount(1, $welcomeTemplates); // Only active version
        $this->assertEquals(2, $welcomeTemplates->first()->version);

        // Test template stats
        $stats = $this->service->getTemplateStats($newVersion->id);
        $this->assertEquals('welcome_student', $stats['name']);
        $this->assertEquals(2, $stats['version']);
        $this->assertEquals(4, $stats['variables_count']);

        // Test duplication
        $duplicate = $this->service->duplicateTemplate($newVersion->id, 'welcome_lecturer', 'Welcome email for lecturers');
        $this->assertEquals('welcome_lecturer', $duplicate->name);
        $this->assertEquals(1, $duplicate->version);
        $this->assertNull($duplicate->parent_id);
        $this->assertEquals('Welcome email for lecturers', $duplicate->description);
    }

    public function test_template_validation_integration(): void
    {
        // Test HTML validation
        $invalidHtmlData = [
            'name' => 'invalid_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test Subject',
            'html_content' => '<h1>Test</h1><script>alert("xss")</script>',
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->createTemplate($invalidHtmlData);
    }

    public function test_template_variable_validation_integration(): void
    {
        // Create template with variables
        $template = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'subject' => 'Hello {{name}}!',
            'html_content' => '<p>Welcome {{name}}, your email is {{email}}</p>',
            'variables' => ['name', 'email'],
        ]);

        // Test missing variables
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->renderTemplate($template->id, ['name' => 'John']); // Missing email
    }

    public function test_pagination_integration(): void
    {
        // Create multiple templates
        EmailTemplate::factory()->count(25)->create();

        $paginated = $this->service->getPaginatedTemplates([], 10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertEquals(25, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
    }

    public function test_template_activation_integration(): void
    {
        // Create template with multiple versions
        $original = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => false,
        ]);

        $version2 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'parent_id' => $original->id,
            'is_active' => true,
        ]);

        // Activate the original version
        $activated = $this->service->activateTemplate($original->id);

        $this->assertTrue($activated->is_active);

        // Check that version 2 is deactivated
        $version2->refresh();
        $this->assertFalse($version2->is_active);
    }
}
