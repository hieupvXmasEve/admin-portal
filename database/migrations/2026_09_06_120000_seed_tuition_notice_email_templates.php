<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Support\NotificationEmailTemplateProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: give existing campuses the tuition-notice email templates.
 * New campuses get them from CampusObserver. See docs/features/email/template-contract.md.
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
            ->whereIn('type_key', [
                NotificationTemplateTypeKey::TuitionNotice->value,
                NotificationTemplateTypeKey::ParentTuitionNotice->value,
            ])
            ->delete();
    }
};
