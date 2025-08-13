<?php

namespace Tests\Unit\Services;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTemplateService();
    }

    /** @test */
    public function it_gets_paginated_templates()
    {
        EmailTemplate::factory()->count(25)->create();

        $result = $this->service->getPaginatedTemplates([], 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(25, $result->total());
    }

    /** @test */
    public function it_filters_templates_by_type()
    {
        EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_WELCOME]);
        EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_GRADE_NOTIFICATION]);

        $result = $this->service->getPaginatedTemplates(['type' => EmailTemplate::TYPE_WELCOME]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(EmailTemplate::TYPE_WELCOME, $result->first()->type);
    }

    /** @test */
    public function it_filters_templates_by_name()
    {
        EmailTemplate::factory()->create(['name' => 'welcome_student']);
        EmailTemplate::factory()->create(['name' => 'grade_notification']);

        $result = $this->service->getPaginatedTemplates(['name' => 'welcome']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('welcome_student', $result->first()->name);
    }

    /** @test */
    public function it_filters_templates_by_active_status()
    {
        EmailTemplate::factory()->create(['is_active' => true]);
        EmailTemplate::factory()->create(['is_active' => false]);

        $result = $this->service->getPaginatedTemplates(['is_active' => true]);

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->first()->is_active);
    }

    /** @test */
    public function it_searches_templates_by_content()
    {
        EmailTemplate::factory()->create([
            'name' => 'welcome_template',
            'subject' => 'Welcome to our system',
            'html_content' => '<p>Welcome message</p>'
        ]);
        EmailTemplate::factory()->create([
            'name' => 'grade_template',
            'subject' => 'Grade notification',
            'html_content' => '<p>Your grade is ready</p>'
        ]);

        $result = $this->service->getPaginatedTemplates(['search' => 'welcome']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('welcome_template', $result->first()->name);
    }

    /** @test */
    public function it_gets_templates_by_type()
    {
        EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_WELCOME, 'is_active' => true]);
        EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_WELCOME, 'is_active' => false]);
        EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_GRADE_NOTIFICATION, 'is_active' => true]);

        $result = $this->service->getTemplatesByType(EmailTemplate::TYPE_WELCOME);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(1, $result->count()); // Only active templates
        $this->assertEquals(EmailTemplate::TYPE_WELCOME, $result->first()->type);
        $this->assertTrue($result->first()->is_active);
    }

    /** @test */
    public function it_gets_template_types()
    {
        $types = $this->service->getTemplateTypes();

        $expectedTypes = [
            EmailTemplate::TYPE_WELCOME,
            EmailTemplate::TYPE_GRADE_NOTIFICATION,
            EmailTemplate::TYPE_COURSE_REGISTRATION,
            EmailTemplate::TYPE_ACADEMIC_HOLD,
            EmailTemplate::TYPE_ENROLLMENT_CONFIRMATION,
            EmailTemplate::TYPE_ASSESSMENT_DEADLINE,
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $types);
        }
    }

    /** @test */
    public function it_creates_template_with_valid_data()
    {
        $data = [
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome {{name}}!',
            'html_content' => '<p>Hello {{name}}, welcome to {{institution}}!</p>',
            'text_content' => 'Hello {{name}}, welcome to {{institution}}!',
            'description' => 'Test template',
        ];

        $template = $this->service->createTemplate($data);

        $this->assertInstanceOf(EmailTemplate::class, $template);
        $this->assertEquals('test_template', $template->name);
        $this->assertEquals(EmailTemplate::TYPE_WELCOME, $template->type);
        $this->assertEquals(1, $template->version);
        $this->assertTrue($template->is_active);
        $this->assertEquals(['name', 'institution'], $template->variables);
    }

    /** @test */
    public function it_validates_template_data_on_create()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            // Missing required fields
            'name' => '',
            'type' => 'invalid_type',
        ]);
    }

    /** @test */
    public function it_updates_existing_template()
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'original_name',
            'subject' => 'Original Subject',
        ]);

        $updateData = [
            'name' => 'updated_name',
            'subject' => 'Updated Subject {{name}}',
            'html_content' => '<p>Updated content for {{name}}</p>',
        ];

        $updated = $this->service->updateTemplate($template->id, $updateData);

        $this->assertEquals('updated_name', $updated->name);
        $this->assertEquals('Updated Subject {{name}}', $updated->subject);
        $this->assertEquals(['name'], $updated->variables);
    }

    /** @test */
    public function it_creates_new_version_of_template()
    {
        $originalTemplate = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => true,
        ]);

        $newVersionData = [
            'subject' => 'Updated Subject {{name}}',
            'html_content' => '<p>Updated content for {{name}}</p>',
            'description' => 'Version 2 of the template',
        ];

        $newVersion = $this->service->createNewVersion($originalTemplate->id, $newVersionData);

        $this->assertEquals(2, $newVersion->version);
        $this->assertEquals($originalTemplate->id, $newVersion->parent_id);
        $this->assertTrue($newVersion->is_active);

        // Original should be deactivated
        $originalTemplate->refresh();
        $this->assertFalse($originalTemplate->is_active);
    }

    /** @test */
    public function it_deletes_template()
    {
        $template = EmailTemplate::factory()->create();

        $result = $this->service->deleteTemplate($template->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    /** @test */
    public function it_activates_parent_when_deleting_active_version()
    {
        $parent = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => false,
        ]);

        $child = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $this->service->deleteTemplate($child->id);

        $parent->refresh();
        $this->assertTrue($parent->is_active);
    }

    /** @test */
    public function it_finds_template_by_id()
    {
        $template = EmailTemplate::factory()->create();

        $found = $this->service->findTemplate($template->id);

        $this->assertEquals($template->id, $found->id);
    }

    /** @test */
    public function it_throws_exception_when_template_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email template with ID 999 not found');

        $this->service->findTemplate(999);
    }

    /** @test */
    public function it_gets_template_by_name()
    {
        EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => false,
        ]);

        $latest = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'is_active' => true,
        ]);

        $found = $this->service->getTemplateByName('test_template');

        $this->assertEquals($latest->id, $found->id);
        $this->assertEquals(2, $found->version);
    }

    /** @test */
    public function it_renders_template_with_variables()
    {
        $template = $this->createMock(EmailTemplate::class);
        $template->method('validateVariables')
            ->with(['name' => 'John'])
            ->willReturn([]);

        $template->method('render')
            ->with(['name' => 'John'])
            ->willReturn([
                'subject' => 'Hello John',
                'html' => '<p>Hello John</p>',
                'text' => 'Hello John'
            ]);

        // Mock the findTemplate method
        $service = $this->getMockBuilder(EmailTemplateService::class)
            ->onlyMethods(['findTemplate'])
            ->getMock();

        $service->method('findTemplate')->willReturn($template);

        $result = $service->renderTemplate(1, ['name' => 'John']);

        $this->assertEquals('Hello John', $result['subject']);
        $this->assertEquals('<p>Hello John</p>', $result['html']);
        $this->assertEquals('Hello John', $result['text']);
    }

    /** @test */
    public function it_throws_exception_for_missing_variables()
    {
        $template = $this->createMock(EmailTemplate::class);
        $template->method('validateVariables')
            ->with(['name' => 'John'])
            ->willReturn(['email']); // Missing email variable

        $service = $this->getMockBuilder(EmailTemplateService::class)
            ->onlyMethods(['findTemplate'])
            ->getMock();

        $service->method('findTemplate')->willReturn($template);

        $this->expectException(ValidationException::class);

        $service->renderTemplate(1, ['name' => 'John']);
    }

    /** @test */
    public function it_renders_template_by_name()
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'subject' => 'Hello {{name}}',
            'html_content' => '<p>Hello {{name}}</p>',
            'variables' => ['name'],
            'is_active' => true,
        ]);

        $result = $this->service->renderTemplateByName('test_template', ['name' => 'John']);

        $this->assertStringContainsString('Hello John', $result['subject']);
        $this->assertStringContainsString('Hello John', $result['html']);
    }

    /** @test */
    public function it_throws_exception_when_template_not_found_by_name()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No active template found with name: nonexistent');

        $this->service->renderTemplateByName('nonexistent', []);
    }

    /** @test */
    public function it_previews_template_with_sample_data()
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{name}}',
            'html_content' => '<p>Hello {{name}}, your email is {{email}}</p>',
            'variables' => ['name', 'email'],
        ]);

        $result = $this->service->previewTemplate($template->id, ['name' => 'John']);

        // Should use provided variable and placeholder for missing one
        $this->assertStringContainsString('Hello John', $result['subject']);
        $this->assertStringContainsString('Hello John', $result['html']);
        $this->assertStringContainsString('[email]', $result['html']);
    }

    /** @test */
    public function it_validates_html_content()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test',
            'html_content' => '<p>Test</p><script>alert("xss")</script>',
        ]);
    }

    /** @test */
    public function it_validates_variable_syntax()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test {{}}', // Empty variable
            'html_content' => '<p>Test</p>',
        ]);
    }

    /** @test */
    public function it_validates_unmatched_braces()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test {{name}', // Missing closing brace
            'html_content' => '<p>Test</p>',
        ]);
    }

    /** @test */
    public function it_validates_nested_braces()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test {{name {{nested}}}}', // Nested braces
            'html_content' => '<p>Test</p>',
        ]);
    }

    /** @test */
    public function it_validates_invalid_variable_names()
    {
        $this->expectException(ValidationException::class);

        $this->service->createTemplate([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Test {{123invalid}}', // Invalid variable name
            'html_content' => '<p>Test</p>',
        ]);
    }

    /** @test */
    public function it_activates_template()
    {
        $template1 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => true,
        ]);

        $template2 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'is_active' => false,
        ]);

        $activated = $this->service->activateTemplate($template2->id);

        $this->assertTrue($activated->is_active);

        $template1->refresh();
        $this->assertFalse($template1->is_active);
    }

    /** @test */
    public function it_duplicates_template()
    {
        $original = EmailTemplate::factory()->create([
            'name' => 'original_template',
            'subject' => 'Original Subject',
            'html_content' => '<p>Original content</p>',
            'description' => 'Original description',
        ]);

        $duplicate = $this->service->duplicateTemplate(
            $original->id,
            'duplicated_template',
            'Duplicated description'
        );

        $this->assertEquals('duplicated_template', $duplicate->name);
        $this->assertEquals('Original Subject', $duplicate->subject);
        $this->assertEquals('<p>Original content</p>', $duplicate->html_content);
        $this->assertEquals('Duplicated description', $duplicate->description);
        $this->assertEquals(1, $duplicate->version);
        $this->assertNull($duplicate->parent_id);
    }

    /** @test */
    public function it_gets_template_stats()
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'version' => 2,
            'variables' => ['name', 'email'],
            'is_active' => true,
        ]);

        $stats = $this->service->getTemplateStats($template->id);

        $this->assertEquals($template->id, $stats['template_id']);
        $this->assertEquals('test_template', $stats['name']);
        $this->assertEquals(EmailTemplate::TYPE_WELCOME, $stats['type']);
        $this->assertEquals(2, $stats['version']);
        $this->assertTrue($stats['is_active']);
        $this->assertEquals(2, $stats['variables_count']);
    }

    /** @test */
    public function it_searches_templates_by_content()
    {
        EmailTemplate::factory()->create([
            'name' => 'welcome_template',
            'subject' => 'Welcome message',
            'html_content' => '<p>Welcome to our system</p>',
        ]);

        EmailTemplate::factory()->create([
            'name' => 'grade_template',
            'subject' => 'Grade notification',
            'html_content' => '<p>Your grade is ready</p>',
        ]);

        $results = $this->service->searchTemplates('welcome');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertEquals(1, $results->count());
        $this->assertEquals('welcome_template', $results->first()->name);
    }

    /** @test */
    public function it_searches_templates_with_filters()
    {
        EmailTemplate::factory()->create([
            'name' => 'welcome_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome message',
            'is_active' => true,
        ]);

        EmailTemplate::factory()->create([
            'name' => 'welcome_grade',
            'type' => EmailTemplate::TYPE_GRADE_NOTIFICATION,
            'subject' => 'Welcome grade notification',
            'is_active' => false,
        ]);

        $results = $this->service->searchTemplates('welcome', [
            'type' => EmailTemplate::TYPE_WELCOME,
            'is_active' => true,
        ]);

        $this->assertEquals(1, $results->count());
        $this->assertEquals('welcome_template', $results->first()->name);
    }
}
