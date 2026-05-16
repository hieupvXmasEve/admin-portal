<?php

declare(strict_types=1);

namespace App\Modules\Notification\Observers;

use App\Models\Campus;
use App\Modules\Notification\Support\NotificationEmailTemplateProvisioner;

/**
 * Observes Campus model lifecycle events to keep
 * notification_email_templates in sync.
 *
 * On `created`, provisions the 4 default email templates for the new campus
 * so the DbEmailContentProvider never throws "template not found" the first
 * time an admin sends from a freshly-created campus.
 */
class CampusObserver
{
    public function created(Campus $campus): void
    {
        app(NotificationEmailTemplateProvisioner::class)->provisionForCampus($campus->id);
    }
}
