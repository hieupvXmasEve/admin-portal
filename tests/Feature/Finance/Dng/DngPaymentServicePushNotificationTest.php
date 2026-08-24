<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
        'status' => 'intake_course',
    ]);
});

function makePushingDngPaymentService(): DngPaymentService
{
    return new DngPaymentService(
        Mockery::mock(DngClient::class),
        Mockery::mock(PaymentService::class),
        app(DomainEventPublisher::class),
    );
}

it('includes the email channel when the campus already has its auto-provisioned template', function () {
    // CampusObserver auto-provisions the default dng_payment_pushed template
    // for every new campus (NotificationEmailTemplateProvisioner), so this is
    // the normal state a real campus is in.
    expect(NotificationEmailTemplate::where('campus_id', $this->campus->id)
        ->where('type_key', NotificationTemplateTypeKey::DngPaymentPushed)
        ->exists())->toBeTrue();

    $service = makePushingDngPaymentService();
    $service->publishPushNotification($this->student->id, $this->campus->id, $this->student->full_name, 999, '1000000', 'Tuition');

    $outbox = NotificationEventOutbox::where('event_name', 'finance.dng_payment_pushed')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['channels'])->toBe(['realtime', 'email']);
});

it('skips the email channel and logs a notice when no admin template is configured yet', function () {
    // Simulate the edge case a real campus is not normally in: no
    // notification_email_templates row for this type_key/campus.
    NotificationEmailTemplate::where('campus_id', $this->campus->id)
        ->where('type_key', NotificationTemplateTypeKey::DngPaymentPushed)
        ->delete();

    Log::shouldReceive('notice')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'email template not configured'));

    $service = makePushingDngPaymentService();
    $service->publishPushNotification($this->student->id, $this->campus->id, $this->student->full_name, 999, '1000000', 'Tuition');

    $outbox = NotificationEventOutbox::where('event_name', 'finance.dng_payment_pushed')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['channels'])->toBe(['realtime']);
});
