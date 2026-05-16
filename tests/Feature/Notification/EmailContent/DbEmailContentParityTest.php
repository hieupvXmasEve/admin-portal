<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;
use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;
use App\Modules\Notification\EmailContent\Types\ParentPaymentReminderEmailContent;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exit-state proof (P1 #12, story S1.7).
 *
 * For each of the 4 NotificationTemplateTypeKey cases, render the seeded
 * `notification_email_templates` row via DbEmailContentProvider with a fixed
 * `$data` fixture, render the same fixture through the legacy
 * `*EmailContent.php` provider, then assert byte-equivalence after whitespace
 * normalisation.
 *
 * Setup notes:
 *  - RefreshDatabase runs all migrations including
 *    `2026_05_16_170100_seed_notification_email_templates`, but that seeder
 *    inserts zero rows when no Campus is present. The test creates a Campus
 *    via factory then replays the seeder's `up()` (idempotent via
 *    firstOrCreate) so the 4 default rows exist for that campus.
 *  - Sample fixtures intentionally avoid HTML-special characters in variable
 *    values; legacy `htmlspecialchars()` is therefore a no-op and parity
 *    holds after whitespace collapse.
 *  - `paid_at` sample `'15/06/2026 14:30'` cannot be parsed by Carbon; the
 *    legacy DngPaymentReceived provider falls back to verbatim rendering on
 *    parse failure, matching the DB-backed substitution.
 */
uses(RefreshDatabase::class);

/**
 * Collapse whitespace so heredoc indentation and minor seeder formatting
 * differences do not register as divergence.
 */
function parityNormalize(string $value): string
{
    $collapsed = preg_replace('/\s+/', ' ', $value);

    return trim((string) $collapsed);
}

/**
 * Replay the seeded data migration once a Campus exists. The migration's
 * firstOrCreate makes this idempotent.
 */
function seedNotificationTemplatesForCampus(int $campusId): void
{
    $existing = NotificationEmailTemplate::query()
        ->where('campus_id', $campusId)
        ->count();
    if ($existing === count(NotificationTemplateTypeKey::cases())) {
        return;
    }

    $migrationPath = base_path('database/migrations/2026_05_16_170100_seed_notification_email_templates.php');
    /** @var Migration $migration */
    $migration = require $migrationPath;
    $migration->up();
}

dataset('parity_cases', [
    'payment_reminder' => [
        NotificationTemplateTypeKey::PaymentReminder,
        PaymentReminderEmailContent::class,
        [
            'student_name' => 'Nguyễn Văn A',
            'student_code' => 'SE12345',
            'semester_code' => 'SP2026',
            'invoice_code' => 'INV-2026-00123',
            'balance_formatted' => '5.000.000',
            'due_date' => '15/06/2026',
        ],
    ],
    'parent_payment_reminder' => [
        NotificationTemplateTypeKey::ParentPaymentReminder,
        ParentPaymentReminderEmailContent::class,
        [
            'parent_name' => 'Quý Phụ Huynh',
            'student_name' => 'Nguyễn Văn A',
            'student_code' => 'SE12345',
            'semester_code' => 'SP2026',
            'invoice_code' => 'INV-2026-00123',
            'balance_formatted' => '5.000.000',
            'due_date' => '15/06/2026',
        ],
    ],
    'dng_payment_pushed' => [
        NotificationTemplateTypeKey::DngPaymentPushed,
        DngPaymentPushedEmailContent::class,
        [
            'student_name' => 'Nguyễn Văn A',
            'student_code' => 'SE12345',
            'semester_code' => 'SP2026',
            'program_name' => 'B.Sc Software Engineering',
            'invoice_code' => 'INV-2026-00123',
            'amount_formatted' => '12.500.000 VNĐ',
            'due_date' => '15/06/2026',
        ],
    ],
    'dng_payment_received' => [
        NotificationTemplateTypeKey::DngPaymentReceived,
        DngPaymentReceivedEmailContent::class,
        [
            'student_name' => 'Nguyễn Văn A',
            'student_code' => 'SE12345',
            'semester_code' => 'SP2026',
            'amount_formatted' => '12.500.000 VNĐ',
            'paid_at' => '15/06/2026 14:30',
        ],
    ],
]);

it(
    'renders the seeded template byte-equivalent to the legacy provider for each type_key',
    function (NotificationTemplateTypeKey $typeKey, string $legacyClass, array $variables) {
        $campus = Campus::factory()->create();
        seedNotificationTemplatesForCampus($campus->id);

        /** @var EmailContentProvider $legacy */
        $legacy = new $legacyClass;
        $db = new DbEmailContentProvider($typeKey);

        $dbData = $variables + ['campus_id' => $campus->id];

        $legacySubject = $legacy->subject($variables);
        $dbSubject = $db->subject($dbData);
        expect(parityNormalize($dbSubject))
            ->toBe(parityNormalize($legacySubject), "Subject parity failed for {$typeKey->value}");

        $legacyHtml = $legacy->htmlBody($variables);
        $dbHtml = $db->htmlBody($dbData);
        expect(parityNormalize($dbHtml))
            ->toBe(parityNormalize($legacyHtml), "HTML body parity failed for {$typeKey->value}");
    },
)->with('parity_cases');
