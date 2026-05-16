<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Sub-assertions a/b/c/d from story-map S1.5:
 *  a) flag=true  -> resolve() returns DbEmailContentProvider
 *  b) flag=false -> resolve() returns legacy PaymentReminderEmailContent
 *  c) same campus_id twice -> 1 DB query (memoised)
 *  d) different campus_id  -> 2 DB queries (no cross-campus reuse)
 *
 * Invoked by Pest test runner via:
 *  ./scripts/dev.sh test tests/Feature/Notification/EmailContent/DbEmailContentProviderTest.php
 */
uses(RefreshDatabase::class);

function makeNotificationTemplate(int $campusId, string $typeKey = 'payment_reminder'): NotificationEmailTemplate
{
    // updateOrCreate (not create) so we coexist with CampusObserver, which
    // auto-provisions a notification_email_templates row when a Campus is
    // created in the test factory.
    return NotificationEmailTemplate::updateOrCreate(
        [
            'campus_id' => $campusId,
            'type_key' => $typeKey,
        ],
        [
            'subject' => 'Hi {{student_name}}',
            'body_html' => '<p>{{student_name}}</p>',
        ],
    );
}

it('resolves DbEmailContentProvider when use_db_templates flag is true', function () {
    config(['notifications.use_db_templates' => true]);
    app()->forgetInstance(EmailContentRegistry::class);

    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $provider = $registry->resolve('payment_reminder');

    expect($provider)->toBeInstanceOf(DbEmailContentProvider::class);
});

it('resolves legacy PaymentReminderEmailContent when use_db_templates flag is false', function () {
    config(['notifications.use_db_templates' => false]);
    app()->forgetInstance(EmailContentRegistry::class);

    /** @var EmailContentRegistry $registry */
    $registry = app(EmailContentRegistry::class);

    $provider = $registry->resolve('payment_reminder');

    expect($provider)->toBeInstanceOf(PaymentReminderEmailContent::class);
});

it('memoises the template lookup for repeated calls with the same campus_id', function () {
    config(['notifications.use_db_templates' => true]);

    $campus = Campus::factory()->create();
    makeNotificationTemplate($campus->id);

    $provider = new DbEmailContentProvider(NotificationTemplateTypeKey::PaymentReminder);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $first = $provider->htmlBody(['campus_id' => $campus->id, 'student_name' => 'Alice']);
    $second = $provider->htmlBody(['campus_id' => $campus->id, 'student_name' => 'Bob']);

    expect($first)->toBe('<p>Alice</p>');
    expect($second)->toBe('<p>Bob</p>');

    $selects = array_values(array_filter(
        DB::getQueryLog(),
        fn (array $entry): bool => str_starts_with(
            ltrim(strtolower((string) $entry['query'])),
            'select',
        ) && str_contains((string) $entry['query'], 'notification_email_templates'),
    ));

    DB::disableQueryLog();

    expect($selects)->toHaveCount(1);
});

it('issues a separate DB query for each distinct campus_id', function () {
    config(['notifications.use_db_templates' => true]);

    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    makeNotificationTemplate($campusA->id);
    makeNotificationTemplate($campusB->id);

    $provider = new DbEmailContentProvider(NotificationTemplateTypeKey::PaymentReminder);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $provider->htmlBody(['campus_id' => $campusA->id, 'student_name' => 'Alice']);
    $provider->htmlBody(['campus_id' => $campusB->id, 'student_name' => 'Bob']);

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
