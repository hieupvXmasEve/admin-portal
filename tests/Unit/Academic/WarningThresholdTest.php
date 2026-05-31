<?php

declare(strict_types=1);

use App\Modules\Academic\Queries\ListWarningCenterQuery;
use App\Modules\Academic\Support\WarningDedupe;

it('converts syllabus attendance percentage thresholds into absence milestones', function (): void {
    $query = new ListWarningCenterQuery;

    expect($query->allowedAbsences(totalSessions: 20, minAttendanceThreshold: 80.0))->toBe(4)
        ->and($query->warningAbsences(totalSessions: 20, minAttendanceThreshold: 80.0, warningRatio: 0.5))->toBe(2)
        ->and($query->allowedAbsences(totalSessions: 15, minAttendanceThreshold: 80.0))->toBe(3)
        ->and($query->warningAbsences(totalSessions: 15, minAttendanceThreshold: 80.0, warningRatio: 0.5))->toBe(1);
});

it('creates different attendance dedupe keys for the next absence milestone', function (): void {
    $first = WarningDedupe::attendance('attendance_early_warning', 10, 20, 30, 2);
    $next = WarningDedupe::attendance('attendance_early_warning', 10, 20, 31, 3);

    expect($first)->not->toBe($next)
        ->and(strlen($first))->toBe(64)
        ->and(strlen($next))->toBe(64);
});
