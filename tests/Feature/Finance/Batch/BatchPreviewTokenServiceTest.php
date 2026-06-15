<?php

declare(strict_types=1);

use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->service = app(BatchPreviewTokenService::class);
    $this->userId = 99;
    $this->lines = [
        new BatchPreviewLine('a', ['x' => 1], []),
        new BatchPreviewLine('b', ['x' => 2], []),
        new BatchPreviewLine('c', ['x' => 3], []),
    ];
});

it('issues a token and verifies an unchanged subset', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1],
        'b' => ['x' => 2],
    ]);

    expect($result['ok'])->toBeTrue()->and($result['changed'])->toBe([]);
});

it('blocks the commit when a selected line drifted', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1],
        'b' => ['x' => 999],
    ]);

    expect($result['ok'])->toBeFalse()->and($result['changed'])->toBe(['b']);
});

it('allows excluding warning lines (subset smaller than issued set still verifies)', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'a' => ['x' => 1],
    ]);

    expect($result['ok'])->toBeTrue();
});

it('reports unknown selected keys as drift, not silent skip', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::ChargeGeneration, [
        'zzz' => ['x' => 1],
    ]);

    expect($result['ok'])->toBeFalse()->and($result['changed'])->toContain('zzz');
});

it('consume deletes the token so a second commit fails (one-time use)', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 1], $this->lines);

    $first = $this->service->consume($this->userId, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);
    $second = $this->service->consume($this->userId, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);

    expect($first['ok'])->toBeTrue()
        ->and($second['ok'])->toBeFalse()
        ->and($second['missing'])->toBeTrue();
});

it('rejects a token issued for a different user', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, [], $this->lines);

    $result = $this->service->verify($this->userId + 1, $token, BatchJobType::ChargeGeneration, ['a' => ['x' => 1]]);

    expect($result['ok'])->toBeFalse()->and($result['missing'])->toBeTrue();
});

it('rejects a token replayed under a different job type', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, [], $this->lines);

    $result = $this->service->verify($this->userId, $token, BatchJobType::DngPush, ['a' => ['x' => 1]]);

    expect($result['ok'])->toBeFalse()->and($result['missing'])->toBeTrue();
});

it('returns the stored scope for re-resolution and null for a bad token', function () {
    $token = $this->service->issue($this->userId, BatchJobType::ChargeGeneration, ['semester_id' => 5, 'fee_category' => 'major'], $this->lines);

    expect($this->service->scope($this->userId, $token, BatchJobType::ChargeGeneration))
        ->toBe(['semester_id' => 5, 'fee_category' => 'major'])
        ->and($this->service->scope($this->userId, 'nope', BatchJobType::ChargeGeneration))->toBeNull();
});