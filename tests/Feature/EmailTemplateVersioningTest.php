<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected EmailTemplateVersioningService $versioningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->versioningService = app(EmailTemplateVersioningService::class);
    }

    public function test_can_create_new_template_version(): void
    {
        // Create initial template
        $template = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => true,
        ]);

        // Create new version
        $newVersion = $this->versioningService->createNewVersion($template, [
            'subject' => 'Updated Subject {{name}}',
            'html_content' => '<h1>Updated Content</h1><p>Hello {{name}}</p>',
            'text_content' => 'Updated text content for {{name}}',
            'is_active' => true,
        ]);

        // Assert new version was created
        $this->assertEquals(2, $newVersion->version);
        $this->assertEquals('test_template', $newVersion->name);
        $this->assertTrue($newVersion->is_active);
        $this->assertEquals($template->id, $newVersion->parent_id);

        // Assert old version was deactivated
        $template->refresh();
        $this->assertFalse($template->is_active);
    }

    public function test_can_rollback_to_previous_version(): void
    {
        // Create template with multiple versions
        $template1 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => false,
        ]);

        $template2 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'is_active' => false,
            'parent_id' => $template1->id,
        ]);

        $template3 = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 3,
            'is_active' => true,
            'parent_id' => $template2->id,
        ]);

        // Rollback to version 2
        $rolledBack = $this->versioningService->rollbackToVersion('test_template', 2);

        // Assert version 2 is now active
        $this->assertEquals(2, $rolledBack->version);
        $this->assertTrue($rolledBack->is_active);

        // Assert version 3 is now inactive
        $template3->refresh();
        $this->assertFalse($template3->is_active);
    }

    public function test_can_get_template_versions(): void
    {
        // Create multiple versions
        EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
        ]);

        EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
        ]);

        EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 3,
        ]);

        $versions = $this->versioningService->getTemplateVersions('test_template');

        $this->assertCount(3, $versions);
        $this->assertEquals([3, 2, 1], $versions->pluck('version')->toArray());
    }

    public function test_can_get_current_version(): void
    {
        // Create inactive version
        EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 1,
            'is_active' => false,
        ]);

        // Create active version
        $activeTemplate = EmailTemplate::factory()->create([
            'name' => 'test_template',
            'version' => 2,
            'is_active' => true,
        ]);

        $current = $this->versioningService->getCurrentVersion('test_template');

        $this->assertNotNull($current);
        $this->assertEquals(2, $current->version);
        $this->assertEquals($activeTemplate->id, $current->id);
    }

    public function test_can_compare_versions(): void
    {
        $version1 = EmailTemplate::factory()->create([
            'subject' => 'Original Subject',
            'html_content' => '<h1>Original</h1>',
        ]);

        $version2 = EmailTemplate::factory()->create([
            'subject' => 'Updated Subject',
            'html_content' => '<h1>Updated</h1>',
        ]);

        $comparison = $this->versioningService->compareVersions($version1, $version2);

        $this->assertTrue($comparison['subject_changed']);
        $this->assertTrue($comparison['html_content_changed']);
        $this->assertEquals('Original Subject', $comparison['changes']['subject']['old']);
        $this->assertEquals('Updated Subject', $comparison['changes']['subject']['new']);
    }

    public function test_can_duplicate_template(): void
    {
        $original = EmailTemplate::factory()->create([
            'name' => 'original_template',
            'subject' => 'Original Subject',
        ]);

        $duplicate = $this->versioningService->duplicateTemplate($original, 'duplicated_template');

        $this->assertEquals('duplicated_template', $duplicate->name);
        $this->assertEquals($original->subject, $duplicate->subject);
        $this->assertEquals($original->type, $duplicate->type);
        $this->assertEquals(1, $duplicate->version);
        $this->assertFalse($duplicate->is_active);
        $this->assertStringContainsString('(Copy)', $duplicate->description);
    }

    public function test_can_archive_old_versions(): void
    {
        // Create 15 versions
        for ($i = 1; $i <= 15; $i++) {
            EmailTemplate::factory()->create([
                'name' => 'test_template',
                'version' => $i,
                'is_active' => $i === 15, // Only latest is active
            ]);
        }

        // Archive keeping only 5 versions
        $archivedCount = $this->versioningService->archiveOldVersions('test_template', 5);

        // Should archive 9 versions (14 inactive versions - 5 to keep)
        // The active version (version 15) is not archived
        $this->assertEquals(9, $archivedCount);

        // Should have 6 versions remaining (1 active + 5 inactive)
        $remainingVersions = EmailTemplate::where('name', 'test_template')->count();
        $this->assertEquals(6, $remainingVersions);
    }

    public function test_can_preview_changes(): void
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Original Subject',
            'html_content' => '<h1>Original</h1>',
        ]);

        $newAttributes = [
            'subject' => 'Updated Subject',
            'html_content' => '<h1>Updated</h1>',
        ];

        $preview = $this->versioningService->previewChanges($template, $newAttributes);

        $this->assertTrue($preview['has_changes']);
        $this->assertEquals('Original Subject', $preview['current']['subject']);
        $this->assertEquals('Updated Subject', $preview['proposed']['subject']);
    }

    public function test_validates_template_version(): void
    {
        // Test with invalid data
        $errors = $this->versioningService->validateTemplateVersion([
            'subject' => '',
            'html_content' => '',
        ]);

        $this->assertArrayHasKey('subject', $errors);
        $this->assertArrayHasKey('html_content', $errors);

        // Test with valid data
        $errors = $this->versioningService->validateTemplateVersion([
            'subject' => 'Valid Subject',
            'html_content' => '<h1>Valid Content {{name}}</h1>',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validates_template_syntax(): void
    {
        // Test with unmatched braces
        $errors = $this->versioningService->validateTemplateVersion([
            'subject' => 'Valid Subject',
            'html_content' => '<h1>Invalid {{name Content</h1>',
        ]);

        $this->assertArrayHasKey('html_content_syntax', $errors);

        // Test with invalid variable names
        $errors = $this->versioningService->validateTemplateVersion([
            'subject' => 'Valid Subject',
            'html_content' => '<h1>Invalid {{123invalid}} Content</h1>',
        ]);

        $this->assertArrayHasKey('html_content_syntax', $errors);
    }

    public function test_template_rendering_with_variables(): void
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Welcome {{name}} to {{course}}',
            'html_content' => '<h1>Hello {{name}}</h1><p>Course: {{course}}</p>',
            'text_content' => 'Hello {{name}}, welcome to {{course}}',
        ]);

        $variables = [
            'name' => 'John Doe',
            'course' => 'Computer Science 101',
        ];

        $rendered = $template->render($variables);

        $this->assertEquals('Welcome John Doe to Computer Science 101', $rendered['subject']);
        $this->assertStringContainsString('Hello John Doe', $rendered['html']);
        $this->assertStringContainsString('Course: Computer Science 101', $rendered['html']);
        $this->assertEquals('Hello John Doe, welcome to Computer Science 101', $rendered['text']);
    }

    public function test_template_variable_extraction(): void
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Welcome {{name}} to {{course}}',
            'html_content' => '<h1>Hello {{name}}</h1><p>Semester: {{semester}}</p>',
            'text_content' => 'Contact: {{email}}',
        ]);

        $variables = $template->extractVariables();

        $this->assertContains('name', $variables);
        $this->assertContains('course', $variables);
        $this->assertContains('semester', $variables);
        $this->assertContains('email', $variables);
        $this->assertCount(4, $variables);
    }

    public function test_template_variable_validation(): void
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Welcome {{name}} to {{course}}',
            'html_content' => '<h1>Hello {{name}}</h1>',
            'text_content' => 'Welcome {{name}}',
            'variables' => ['name', 'course'], // Override factory variables
        ]);

        // Test with missing variables
        $missingVariables = $template->validateVariables(['name' => 'John']);
        $this->assertContains('course', $missingVariables);

        // Test with all variables provided
        $missingVariables = $template->validateVariables([
            'name' => 'John',
            'course' => 'CS101',
        ]);
        $this->assertEmpty($missingVariables);
    }
}
