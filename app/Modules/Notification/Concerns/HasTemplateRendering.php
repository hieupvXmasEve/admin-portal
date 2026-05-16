<?php

declare(strict_types=1);

namespace App\Modules\Notification\Concerns;

/**
 * Provides simple `{{var}}` substitution for template-bearing models.
 *
 * Shared by:
 * - App\Modules\Notification\Models\NotificationEmailTemplate (new in P1)
 * - App\Models\EmailTemplate (existing, refactored in S1.2 to consume this trait)
 *
 * Substitution is intentionally identical to the legacy heredoc interpolation
 * used by app/Modules/Notification/EmailContent/Types/*EmailContent.php so that
 * the parity test (S1.7) can rely on byte-equivalence after whitespace
 * normalisation.
 */
trait HasTemplateRendering
{
    /**
     * Replace every `{{key}}` placeholder in $content with the matching value
     * from $variables. Unknown placeholders are left untouched so the caller
     * can decide whether to treat them as errors.
     *
     * Values are substituted RAW. Suitable for plain-text targets (email
     * subjects, log messages). For HTML targets, prefer
     * {@see self::renderContentEscaped()} so attacker-controlled `$value`s
     * cannot inject markup.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function renderContent(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $content = str_replace($placeholder, (string) $value, $content);
        }

        return $content;
    }

    /**
     * Like {@see self::renderContent()} but each substituted value is escaped
     * via `htmlspecialchars(... ENT_QUOTES, 'UTF-8')` BEFORE substitution.
     *
     * Use this for HTML body rendering so a student name like `O'Brien` or a
     * code containing `<lab>` cannot corrupt the HTML or inject markup. Mirrors
     * the protection provided by the legacy
     * App\Modules\Notification\EmailContent\Types\*EmailContent::htmlBody()
     * implementations.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function renderContentEscaped(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $content = str_replace($placeholder, $escaped, $content);
        }

        return $content;
    }
}
