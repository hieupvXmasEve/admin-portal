<?php

namespace Tests\Unit\Services;

use App\Services\EmailCredentialEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailCredentialEncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailCredentialEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailCredentialEncryptionService();
    }

    /** @test */
    public function it_encrypts_credentials_with_salt()
    {
        $password = 'test_password_123';

        $result = $this->service->encryptCredentials($password);

        $this->assertArrayHasKey('encrypted_password', $result);
        $this->assertArrayHasKey('salt', $result);
        $this->assertArrayHasKey('verification_hash', $result);
        $this->assertArrayHasKey('encrypted_at', $result);

        $this->assertNotEmpty($result['encrypted_password']);
        $this->assertNotEmpty($result['salt']);
        $this->assertNotEmpty($result['verification_hash']);
        $this->assertEquals(32, strlen($result['salt']));
    }

    /** @test */
    public function it_encrypts_credentials_with_provided_salt()
    {
        $password = 'test_password_123';
        $customSalt = 'custom_salt_for_testing_purposes';

        $result = $this->service->encryptCredentials($password, $customSalt);

        $this->assertEquals($customSalt, $result['salt']);
    }

    /** @test */
    public function it_decrypts_credentials_correctly()
    {
        $originalPassword = 'test_password_123';

        // First encrypt
        $encrypted = $this->service->encryptCredentials($originalPassword);

        // Then decrypt
        $decrypted = $this->service->decryptCredentials(
            $encrypted['encrypted_password'],
            $encrypted['salt'],
            $encrypted['verification_hash']
        );

        $this->assertEquals($originalPassword, $decrypted);
    }

    /** @test */
    public function it_decrypts_credentials_without_verification_hash()
    {
        $originalPassword = 'test_password_123';

        $encrypted = $this->service->encryptCredentials($originalPassword);

        $decrypted = $this->service->decryptCredentials(
            $encrypted['encrypted_password'],
            $encrypted['salt']
        );

        $this->assertEquals($originalPassword, $decrypted);
    }

    /** @test */
    public function it_throws_exception_for_invalid_verification_hash()
    {
        $originalPassword = 'test_password_123';

        $encrypted = $this->service->encryptCredentials($originalPassword);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to decrypt email credentials');

        $this->service->decryptCredentials(
            $encrypted['encrypted_password'],
            $encrypted['salt'],
            'invalid_verification_hash'
        );
    }

    /** @test */
    public function it_throws_exception_for_wrong_salt()
    {
        $originalPassword = 'test_password_123';

        $encrypted = $this->service->encryptCredentials($originalPassword);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to decrypt email credentials');

        $this->service->decryptCredentials(
            $encrypted['encrypted_password'],
            'wrong_salt',
            $encrypted['verification_hash']
        );
    }

    /** @test */
    public function it_rotates_encryption_successfully()
    {
        $originalPassword = 'test_password_123';

        // Initial encryption
        $firstEncryption = $this->service->encryptCredentials($originalPassword);

        // Rotate encryption
        $rotated = $this->service->rotateEncryption(
            $firstEncryption['encrypted_password'],
            $firstEncryption['salt'],
            $firstEncryption['verification_hash']
        );

        // Verify the rotated encryption works
        $decrypted = $this->service->decryptCredentials(
            $rotated['encrypted_password'],
            $rotated['salt'],
            $rotated['verification_hash']
        );

        $this->assertEquals($originalPassword, $decrypted);
        $this->assertNotEquals($firstEncryption['salt'], $rotated['salt']);
        $this->assertNotEquals($firstEncryption['encrypted_password'], $rotated['encrypted_password']);
    }

    /** @test */
    public function it_validates_encrypted_credentials()
    {
        $password = 'test_password_123';

        $encrypted = $this->service->encryptCredentials($password);

        $isValid = $this->service->validateEncryptedCredentials(
            $encrypted['encrypted_password'],
            $encrypted['salt'],
            $encrypted['verification_hash']
        );

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_returns_false_for_invalid_credentials()
    {
        $password = 'test_password_123';

        $encrypted = $this->service->encryptCredentials($password);

        $isValid = $this->service->validateEncryptedCredentials(
            $encrypted['encrypted_password'],
            'wrong_salt',
            $encrypted['verification_hash']
        );

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_generates_secure_password_with_default_length()
    {
        $password = $this->service->generateSecurePassword();

        $this->assertEquals(16, strlen($password));
        $this->assertMatchesRegularExpression('/[a-z]/', $password); // Has lowercase
        $this->assertMatchesRegularExpression('/[A-Z]/', $password); // Has uppercase
        $this->assertMatchesRegularExpression('/[0-9]/', $password); // Has numbers
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $password); // Has special chars
    }

    /** @test */
    public function it_generates_secure_password_with_custom_length()
    {
        $password = $this->service->generateSecurePassword(24);

        $this->assertEquals(24, strlen($password));
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $password);
    }

    /** @test */
    public function it_generates_different_passwords_each_time()
    {
        $password1 = $this->service->generateSecurePassword();
        $password2 = $this->service->generateSecurePassword();

        $this->assertNotEquals($password1, $password2);
    }

    /** @test */
    public function it_securely_wipes_password()
    {
        $password = 'sensitive_password_123';
        $originalPassword = $password;

        $this->service->secureWipe($password);

        $this->assertEmpty($password);
        $this->assertNotEquals($originalPassword, $password);
    }

    /** @test */
    public function it_creates_credential_backup()
    {
        $password = 'test_password_123';
        $encrypted = $this->service->encryptCredentials($password);

        $backup = $this->service->createCredentialBackup($encrypted);

        $this->assertNotEmpty($backup);
        $this->assertIsString($backup);
    }

    /** @test */
    public function it_restores_credentials_from_backup()
    {
        $password = 'test_password_123';
        $encrypted = $this->service->encryptCredentials($password);

        $backup = $this->service->createCredentialBackup($encrypted);
        $restored = $this->service->restoreFromBackup($backup);

        $this->assertEquals($encrypted['encrypted_password'], $restored['encrypted_password']);
        $this->assertEquals($encrypted['salt'], $restored['salt']);
        $this->assertEquals($encrypted['verification_hash'], $restored['verification_hash']);
        $this->assertArrayHasKey('backup_id', $restored);
        $this->assertArrayHasKey('backup_created_at', $restored);
    }

    /** @test */
    public function it_throws_exception_for_invalid_backup_data()
    {
        $invalidBackup = Crypt::encryptString('invalid_json_data');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to restore credentials from backup');

        $this->service->restoreFromBackup($invalidBackup);
    }

    /** @test */
    public function it_throws_exception_for_malformed_backup()
    {
        $malformedData = ['invalid' => 'structure'];
        $malformedBackup = Crypt::encryptString(json_encode($malformedData));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to restore credentials from backup');

        $this->service->restoreFromBackup($malformedBackup);
    }

    /** @test */
    public function it_checks_if_credentials_need_rotation()
    {
        // Recent encryption (should not need rotation)
        $recentDate = now()->subDays(30)->toISOString();
        $needsRotation = $this->service->needsRotation($recentDate, 90);
        $this->assertFalse($needsRotation);

        // Old encryption (should need rotation)
        $oldDate = now()->subDays(100)->toISOString();
        $needsRotation = $this->service->needsRotation($oldDate, 90);
        $this->assertTrue($needsRotation);
    }

    /** @test */
    public function it_returns_true_for_invalid_date_format()
    {
        $needsRotation = $this->service->needsRotation('invalid_date_format');
        $this->assertTrue($needsRotation); // Should default to true for safety
    }

    /** @test */
    public function it_gets_encryption_metadata()
    {
        $password = 'test_password_123';
        $encrypted = $this->service->encryptCredentials($password);

        $metadata = $this->service->getEncryptionMetadata($encrypted);

        $this->assertTrue($metadata['has_salt']);
        $this->assertTrue($metadata['has_verification_hash']);
        $this->assertEquals(32, $metadata['salt_length']);
        $this->assertEquals('AES-256-CBC', $metadata['encryption_algorithm']);
        $this->assertFalse($metadata['needs_rotation']); // Should be false for new encryption
        $this->assertNotNull($metadata['encrypted_at']);
    }

    /** @test */
    public function it_gets_metadata_for_incomplete_data()
    {
        $incompleteData = [
            'encrypted_password' => 'some_encrypted_data'
        ];

        $metadata = $this->service->getEncryptionMetadata($incompleteData);

        $this->assertFalse($metadata['has_salt']);
        $this->assertFalse($metadata['has_verification_hash']);
        $this->assertEquals(0, $metadata['salt_length']);
        $this->assertTrue($metadata['needs_rotation']); // Should be true when encrypted_at is missing
        $this->assertNull($metadata['encrypted_at']);
    }

    /** @test */
    public function it_handles_encryption_failure_gracefully()
    {
        // Mock Crypt to throw an exception
        Crypt::shouldReceive('encryptString')
            ->once()
            ->andThrow(new \Exception('Encryption failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to encrypt email credentials');

        $this->service->encryptCredentials('test_password');
    }

    /** @test */
    public function it_handles_decryption_failure_gracefully()
    {
        $password = 'test_password_123';
        $encrypted = $this->service->encryptCredentials($password);

        // Mock Crypt to throw an exception on decryption
        Crypt::shouldReceive('decryptString')
            ->once()
            ->andThrow(new \Exception('Decryption failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to decrypt email credentials');

        $this->service->decryptCredentials(
            $encrypted['encrypted_password'],
            $encrypted['salt']
        );
    }

    /** @test */
    public function it_handles_backup_creation_failure()
    {
        $encrypted = [
            'encrypted_password' => 'test',
            'salt' => 'test_salt',
            'verification_hash' => 'test_hash'
        ];

        // Mock Crypt to throw an exception
        Crypt::shouldReceive('encryptString')
            ->once()
            ->andThrow(new \Exception('Backup encryption failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create credential backup');

        $this->service->createCredentialBackup($encrypted);
    }
}
