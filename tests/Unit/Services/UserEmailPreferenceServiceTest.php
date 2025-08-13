<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\UserEmailPreferenceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserEmailPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserEmailPreferenceService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserEmailPreferenceService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_gets_user_preferences()
    {
        UserEmailPreference::factory()->count(3)->create(['user_id' => $this->user->id]);

        $preferences = $this->service->getUserPreferences($this->user->id);

        $this->assertInstanceOf(Collection::class, $preferences);
        $this->assertEquals(3, $preferences->count());
    }

    /** @test */
    public function it_gets_specific_user_preference()
    {
        $preference = UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME
        ]);

        $found = $this->service->getUserPreference($this->user->id, UserEmailPreference::TYPE_WELCOME);

        $this->assertInstanceOf(UserEmailPreference::class, $found);
        $this->assertEquals($preference->id, $found->id);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_preference()
    {
        $found = $this->service->getUserPreference($this->user->id, UserEmailPreference::TYPE_WELCOME);

        $this->assertNull($found);
    }

    /** @test */
    public function it_gets_or_creates_user_preference()
    {
        // First call should create
        $preference = $this->service->getOrCreateUserPreference($this->user->id, UserEmailPreference::TYPE_WELCOME);

        $this->assertInstanceOf(UserEmailPreference::class, $preference);
        $this->assertEquals($this->user->id, $preference->user_id);
        $this->assertEquals(UserEmailPreference::TYPE_WELCOME, $preference->notification_type);

        // Second call should return existing
        $samePreference = $this->service->getOrCreateUserPreference($this->user->id, UserEmailPreference::TYPE_WELCOME);

        $this->assertEquals($preference->id, $samePreference->id);
    }

    /** @test */
    public function it_updates_user_preference()
    {
        $preference = $this->service->updateUserPreference(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME,
            true,
            UserEmailPreference::FREQUENCY_DAILY,
            ['custom_setting' => 'value']
        );

        $this->assertInstanceOf(UserEmailPreference::class, $preference);
        $this->assertTrue($preference->is_enabled);
        $this->assertEquals(UserEmailPreference::FREQUENCY_DAILY, $preference->frequency);
        $this->assertEquals(['custom_setting' => 'value'], $preference->settings);
    }

    /** @test */
    public function it_validates_user_id_on_update()
    {
        $this->expectException(ValidationException::class);

        $this->service->updateUserPreference(
            999, // Non-existent user
            UserEmailPreference::TYPE_WELCOME,
            true
        );
    }

    /** @test */
    public function it_validates_notification_type_on_update()
    {
        $this->expectException(ValidationException::class);

        $this->service->updateUserPreference(
            $this->user->id,
            'invalid_type',
            true
        );
    }

    /** @test */
    public function it_validates_frequency_on_update()
    {
        $this->expectException(ValidationException::class);

        $this->service->updateUserPreference(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME,
            true,
            'invalid_frequency'
        );
    }

    /** @test */
    public function it_bulk_updates_user_preferences()
    {
        $preferences = [
            [
                'notification_type' => UserEmailPreference::TYPE_WELCOME,
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            [
                'notification_type' => UserEmailPreference::TYPE_GRADE_NOTIFICATION,
                'is_enabled' => false,
                'frequency' => UserEmailPreference::FREQUENCY_NEVER,
            ],
        ];

        $results = $this->service->bulkUpdateUserPreferences($this->user->id, $preferences);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(UserEmailPreference::class, $results[0]);
        $this->assertTrue($results[0]->is_enabled);
        $this->assertFalse($results[1]->is_enabled);
    }

    /** @test */
    public function it_rolls_back_on_bulk_update_failure()
    {
        $preferences = [
            [
                'notification_type' => UserEmailPreference::TYPE_WELCOME,
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
            ],
            [
                'notification_type' => 'invalid_type', // This will cause failure
                'is_enabled' => false,
                'frequency' => UserEmailPreference::FREQUENCY_NEVER,
            ],
        ];

        $this->expectException(ValidationException::class);

        $this->service->bulkUpdateUserPreferences($this->user->id, $preferences);

        // Verify no preferences were created
        $this->assertEquals(0, UserEmailPreference::where('user_id', $this->user->id)->count());
    }

    /** @test */
    public function it_initializes_default_preferences()
    {
        $preferences = $this->service->initializeDefaultPreferences($this->user->id);

        $this->assertGreaterThan(0, $preferences->count());

        // Check that all default types are created
        $expectedTypes = [
            UserEmailPreference::TYPE_WELCOME,
            UserEmailPreference::TYPE_GRADE_NOTIFICATION,
            UserEmailPreference::TYPE_COURSE_REGISTRATION,
            UserEmailPreference::TYPE_ACADEMIC_HOLD,
            UserEmailPreference::TYPE_ENROLLMENT_CONFIRMATION,
            UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
            UserEmailPreference::TYPE_SYSTEM_ANNOUNCEMENT,
            UserEmailPreference::TYPE_REMINDER,
        ];

        foreach ($expectedTypes as $type) {
            $this->assertTrue($preferences->contains('notification_type', $type));
        }
    }

    /** @test */
    public function it_opts_out_user()
    {
        // Create some preferences first
        UserEmailPreference::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_enabled' => true
        ]);

        $updatedCount = $this->service->optOutUser($this->user->id);

        $this->assertEquals(3, $updatedCount);

        // Verify all preferences are disabled
        $enabledCount = UserEmailPreference::where('user_id', $this->user->id)
            ->where('is_enabled', true)
            ->count();

        $this->assertEquals(0, $enabledCount);
    }

    /** @test */
    public function it_opts_in_user()
    {
        // Create some disabled preferences first
        UserEmailPreference::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_enabled' => false
        ]);

        $updatedCount = $this->service->optInUser($this->user->id);

        $this->assertEquals(3, $updatedCount);

        // Verify all preferences are enabled
        $disabledCount = UserEmailPreference::where('user_id', $this->user->id)
            ->where('is_enabled', false)
            ->count();

        $this->assertEquals(0, $disabledCount);
    }

    /** @test */
    public function it_unsubscribes_from_type()
    {
        $preference = $this->service->unsubscribeFromType(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertFalse($preference->is_enabled);
        $this->assertEquals(UserEmailPreference::FREQUENCY_NEVER, $preference->frequency);
    }

    /** @test */
    public function it_subscribes_to_type()
    {
        $preference = $this->service->subscribeToType(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME,
            UserEmailPreference::FREQUENCY_DAILY
        );

        $this->assertTrue($preference->is_enabled);
        $this->assertEquals(UserEmailPreference::FREQUENCY_DAILY, $preference->frequency);
    }

    /** @test */
    public function it_checks_if_user_can_receive_notification()
    {
        // Create enabled preference
        UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        $canReceive = $this->service->canUserReceiveNotification(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertTrue($canReceive);
    }

    /** @test */
    public function it_creates_default_preference_when_checking_nonexistent()
    {
        $canReceive = $this->service->canUserReceiveNotification(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertTrue($canReceive);

        // Verify preference was created
        $preference = UserEmailPreference::where('user_id', $this->user->id)
            ->where('notification_type', UserEmailPreference::TYPE_WELCOME)
            ->first();

        $this->assertNotNull($preference);
    }

    /** @test */
    public function it_marks_notification_as_sent()
    {
        $preference = UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
            'last_sent_at' => null
        ]);

        $result = $this->service->markNotificationAsSent(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertTrue($result);

        $preference->refresh();
        $this->assertNotNull($preference->last_sent_at);
    }

    /** @test */
    public function it_returns_false_when_marking_nonexistent_preference()
    {
        $result = $this->service->markNotificationAsSent(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertFalse($result);
    }

    /** @test */
    public function it_gets_users_for_notification_type()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create preferences - only user1 and user2 should receive notifications
        UserEmailPreference::factory()->create([
            'user_id' => $user1->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        UserEmailPreference::factory()->create([
            'user_id' => $user2->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_DAILY
        ]);

        UserEmailPreference::factory()->create([
            'user_id' => $user3->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
            'is_enabled' => false, // Disabled
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        $users = $this->service->getUsersForNotificationType(UserEmailPreference::TYPE_WELCOME);

        $this->assertEquals(2, $users->count());
        $this->assertTrue($users->contains('id', $user1->id));
        $this->assertTrue($users->contains('id', $user2->id));
        $this->assertFalse($users->contains('id', $user3->id));
    }

    /** @test */
    public function it_gets_user_notification_stats()
    {
        // Create various preferences
        UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_DAILY
        ]);

        UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'is_enabled' => false,
            'frequency' => UserEmailPreference::FREQUENCY_NEVER
        ]);

        $stats = $this->service->getUserNotificationStats($this->user->id);

        $this->assertArrayHasKey('total_types', $stats);
        $this->assertArrayHasKey('enabled_types', $stats);
        $this->assertArrayHasKey('disabled_types', $stats);
        $this->assertArrayHasKey('immediate_notifications', $stats);
        $this->assertArrayHasKey('daily_notifications', $stats);
        $this->assertArrayHasKey('weekly_notifications', $stats);
        $this->assertArrayHasKey('never_notifications', $stats);
        $this->assertArrayHasKey('last_updated', $stats);

        $this->assertEquals(2, $stats['enabled_types']);
        $this->assertEquals(1, $stats['disabled_types']);
        $this->assertEquals(1, $stats['immediate_notifications']);
        $this->assertEquals(1, $stats['daily_notifications']);
        $this->assertEquals(1, $stats['never_notifications']);
    }

    /** @test */
    public function it_generates_unsubscribe_token()
    {
        $token = $this->service->generateUnsubscribeToken(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /** @test */
    public function it_processes_unsubscribe_token()
    {
        $token = $this->service->generateUnsubscribeToken(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $result = $this->service->processUnsubscribeToken($token);

        $this->assertTrue($result);

        // Verify user was unsubscribed
        $preference = UserEmailPreference::where('user_id', $this->user->id)
            ->where('notification_type', UserEmailPreference::TYPE_WELCOME)
            ->first();

        $this->assertNotNull($preference);
        $this->assertFalse($preference->is_enabled);
    }

    /** @test */
    public function it_processes_unsubscribe_all_token()
    {
        // Create some preferences first
        UserEmailPreference::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_enabled' => true
        ]);

        $token = $this->service->generateUnsubscribeToken($this->user->id); // No specific type = all

        $result = $this->service->processUnsubscribeToken($token);

        $this->assertTrue($result);

        // Verify all preferences are disabled
        $enabledCount = UserEmailPreference::where('user_id', $this->user->id)
            ->where('is_enabled', true)
            ->count();

        $this->assertEquals(0, $enabledCount);
    }

    /** @test */
    public function it_returns_false_for_invalid_unsubscribe_token()
    {
        $result = $this->service->processUnsubscribeToken('invalid_token');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_deletes_user_preference()
    {
        $preference = UserEmailPreference::factory()->create([
            'user_id' => $this->user->id,
            'notification_type' => UserEmailPreference::TYPE_WELCOME
        ]);

        $result = $this->service->deleteUserPreference(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertTrue($result);
        $this->assertDatabaseMissing('user_email_preferences', ['id' => $preference->id]);
    }

    /** @test */
    public function it_returns_false_when_deleting_nonexistent_preference()
    {
        $result = $this->service->deleteUserPreference(
            $this->user->id,
            UserEmailPreference::TYPE_WELCOME
        );

        $this->assertFalse($result);
    }

    /** @test */
    public function it_deletes_all_user_preferences()
    {
        UserEmailPreference::factory()->count(3)->create(['user_id' => $this->user->id]);

        $deletedCount = $this->service->deleteAllUserPreferences($this->user->id);

        $this->assertEquals(3, $deletedCount);
        $this->assertEquals(0, UserEmailPreference::where('user_id', $this->user->id)->count());
    }

    /** @test */
    public function it_returns_zero_when_deleting_no_preferences()
    {
        $deletedCount = $this->service->deleteAllUserPreferences($this->user->id);

        $this->assertEquals(0, $deletedCount);
    }
}
