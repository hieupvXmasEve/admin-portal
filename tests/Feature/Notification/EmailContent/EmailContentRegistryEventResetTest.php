<?php

declare(strict_types=1);

use App\Modules\Notification\EmailContent\EmailContentRegistry;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\Events\JobProcessed;

/**
 * B4 — verifies the NotificationServiceProvider event listeners
 * (`RequestHandled` and `JobProcessed`) reset the EmailContentRegistry's
 * memoised provider cache at request / queue-job boundaries, protecting
 * long-running runtimes (FrankenPHP, Octane, queue workers) from leaking
 * cached state across requests/jobs.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['notifications.use_db_templates' => true]);
    app()->forgetInstance(EmailContentRegistry::class);
});

it('resets the registry when RequestHandled fires', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $first = $registry->resolve('payment_reminder');

    event(new RequestHandled(Request::create('/'), new Response));

    $second = $registry->resolve('payment_reminder');

    expect($second)->not->toBe($first);
});

it('resets the registry when JobProcessed fires', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $first = $registry->resolve('payment_reminder');

    // shouldIgnoreMissing() so listeners like Telescope's JobWatcher (which
    // calls $event->job->payload()) do not throw on the mock.
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    event(new JobProcessed('sync', $job));

    $second = $registry->resolve('payment_reminder');

    expect($second)->not->toBe($first);
});
