<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceAuditScope;

it('reports empty when no student ids and no semester', function () {
    expect((new FinanceAuditScope)->isEmpty())->toBeTrue();
});

it('is not empty when only a semester is set', function () {
    expect((new FinanceAuditScope(semesterId: 12))->isEmpty())->toBeFalse();
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
