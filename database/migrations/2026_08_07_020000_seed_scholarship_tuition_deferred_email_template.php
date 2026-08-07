<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Support\NotificationEmailTemplateProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: give existing campuses the scholarship-tuition-deferred
 * email template. New campuses get it from the CampusObserver, but campuses
 * created before this type_key existed would otherwise have no row.
 *
 * Delegates to the provisioner, which is idempotent on the unique
 * (campus_id, type_key) key, so a campus that already has the row is untouched.
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
        // Only this type_key: other templates predate this migration and may
        // carry admin edits.
        NotificationEmailTemplate::query()
            ->where('type_key', NotificationTemplateTypeKey::ScholarshipAdjustmentTuitionDeferred->value)
            ->delete();
    }
};
