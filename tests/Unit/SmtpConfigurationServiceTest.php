<?php

namespace Tests\Unit;

use App\Models\EmailConfiguration;
use App\Services\SmtpConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SmtpConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmtpConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SmtpConfigurationService::class);
    }

    /** @test */
    public function it_can_create_smtp_configuration()
    {
        $data = [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => 'password123',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System',
            'daily_limit' => 1000,
            'rate_limit' => 50,
            'is_active' => true
        ];

        $configuration = $this->service->create($data);

        $this->assertInstanceOf(EmailConfiguration::class, $configuration);
        $this->assertEquals('Test SMTP', $configuration->name);
        $this->assertEquals('smtp.example.com', $configuration->host);
        $this->assertEquals(587, $configuration->port);
        $this->assertTrue($configuration->is_active);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'name' => 'Test SMTP',
            // Missing required fields
        ]);
    }

    /** @test */
    public function it_can_update_smtp_configuration()
    {
        $configuration = EmailConfiguration::factory()->create([
            'name' => 'Original Name',
            'host' => 'original.example.com'
        ]);

        $updatedData = [
            'name' => 'Updated Name',
            'host' => 'updated.example.com',
            'port' => 465,
            'username' => 'updated@example.com',
            'password' => 'newpassword',
            'encryption' => 'ssl',
            'from_address' => 'updated@example.com',
            'from_name' => 'Updated System',
            'daily_limit' => 2000,
            'rate_limit' => 100,
            'is_active' => true
        ];

        $updated = $this->service->update($configuration, $updatedData);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('updated.example.com', $updated->host);
        $this->assertEquals(465, $updated->port);
    }

    /** @test */
    public function it_can_get_all_configurations()
    {
        EmailConfiguration::factory()->count(3)->create();

        $configurations = $this->service->getAll();

        $this->assertCount(3, $configurations);
    }

    /** @test */
    public function it_can_get_active_configuration()
    {
        EmailConfiguration::factory()->create(['is_active' => false]);
        $activeConfig = EmailConfiguration::factory()->create(['is_active' => true]);

        $active = $this->service->getActive();

        $this->assertNotNull($active);
        $this->assertEquals($activeConfig->id, $active->id);
    }

    /** @test */
    public function it_can_test_connection_with_missing_fields()
    {
        // Create a configuration with minimal required fields but missing testable fields
        $configuration = new EmailConfiguration([
            'name' => 'Test Config',
            'host' => '',
            'port' => 587,
            'from_address' => '',
            'encryption' => 'tls',
            'from_name' => 'Test',
            'daily_limit' => 100,
            'rate_limit' => 10
        ]);

        $result = $this->service->testConnection($configuration);

        $this->assertFalse($result['success']);
        $this->assertEquals('MISSING_REQUIRED_FIELDS', $result['error_code']);
    }

    /** @test */
    public function it_validates_business_rules()
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => 'password123',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System',
            'daily_limit' => 100,
            'rate_limit' => 200, // Rate limit exceeds daily limit
        ]);
    }

    /** @test */
    public function it_can_get_statistics()
    {
        EmailConfiguration::factory()->tested()->create(['is_active' => true, 'test_result' => 'success']);
        EmailConfiguration::factory()->testFailed()->create(['is_active' => false, 'test_result' => 'failed']);

        $stats = $this->service->getStatistics();

        $this->assertEquals(2, $stats['total_configurations']);
        $this->assertEquals(1, $stats['active_configurations']);
        $this->assertEquals(2, $stats['tested_configurations']);
        $this->assertEquals(1, $stats['successful_tests']);
    }

    /** @test */
    public function it_can_rotate_credentials()
    {
        $configuration = EmailConfiguration::factory()->create([
            'password' => 'oldpassword'
        ]);

        $rotated = $this->service->rotateCredentials($configuration, 'newpassword');

        $this->assertEquals('newpassword', $rotated->password);
        $this->assertNull($rotated->last_tested_at);
        $this->assertNull($rotated->test_result);
    }

    /** @test */
    public function it_prevents_deletion_of_only_active_configuration()
    {
        $configuration = EmailConfiguration::factory()->create(['is_active' => true]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the only active email configuration.');

        $this->service->delete($configuration);
    }

    /** @test */
    public function it_can_delete_configuration_when_others_exist()
    {
        $activeConfig = EmailConfiguration::factory()->create(['is_active' => true]);
        $inactiveConfig = EmailConfiguration::factory()->create(['is_active' => false]);

        $result = $this->service->delete($inactiveConfig);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('email_configurations', ['id' => $inactiveConfig->id]);
        $this->assertDatabaseHas('email_configurations', ['id' => $activeConfig->id]);
    }

    /** @test */
    public function it_activates_another_configuration_when_deleting_active_one()
    {
        $activeConfig = EmailConfiguration::factory()->create(['is_active' => true]);
        $inactiveConfig = EmailConfiguration::factory()->create(['is_active' => false]);

        $result = $this->service->delete($activeConfig);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('email_configurations', ['id' => $activeConfig->id]);

        $inactiveConfig->refresh();
        $this->assertTrue($inactiveConfig->is_active);
    }
}
