<?php

declare(strict_types=1);

use App\Actions\Academic\DetermineAcademicStandingAction;

beforeEach(function () {
    $this->action = new DetermineAcademicStandingAction();
});

it('returns normal when gpa is above threshold', function () {
    expect($this->action->execute(75.0))->toBe('normal');
});

it('returns normal at exact threshold (50.0)', function () {
    expect($this->action->execute(50.0))->toBe('normal');
});

it('returns warning just below threshold', function () {
    expect($this->action->execute(49.999))->toBe('warning');
});

it('returns warning for zero gpa', function () {
    expect($this->action->execute(0.0))->toBe('warning');
});

it('uses the 100-point scale threshold, not the 4-point scale', function () {
    // Regression: pre-fix code compared against 2.0 which would have classified
    // a 49.0 (failing on 100 scale) as "normal" — this asserts the new threshold.
    expect($this->action->execute(2.0))->toBe('warning');
    expect($this->action->execute(49.0))->toBe('warning');
});

it('exposes the threshold constant for callers', function () {
    expect(DetermineAcademicStandingAction::NORMAL_STANDING_THRESHOLD)->toBe(50.0);
});
