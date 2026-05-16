<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;

it('substitutes mustache placeholders in subject and body', function () {
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => NotificationTemplateTypeKey::PaymentReminder,
        'subject' => 'Hello {{student_name}} - {{semester_code}}',
        'body_html' => '<p>Dear {{student_name}}, your balance is {{balance_formatted}} VND.</p>',
    ]);

    $rendered = $template->render([
        'student_name' => 'Nguyen Van A',
        'semester_code' => 'SP2026',
        'balance_formatted' => '5.000.000',
    ]);

    expect($rendered['subject'])->toBe('Hello Nguyen Van A - SP2026');
    expect($rendered['html'])->toBe('<p>Dear Nguyen Van A, your balance is 5.000.000 VND.</p>');
    expect($rendered['text'])->toBeNull();
});

it('leaves unknown placeholders untouched', function () {
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => NotificationTemplateTypeKey::PaymentReminder,
        'subject' => 'Hi {{known}} and {{unknown}}',
        'body_html' => '<p>{{unknown}}</p>',
    ]);

    $rendered = $template->render(['known' => 'A']);

    expect($rendered['subject'])->toBe('Hi A and {{unknown}}');
    expect($rendered['html'])->toBe('<p>{{unknown}}</p>');
});

it('casts type_key to NotificationTemplateTypeKey enum', function () {
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => 'payment_reminder',
        'subject' => 'x',
        'body_html' => 'y',
    ]);

    expect($template->type_key)->toBe(NotificationTemplateTypeKey::PaymentReminder);
});

it('coerces non-string variable values via casting', function () {
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => NotificationTemplateTypeKey::DngPaymentReceived,
        'subject' => 'amount={{amount}}',
        'body_html' => 'paid={{paid}}',
    ]);

    $rendered = $template->render([
        'amount' => 1234567,
        'paid' => true,
    ]);

    expect($rendered['subject'])->toBe('amount=1234567');
    expect($rendered['html'])->toBe('paid=1');
});

it('html-escapes substituted values in body_html so unsafe chars cannot corrupt markup', function () {
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => NotificationTemplateTypeKey::PaymentReminder,
        'subject' => 'Hello {{student_name}}',
        'body_html' => '<p>Dear {{student_name}}, invoice {{invoice_code}}</p>',
    ]);

    $rendered = $template->render([
        'student_name' => "O'Brien & <lab>",
        'invoice_code' => 'INV-"2026"-001',
    ]);

    // Subject is RAW (legacy parity — legacy subject() never escaped).
    expect($rendered['subject'])->toBe("Hello O'Brien & <lab>");

    // HTML body must escape every interpolated value so injected markup
    // cannot reach the recipient mail client.
    expect($rendered['html'])->toBe(
        '<p>Dear O&#039;Brien &amp; &lt;lab&gt;, invoice INV-&quot;2026&quot;-001</p>'
    );
});

it('does NOT escape literal markup that lives in the authored template itself', function () {
    // The seeded HTML is server-authored; only substituted values are escaped.
    $template = new NotificationEmailTemplate([
        'campus_id' => 1,
        'type_key' => NotificationTemplateTypeKey::PaymentReminder,
        'subject' => 'x',
        'body_html' => '<a href="http://portal.example.com/login">Login</a> for {{student_name}}',
    ]);

    $rendered = $template->render(['student_name' => 'A']);

    // The authored <a href="..."> tag MUST survive verbatim.
    expect($rendered['html'])->toBe(
        '<a href="http://portal.example.com/login">Login</a> for A'
    );
});
