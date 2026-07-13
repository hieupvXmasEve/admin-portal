<?php

declare(strict_types=1);

use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;

it('resolves a registered type_key through the default DB provider', function () {
    $registry = new EmailContentRegistry;

    $provider = $registry->resolve('dng_payment_pushed');

    expect($provider)->toBeInstanceOf(DbEmailContentProvider::class);
});

it('returns true for registered type_key', function () {
    $registry = new EmailContentRegistry;

    expect($registry->has('dng_payment_pushed'))->toBeTrue();
});

it('returns false for unregistered type_key', function () {
    $registry = new EmailContentRegistry;

    expect($registry->has('unknown_type_key'))->toBeFalse();
});

it('throws exception when resolving unregistered type_key', function () {
    $registry = new EmailContentRegistry;

    expect(fn () => $registry->resolve('unknown_type_key'))
        ->toThrow(InvalidArgumentException::class);
});
