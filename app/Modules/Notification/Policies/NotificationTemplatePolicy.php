<?php

declare(strict_types=1);

namespace App\Modules\Notification\Policies;

use App\Models\User;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Gates all admin read/write actions on NotificationEmailTemplate via
 * the `notification_email_templates` permission bucket
 * (config/permission.php). Permissions are seeded by
 * UpdatePermissionsSeeder and granted to super_admin by default;
 * admins may grant per-action permissions to other roles at runtime
 * to share viewer / editor / tester responsibilities.
 *
 * Decision anchor: CONTEXT.md D7 — originally super_admin only; the
 * permission split keeps the default behavior identical (super_admin
 * holds all four permissions) but unlocks finer-grained delegation.
 */
class NotificationTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_notification_email_template');
    }

    public function view(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasPermission('view_notification_email_template');
    }

    public function update(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasPermission('edit_notification_email_template');
    }

    /**
     * Create a new template row for the current campus + a missing type_key.
     * Reuses the edit permission so anyone who can author content can also
     * provision a blank row from the admin UI (no separate "create" right
     * since the user explicitly asked for in-app on-demand creation rather
     * than migration-driven provisioning).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('edit_notification_email_template');
    }

    public function preview(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasPermission('preview_notification_email_template');
    }

    public function testSend(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasPermission('test_send_notification_email_template');
    }
}
