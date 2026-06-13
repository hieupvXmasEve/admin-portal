<?php

declare(strict_types=1);

use App\Models\Student;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;

it('treats financial statuses as active collection students', function () {
    $student = new Student(['status' => 'intake_course']);

    expect(LifecycleDueItemPredicate::isLifecycleException($student))->toBeFalse();
});

it('treats deferred dropout and transfer statuses as lifecycle exceptions', function () {
    foreach (['deferred', 'dropout', 'dropout_transfer'] as $status) {
        $student = new Student(['status' => $status]);
        expect(LifecycleDueItemPredicate::isLifecycleException($student))->toBeTrue();
    }
});

it('treats missing students as lifecycle exceptions', function () {
    expect(LifecycleDueItemPredicate::isLifecycleException(null))->toBeTrue();
});

it('treats inactive non-financial statuses as lifecycle exceptions', function () {
    foreach (['pending', 'inactive', 'graduated'] as $status) {
        $student = new Student(['status' => $status]);
        expect(LifecycleDueItemPredicate::isLifecycleException($student))->toBeTrue();
    }
});
