<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies active collection scope using a join instead of nested whereHas', function () {
    $query = DngPaymentRequest::query();

    LifecycleDueItemPredicate::applyActiveCollectionScope($query, 123);

    $sql = strtolower($query->toSql());
    $normalizedSql = str_replace(['`', '"'], '', $sql);

    expect($sql)->toContain('join')
        ->and($sql)->not->toContain('exists')
        ->and($normalizedSql)->toContain('students.campus_id');
});
