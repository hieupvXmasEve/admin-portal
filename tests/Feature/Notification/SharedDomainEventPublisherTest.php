<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Student;
use App\Models\User;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('persists one deterministic outbox event after the source transaction commits', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);

    $event = new DomainEvent(
        name: 'academic.course_completed',
        deduplicationKey: 'academic.course_completed:offering:42:student:7:grade:A',
        occurredAt: CarbonImmutable::parse('2026-07-18 09:00:00'),
        aggregateType: 'course_offering',
        aggregateId: '42',
        campusId: 1,
        actorUserId: null,
        payload: ['student_id' => 7],
    );

    DB::transaction(function () use ($event) {
        app(DomainEventPublisher::class)->publishAfterCommit($event);

        expect(NotificationEventOutbox::query()->count())->toBe(0);
    });

    expect(NotificationEventOutbox::query()->count())->toBe(1)
        ->and(NotificationEventOutbox::query()->sole()->event_id)->toBe($event->eventId());

    app(DomainEventPublisher::class)->publish($event);

    expect(NotificationEventOutbox::query()->count())->toBe(1);
});

it('does not persist notification work when the source transaction rolls back', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);

    $event = new DomainEvent(
        name: 'academic.course_stage_changed',
        deduplicationKey: 'academic.course_stage_changed:student:7:semester:3:from:egc:to:course',
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'student',
        aggregateId: '7',
        campusId: 1,
        actorUserId: null,
        payload: ['student_id' => 7],
    );

    try {
        DB::transaction(function () use ($event) {
            app(DomainEventPublisher::class)->publishAfterCommit($event);

            throw new RuntimeException('roll back source transition');
        });
    } catch (RuntimeException) {
        // The source transition intentionally rolled back.
    }

    expect(NotificationEventOutbox::query()->count())->toBe(0);
});

it('keeps academic warnings deliverable when lifecycle V2 feature flags are disabled', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => false,
        'notification.write_mode' => 'off',
    ]);

    $event = new DomainEvent(
        name: 'academic.warning_sent',
        deduplicationKey: 'academic.warning_sent:attendance:7:course:42:session:3',
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'student_warning_log',
        aggregateId: '5',
        campusId: 1,
        actorUserId: 2,
        payload: ['student_id' => 7, 'warning_type' => 'attendance_exceeded'],
    );

    app(DomainEventPublisher::class)->publish($event);

    expect(NotificationEventOutbox::query()->count())->toBe(1)
        ->and(NotificationEventOutbox::query()->sole()->event_id)->toBe($event->eventId());
});

it('does not duplicate messages or deliveries when an Academic event is consumed again', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
        'notification.campus.strict_isolation' => true,
    ]);
    Queue::fake();

    $campus = Campus::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'user_id' => User::factory()->create()->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $event = new DomainEvent(
        name: 'academic.course_completed',
        deduplicationKey: 'academic.course_completed:course_offering:42:student:'.$student->id.':grade:A:score:85.000:passed:1',
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'course_offering',
        aggregateId: '42',
        campusId: $campus->id,
        actorUserId: null,
        payload: [
            'student_id' => $student->id,
            'data' => ['title' => 'Course Completed: TEST', 'body' => 'Course completion message.'],
        ],
    );

    app(DomainEventPublisher::class)->publish($event);
    $outbox = NotificationEventOutbox::query()->sole();

    app(HandleOutboxEventAction::class)->run($outbox);
    app(HandleOutboxEventAction::class)->run($outbox->fresh());

    expect(NotificationMessage::query()->count())->toBe(1)
        ->and(NotificationDelivery::query()->count())->toBe(1);
});
