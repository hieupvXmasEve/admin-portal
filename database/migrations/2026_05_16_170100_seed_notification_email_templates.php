<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Support\NotificationEmailTemplateProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: seed notification_email_templates with one row per
 * (campus_id, type_key). Subject + HTML body defaults now live in
 * App\Modules\Notification\Support\NotificationEmailTemplateProvisioner so
 * post-deploy campus creation (via CampusObserver) can reuse them. This
 * migration just walks existing campuses and delegates to the provisioner.
 *
 * Idempotent via firstOrCreate on the unique (campus_id, type_key) key.
 * See docs/features/email/template-contract.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(NotificationEmailTemplateProvisioner::class);

        Campus::query()->orderBy('id')->each(function (Campus $campus) use ($provisioner): void {
            $provisioner->provisionForCampus($campus->id);
        });
    }

    public function down(): void
    {
        NotificationEmailTemplate::query()
            ->whereIn(
                'type_key',
                array_map(static fn (NotificationTemplateTypeKey $c): string => $c->value, NotificationTemplateTypeKey::cases()),
            )
            ->delete();
    }
};
