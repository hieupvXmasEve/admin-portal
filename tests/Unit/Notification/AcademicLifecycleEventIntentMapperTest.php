<?php

declare(strict_types=1);

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Support\EventIntentMapper;
use Carbon\CarbonImmutable;

it('maps Academic lifecycle facts to the existing student notification intent', function (string $eventName, string $typeKey) {
    $intents = (new EventIntentMapper)->map(new DomainEventEnvelope(
        eventId: '8e4f52a8-4fc3-4c83-a7c5-dca1d1a3be1e',
        eventName: $eventName,
        eventVersion: 1,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'student',
        aggregateId: '7',
        campusId: 1,
        actorUserId: null,
        payload: [
            'student_id' => 7,
            'data' => ['title' => 'Title', 'body' => 'Body'],
        ],
    ));

    expect($intents)->toHaveCount(1)
        ->and($intents[0]->typeKey)->toBe($typeKey)
        ->and($intents[0]->recipientTargets)->toBe([['type' => 'student', 'id' => 7]])
        ->and($intents[0]->channels)->toBe(['realtime']);
})->with([
    ['academic.course_completed', 'course_completed'],
    ['academic.course_stage_changed', 'course_stage_changed'],
    ['academic.egc_course_completed', 'egc_course_completed'],
    ['academic.egc_program_completed', 'egc_program_completed'],
]);

it('uses the warning event facts to retain warning type and configured channels', function () {
    $intents = (new EventIntentMapper)->map(new DomainEventEnvelope(
        eventId: '91b9302a-c8f9-478b-b08e-019ba9037578',
        eventName: 'academic.warning_sent',
        eventVersion: 1,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'student_warning_log',
        aggregateId: '5',
        campusId: 1,
        actorUserId: 2,
        payload: [
            'student_id' => 7,
            'warning_type' => 'attendance_exceeded',
            'requested_channels' => ['email', 'realtime'],
            'data' => ['title' => 'Attendance warning', 'body' => 'You have missed too many classes.'],
        ],
    ));

    expect($intents)->toHaveCount(1)
        ->and($intents[0]->typeKey)->toBe('attendance_exceeded')
        ->and($intents[0]->channels)->toBe(['email', 'realtime'])
        ->and($intents[0]->recipientTargets)->toBe([['type' => 'student', 'id' => 7]]);
});
