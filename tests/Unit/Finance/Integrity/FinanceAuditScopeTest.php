<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceAuditScope;

it('reports empty when no student ids', function () {
    expect((new FinanceAuditScope())->isEmpty())->toBeTrue();
});

it('exposes a comma-separated int list of student ids', function () {
    $scope = new FinanceAuditScope(studentIds: [3, 1, 2, 2]);
    expect($scope->isEmpty())->toBeFalse()
        ->and($scope->studentIdsCsv())->toBe('1,2,3');
});

it('sanitizes non-positive and non-int ids out of the csv', function () {
    $scope = new FinanceAuditScope(studentIds: [5, 0, -1]);
    expect($scope->studentIdsCsv())->toBe('5');
});
