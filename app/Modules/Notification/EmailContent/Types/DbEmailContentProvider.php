<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Types;

use App\Modules\Notification\Concerns\HasTemplateRendering;
use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use InvalidArgumentException;
use RuntimeException;

/**
 * DB-backed provider for one EmailContentRegistry type_key. Reads
 * `$data['campus_id']` per call to look up the matching
 * NotificationEmailTemplate row, rendering subject + html with mustache
 * substitution.
 *
 * Callers:
 *  - App\Modules\Notification\EmailContent\EmailContentRegistry::resolve()
 *    instantiates one instance per type_key when the
 *    `notifications.use_db_templates` flag is true.
 *  - Indirectly consumed by App\Modules\Notification\Actions\HandleOutboxEventAction
 *    (buildEmailData) and the 4 finance Send*RemindersAction classes.
 *
 * One instance is bound per type_key; the request-lifetime memo avoids N+1
 * when finance Actions iterate invoices within the same campus.
 */
final class DbEmailContentProvider implements EmailContentProvider
{
    use HasTemplateRendering;

    /** @var array<string, NotificationEmailTemplate> */
    private array $cache = [];

    public function __construct(
        private readonly NotificationTemplateTypeKey $typeKey,
    ) {}

    public function subject(array $data): string
    {
        return $this->renderContent($this->resolveTemplate($data)->subject, $data);
    }

    public function htmlBody(array $data): string
    {
        // Escape variable values before substitution; mirrors the legacy
        // *EmailContent::htmlBody() htmlspecialchars wrap so a student name
        // like "O'Brien" or "<lab>" cannot corrupt the HTML or inject markup.
        return $this->renderContentEscaped($this->resolveTemplate($data)->body_html, $data);
    }

    public function textBody(array $data): ?string
    {
        return null;
    }

    /**
     * Clear the per-(typeKey, campusId) row cache. Invoked by
     * {@see EmailContentRegistry::reset()} via duck-typed `method_exists` so
     * cached NotificationEmailTemplate rows do not survive across HTTP
     * requests or queued-job boundaries under long-running PHP runtimes
     * (FrankenPHP / Octane).
     */
    public function reset(): void
    {
        $this->cache = [];
    }

    private function resolveTemplate(array $data): NotificationEmailTemplate
    {
        $campusId = $data['campus_id'] ?? null;
        if (! is_int($campusId) && ! (is_string($campusId) && ctype_digit($campusId))) {
            throw new InvalidArgumentException(
                sprintf(
                    'DbEmailContentProvider for %s requires integer $data[\'campus_id\']; got %s.',
                    $this->typeKey->value,
                    var_export($campusId, true),
                ),
            );
        }
        $campusId = (int) $campusId;

        $cacheKey = $this->typeKey->value.':'.$campusId;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $template = NotificationEmailTemplate::query()
            ->where('campus_id', $campusId)
            ->where('type_key', $this->typeKey->value)
            ->first();

        if ($template === null) {
            throw new RuntimeException(
                sprintf(
                    'No notification_email_templates row for type_key=%s, campus_id=%d.',
                    $this->typeKey->value,
                    $campusId,
                ),
            );
        }

        return $this->cache[$cacheKey] = $template;
    }
}
