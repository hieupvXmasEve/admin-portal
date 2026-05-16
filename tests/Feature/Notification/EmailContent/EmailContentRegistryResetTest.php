<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * B4 — flag-aware composite cache key + reset() behaviour for
 * EmailContentRegistry and its DbEmailContentProvider inner cache.
 *
 * Sub-assertions a/b/c/d from story-pack B4:
 *  a) Two resolve() calls return the same memoised instance.
 *  b) reset() between two resolve() calls yields different instances.
 *  c) reset() also clears the inner DbEmailContentProvider row cache
 *     (verified by query log: exactly 2 SELECTs on
 *     notification_email_templates, one before reset + one after).
 *  d) Toggling notifications.use_db_templates between two resolve() calls
 *     rebuilds with the correct provider class (composite cache key).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['notifications.use_db_templates' => true]);
    app()->forgetInstance(EmailContentRegistry::class);
});

it('memoises resolved providers per type_key + flag state', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $first = $registry->resolve('payment_reminder');
    $second = $registry->resolve('payment_reminder');

    expect($second)->toBe($first);
});

it('clears the resolved cache when reset() is called', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $first = $registry->resolve('payment_reminder');
    $registry->reset();
    $second = $registry->resolve('payment_reminder');

    expect($second)->not->toBe($first);
});

it('clears the inner DbEmailContentProvider row cache on reset', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    // Creating a Campus triggers CampusObserver, which provisions all 4
    // notification_email_templates rows for the new campus including the
    // payment_reminder type_key used below.
    $campus = Campus::factory()->create();

    /** @var DbEmailContentProvider $provider */
    $provider = $registry->resolve('payment_reminder');

    DB::flushQueryLog();
    DB::enableQueryLog();

    // First call: cache miss -> 1 SELECT on notification_email_templates.
    $provider->htmlBody(['campus_id' => $campus->id, 'student_name' => 'A']);

    $registry->reset();

    // After reset(): the inner $cache is empty, so the same call must
    // re-issue a SELECT on the templates table.
    $provider->htmlBody(['campus_id' => $campus->id, 'student_name' => 'A']);

    $selects = array_values(array_filter(
        DB::getQueryLog(),
        fn (array $entry): bool => str_starts_with(
            ltrim(strtolower((string) $entry['query'])),
            'select',
        ) && str_contains((string) $entry['query'], 'notification_email_templates'),
    ));

    DB::disableQueryLog();

    expect($selects)->toHaveCount(2);
});

it('returns a different provider class when the flag is toggled mid-request', function () {
    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    config(['notifications.use_db_templates' => true]);
    $dbProvider = $registry->resolve('payment_reminder');
    expect($dbProvider)->toBeInstanceOf(DbEmailContentProvider::class);

    config(['notifications.use_db_templates' => false]);
    $legacyProvider = $registry->resolve('payment_reminder');
    expect($legacyProvider)->toBeInstanceOf(PaymentReminderEmailContent::class);
});
