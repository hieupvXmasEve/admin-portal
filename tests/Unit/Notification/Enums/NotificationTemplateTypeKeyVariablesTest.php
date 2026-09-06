<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationTemplateTypeKey;

dataset('notification_template_type_keys', [
    'payment_reminder' => [
        NotificationTemplateTypeKey::PaymentReminder,
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
        NotificationTemplateTypeKey::ParentPaymentReminder,
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
        NotificationTemplateTypeKey::DngPaymentPushed,
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
        NotificationTemplateTypeKey::DngPaymentReceived,
        [
            'student_name',
            'student_code',
            'semester_code',
            'amount_formatted',
            'paid_at',
        ],
    ],
    'tuition_notice' => [
        NotificationTemplateTypeKey::TuitionNotice,
        [
            'student_code',
            'student_name',
            'semester_name',
            'due_date',
            'total_due',
            'items_summary',
            'payment_instruction',
        ],
    ],
    'parent_tuition_notice' => [
        NotificationTemplateTypeKey::ParentTuitionNotice,
        [
            'student_code',
            'student_name',
            'semester_name',
            'due_date',
            'total_due',
            'items_summary',
            'payment_instruction',
        ],
    ],
]);

it('exposes the documented variable allow-list per case', function (NotificationTemplateTypeKey $case, array $expectedKeys) {
    $variables = $case->availableVariables();

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
})->with('notification_template_type_keys');

it('keeps the PaymentReminder student_name sample stable for S1.3 parity', function () {
    $variables = NotificationTemplateTypeKey::PaymentReminder->availableVariables();

    expect($variables)->toHaveKey('student_name');
    expect($variables['student_name']['sample'])->toBe('Nguyễn Văn A');
});
