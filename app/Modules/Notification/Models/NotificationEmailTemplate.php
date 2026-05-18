<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Models\AuditableModel;
use App\Models\Campus;
use App\Models\User;
use App\Modules\Notification\Concerns\HasTemplateRendering;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-campus, per-type_key email body + subject backing the dynamic
 * notification template feature. One row per (campus_id, type_key) by DB
 * constraint; the admin UI in P2 edits this row in place (no delete, no
 * versioning - see CONTEXT.md D4/D5).
 */
class NotificationEmailTemplate extends AuditableModel
{
    use HasTemplateRendering;

    protected $table = 'notification_email_templates';

    /** @var list<string> */
    protected $fillable = [
        'campus_id',
        'type_key',
        'subject',
        'body_html',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type_key' => NotificationTemplateTypeKey::class,
        ];
    }

    /**
     * Pin per-row identity (campus_id, type_key) as immutable AT THE MODEL LAYER.
     *
     * These two columns are mass-assignable on CREATE (the provisioner, the seed
     * migration, test fixtures, and the transient render path all rely on it),
     * but must never change on an existing row. Allowing them to be reassigned
     * would let a future FormRequest rule addition silently cross-tenant write
     * or flip a template's type.
     *
     * The guard runs on `updating` (not `saving`) so initial CREATE goes through
     * untouched.
     */
    protected static function booted(): void
    {
        static::updating(function (NotificationEmailTemplate $template): void {
            if ($template->isDirty('campus_id') || $template->isDirty('type_key')) {
                throw new \LogicException(
                    'NotificationEmailTemplate.campus_id and type_key are immutable after creation.',
                );
            }
        });
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Render the template against the provided variable map.
     *
     * Unknown `{{var}}` placeholders remain in the output verbatim so the
     * caller can detect them; the FormRequest in P2 rejects unknown vars at
     * save-time per CONTEXT.md D8.
     *
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, html: string, text: null}
     */
    public function render(array $variables = []): array
    {
        return [
            'subject' => $this->renderContent((string) $this->subject, $variables),
            // HTML body escapes variable values so a value like "O'Brien" or
            // "<lab>" cannot corrupt markup. Mirrors DbEmailContentProvider::htmlBody().
            'html' => $this->renderContentEscaped((string) $this->body_html, $variables),
            'text' => null,
        ];
    }

    /**
     * Custom activity-log description (mirrors EmailTemplate convention).
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $typeKey = $this->type_key instanceof NotificationTemplateTypeKey
            ? $this->type_key->value
            : (string) $this->type_key;

        return match ($eventName) {
            'created' => "Created notification email template: {$typeKey} (campus {$this->campus_id})",
            'updated' => "Updated notification email template: {$typeKey} (campus {$this->campus_id})",
            'deleted' => "Deleted notification email template: {$typeKey} (campus {$this->campus_id})",
            default => "{$eventName} notification email template: {$typeKey} (campus {$this->campus_id})",
        };
    }
}
