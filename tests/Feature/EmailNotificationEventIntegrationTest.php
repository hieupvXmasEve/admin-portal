<?php

namespace Tests\Feature;

use App\Events\AcademicHoldPlaced;
use App\Events\AssessmentDeadlineApproaching;
use App\Events\CourseRegistrationOpened;
use App\Events\EnrollmentConfirmed;
use App\Events\GradePublished;
use App\Jobs\ProcessNotificationJob;
use App\Listeners\AcademicHoldListener;
use App\Listeners\AssessmentDeadlineListener;
use App\Listeners\CourseRegistrationListener;
use App\Listeners\EnrollmentConfirmedListener;
use App\Listeners\GradePublishedListener;
use App\Models\AcademicHold;
use App\Models\AssessmentComponent;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailNotificationEventIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);
    }

    /** @test */
    public function it_sends_course_registration_notification()
    {
        Queue::fake();
        Event::fake();

        $user = User::factory()->create();
        $courseOffering = CourseOffering::factory()->create();

        // Create email template for course registration
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'name' => 'course_registration_opened',
            'subject' => 'Course Registration Open for {{course_name}}',
            'html_content' => '<p>Registration is now open for {{course_name}}</p>',
            'variables' => ['course_name'],
            'is_active' => true
        ]);

        // Ensure user has preferences enabled
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new CourseRegistrationOpened($courseOffering, [$user]);
        event($event);

        // Verify event was fired
        Event::assertDispatched(CourseRegistrationOpened::class);

        // Manually trigger the listener to test integration
        $listener = new CourseRegistrationListener();
        $listener->handle($event);

        // Verify notification job was queued
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_sends_grade_published_notification()
    {
        Queue::fake();
        Event::fake();

        $student = Student::factory()->create();
        $user = $student->user;
        $courseOffering = CourseOffering::factory()->create();
        $assessmentComponent = AssessmentComponent::factory()->create([
            'course_offering_id' => $courseOffering->id
        ]);

        // Create email template for grade notification
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_GRADE_NOTIFICATION,
            'name' => 'grade_published',
            'subject' => 'Grade Published for {{course_name}}',
            'html_content' => '<p>Your grade for {{course_name}} has been published</p>',
            'variables' => ['course_name', 'student_name'],
            'is_active' => true
        ]);

        // Ensure user has preferences enabled
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_GRADE_NOTIFICATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new GradePublished($student, $assessmentComponent, 85.5);
        event($event);

        // Verify event was fired
        Event::assertDispatched(GradePublished::class);

        // Manually trigger the listener
        $listener = new GradePublishedListener();
        $listener->handle($event);

        // Verify notification job was queued
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_sends_academic_hold_notification()
    {
        Queue::fake();
        Event::fake();

        $student = Student::factory()->create();
        $user = $student->user;
        $academicHold = AcademicHold::factory()->create([
            'student_id' => $student->id
        ]);

        // Create email template for academic hold
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_ACADEMIC_HOLD,
            'name' => 'academic_hold_placed',
            'subject' => 'Academic Hold Placed - {{hold_type}}',
            'html_content' => '<p>An academic hold has been placed on your account: {{hold_reason}}</p>',
            'variables' => ['hold_type', 'hold_reason', 'student_name'],
            'is_active' => true
        ]);

        // Ensure user has preferences enabled
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_ACADEMIC_HOLD,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new AcademicHoldPlaced($academicHold);
        event($event);

        // Verify event was fired
        Event::assertDispatched(AcademicHoldPlaced::class);

        // Manually trigger the listener
        $listener = new AcademicHoldListener();
        $listener->handle($event);

        // Verify notification job was queued
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_sends_enrollment_confirmation_notification()
    {
        Queue::fake();
        Event::fake();

        $student = Student::factory()->create();
        $user = $student->user;
        $courseOffering = CourseOffering::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id
        ]);

        // Create email template for enrollment confirmation
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_ENROLLMENT_CONFIRMATION,
            'name' => 'enrollment_confirmed',
            'subject' => 'Enrollment Confirmed - {{course_name}}',
            'html_content' => '<p>Your enrollment in {{course_name}} has been confirmed</p>',
            'variables' => ['course_name', 'student_name'],
            'is_active' => true
        ]);

        // Ensure user has preferences enabled
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_ENROLLMENT_CONFIRMATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new EnrollmentConfirmed($enrollment);
        event($event);

        // Verify event was fired
        Event::assertDispatched(EnrollmentConfirmed::class);

        // Manually trigger the listener
        $listener = new EnrollmentConfirmedListener();
        $listener->handle($event);

        // Verify notification job was queued
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_sends_assessment_deadline_reminder()
    {
        Queue::fake();
        Event::fake();

        $student = Student::factory()->create();
        $user = $student->user;
        $courseOffering = CourseOffering::factory()->create();
        $assessmentComponent = AssessmentComponent::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'due_date' => now()->addDays(3)
        ]);

        // Create email template for assessment deadline
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_ASSESSMENT_DEADLINE,
            'name' => 'assessment_deadline_approaching',
            'subject' => 'Assessment Due Soon - {{assessment_name}}',
            'html_content' => '<p>{{assessment_name}} is due on {{due_date}}</p>',
            'variables' => ['assessment_name', 'due_date', 'student_name'],
            'is_active' => true
        ]);

        // Ensure user has preferences enabled
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_DAILY
        ]);

        // Fire the event
        $event = new AssessmentDeadlineApproaching($assessmentComponent, [$student]);
        event($event);

        // Verify event was fired
        Event::assertDispatched(AssessmentDeadlineApproaching::class);

        // Manually trigger the listener
        $listener = new AssessmentDeadlineListener();
        $listener->handle($event);

        // Verify notification job was queued
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_respects_user_preferences_in_event_notifications()
    {
        Queue::fake();

        $student = Student::factory()->create();
        $user = $student->user;
        $courseOffering = CourseOffering::factory()->create();

        // Create template
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'name' => 'course_registration_opened',
            'is_active' => true
        ]);

        // User has disabled course registration notifications
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
            'is_enabled' => false,
            'frequency' => UserEmailPreference::FREQUENCY_NEVER
        ]);

        // Fire the event
        $event = new CourseRegistrationOpened($courseOffering, [$user]);
        $listener = new CourseRegistrationListener();
        $listener->handle($event);

        // Verify no notification job was queued due to user preferences
        Queue::assertNotPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_handles_batch_notifications_for_multiple_users()
    {
        Queue::fake();

        $users = User::factory()->count(5)->create();
        $courseOffering = CourseOffering::factory()->create();

        // Create template
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'name' => 'course_registration_opened',
            'subject' => 'Course Registration Open',
            'html_content' => '<p>Registration is now open</p>',
            'is_active' => true
        ]);

        // Enable preferences for all users
        foreach ($users as $user) {
            UserEmailPreference::factory()->create([
                'user_id' => $user->id,
                'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
            ]);
        }

        // Fire the event with multiple users
        $event = new CourseRegistrationOpened($courseOffering, $users->toArray());
        $listener = new CourseRegistrationListener();
        $listener->handle($event);

        // Verify notification jobs were queued for all users
        Queue::assertPushed(ProcessNotificationJob::class, 5);
    }

    /** @test */
    public function it_handles_notification_service_integration()
    {
        Queue::fake();

        $user = User::factory()->create();
        $courseOffering = CourseOffering::factory()->create();

        // Create template
        $template = EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'name' => 'course_registration_opened',
            'subject' => 'Course Registration Open for {{course_name}}',
            'html_content' => '<p>Registration is now open for {{course_name}}</p>',
            'variables' => ['course_name'],
            'is_active' => true
        ]);

        // Enable user preferences
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Use notification service directly
        $this->notificationService->sendAcademicNotification(
            'course_registration_opened',
            [$user],
            ['course_name' => $courseOffering->unit->name ?? 'Test Course']
        );

        // Verify notification was processed
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_handles_scheduled_reminder_notifications()
    {
        Queue::fake();

        $users = User::factory()->count(3)->create();

        // Create template for reminders
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_REMINDER,
            'name' => 'assessment_reminder',
            'subject' => 'Reminder: {{reminder_title}}',
            'html_content' => '<p>This is a reminder about {{reminder_message}}</p>',
            'variables' => ['reminder_title', 'reminder_message'],
            'is_active' => true
        ]);

        // Enable preferences for all users
        foreach ($users as $user) {
            UserEmailPreference::factory()->create([
                'user_id' => $user->id,
                'notification_type' => UserEmailPreference::TYPE_REMINDER,
                'is_enabled' => true,
                'frequency' => UserEmailPreference::FREQUENCY_DAILY
            ]);
        }

        // Schedule a reminder
        $this->notificationService->scheduleReminder(
            'assessment_reminder',
            $users->toArray(),
            now()->addHour(),
            [
                'reminder_title' => 'Assignment Due Tomorrow',
                'reminder_message' => 'Your assignment is due tomorrow at 11:59 PM'
            ]
        );

        // Verify reminder job was scheduled
        Queue::assertPushed(\App\Jobs\SendReminderJob::class);
    }

    /** @test */
    public function it_logs_notification_events_properly()
    {
        Queue::fake();

        $user = User::factory()->create();
        $courseOffering = CourseOffering::factory()->create();

        // Create template
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_COURSE_REGISTRATION,
            'name' => 'course_registration_opened',
            'subject' => 'Course Registration Open',
            'html_content' => '<p>Registration is now open</p>',
            'is_active' => true
        ]);

        // Enable user preferences
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new CourseRegistrationOpened($courseOffering, [$user]);
        $listener = new CourseRegistrationListener();
        $listener->handle($event);

        // Verify that email logs would be created (through job processing)
        Queue::assertPushed(ProcessNotificationJob::class, function ($job) use ($user) {
            return in_array($user->email, $job->recipients);
        });
    }

    /** @test */
    public function it_handles_notification_frequency_preferences()
    {
        Queue::fake();

        $user = User::factory()->create();
        $assessmentComponent = AssessmentComponent::factory()->create();

        // Create template
        EmailTemplate::factory()->create([
            'type' => EmailTemplate::TYPE_ASSESSMENT_DEADLINE,
            'name' => 'assessment_deadline_approaching',
            'is_active' => true
        ]);

        // User prefers daily notifications (not immediate)
        $preference = UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_DAILY,
            'last_sent_at' => now()->subHours(12) // Last sent 12 hours ago
        ]);

        // Fire the event
        $event = new AssessmentDeadlineApproaching($assessmentComponent, [$user]);
        $listener = new AssessmentDeadlineListener();
        $listener->handle($event);

        // For daily frequency, notification should be queued but might be batched
        // The exact behavior depends on the listener implementation
        Queue::assertPushed(ProcessNotificationJob::class);
    }

    /** @test */
    public function it_handles_missing_email_templates_gracefully()
    {
        Queue::fake();

        $user = User::factory()->create();
        $courseOffering = CourseOffering::factory()->create();

        // No email template created for this notification type

        // Enable user preferences
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
            'is_enabled' => true,
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE
        ]);

        // Fire the event
        $event = new CourseRegistrationOpened($courseOffering, [$user]);
        $listener = new CourseRegistrationListener();

        // This should not throw an exception but should handle gracefully
        $listener->handle($event);

        // Verify no job was queued due to missing template
        Queue::assertNotPushed(ProcessNotificationJob::class);
    }
}
