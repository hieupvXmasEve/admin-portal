<?php

declare(strict_types=1);

use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use App\Modules\Notification\Policies\PolicyResolver;

it('denies channels for non-global events when campus is missing under strict isolation', function () {
    $resolver = new PolicyResolver(
        allowedChannels: ['email', 'realtime'],
        strictIsolation: true,
        globalAllowlist: ['system.security'],
    );

    $intent = new NotificationIntent(
        typeKey: 'invoice_paid',
        recipientTargets: [['type' => 'student', 'id' => 1]],
        channels: ['email', 'realtime'],
        data: [],
        templateKeys: [],
    );

    $decision = $resolver->decide($intent, 'finance.invoice_paid', null);

    expect($decision['allow_channels'])->toBe([])
        ->and($decision['deny_channels'])->toBe(['email', 'realtime'])
        ->and($decision['reason'])->toBe('strict_campus_isolation');
});

it('allows global security events when campus is missing', function () {
    $resolver = new PolicyResolver(
        allowedChannels: ['email', 'realtime'],
        strictIsolation: true,
        globalAllowlist: ['system.security'],
    );

    $intent = new NotificationIntent(
        typeKey: 'system_alert',
        recipientTargets: [['type' => 'user', 'id' => 1]],
        channels: ['email', 'realtime'],
        data: [],
        templateKeys: [],
    );

    $decision = $resolver->decide($intent, 'system.security.password_changed', null);

    expect($decision['allow_channels'])->toBe(['email', 'realtime'])
        ->and($decision['deny_channels'])->toBe([])
        ->and($decision['reason'])->toBe('allow_all');
});
