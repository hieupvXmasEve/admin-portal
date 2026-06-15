<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/integrity_fixtures.php';

it('command reports all invariants passing on a clean dataset', function () {
    $this->artisan('finance:audit-invariants')
        ->expectsOutputToContain('All invariants pass')
        ->assertSuccessful();
});

it('command still detects an over-allocated payment after the registry refactor', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    $this->artisan('finance:audit-invariants')
        ->expectsOutputToContain('INV-1')
        ->expectsOutputToContain('total offending')
        ->assertSuccessful();
});

it('command renders sample ids without error under --sample', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    $this->artisan('finance:audit-invariants', ['--sample' => true])
        ->expectsOutputToContain('INV-1')
        ->assertSuccessful();
});
