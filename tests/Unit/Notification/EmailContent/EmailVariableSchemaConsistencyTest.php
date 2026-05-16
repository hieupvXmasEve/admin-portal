<?php

declare(strict_types=1);

use App\Modules\Notification\EmailContent\Contracts\EmailVariableSchema;
use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;
use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;
use App\Modules\Notification\EmailContent\Types\ParentPaymentReminderEmailContent;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;

dataset('email_variable_schemas', [
    'payment_reminder' => [
        PaymentReminderEmailContent::class,
        [
            'student_name',
            'student_code',
            'semester_code',
            'invoice_code',
            'balance_formatted',
            'due_date',
        ],
    ],
    'parent_payment_reminder' => [
        ParentPaymentReminderEmailContent::class,
        [
            'parent_name',
            'student_name',
            'student_code',
            'semester_code',
            'invoice_code',
            'balance_formatted',
            'due_date',
        ],
    ],
    'dng_payment_pushed' => [
        DngPaymentPushedEmailContent::class,
        [
            'student_name',
            'student_code',
            'semester_code',
            'program_name',
            'invoice_code',
            'amount_formatted',
            'due_date',
        ],
    ],
    'dng_payment_received' => [
        DngPaymentReceivedEmailContent::class,
        [
            'student_name',
            'student_code',
            'semester_code',
            'amount_formatted',
            'paid_at',
        ],
    ],
]);

it('implements EmailVariableSchema and exposes the documented allow-list', function (string $className, array $expectedKeys) {
    $instance = new $className;

    expect($instance)->toBeInstanceOf(EmailVariableSchema::class);

    $variables = $instance->availableVariables();

    expect(array_keys($variables))
        ->toEqualCanonicalizing($expectedKeys);

    foreach ($variables as $key => $entry) {
        expect($entry)
            ->toBeArray()
            ->toHaveKeys(['label', 'sample'], "Variable {$key} must declare both label and sample");

        expect($entry['label'])
            ->toBeString()
            ->not->toBe('', "Variable {$key} label must be a non-empty string");

        expect($entry['sample'])
            ->not->toBeNull("Variable {$key} sample must not be null");
    }
})->with('email_variable_schemas');
