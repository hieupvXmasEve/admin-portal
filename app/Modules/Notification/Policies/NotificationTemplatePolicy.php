<?php

declare(strict_types=1);

namespace App\Modules\Notification\Policies;

use App\Models\User;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Gates all admin read/write actions on NotificationEmailTemplate to
 * users with the `super_admin` system role.
 *
 * Decision anchor: CONTEXT.md D7 — Super Admin only; no campus scoping
 * (Super Admin edits templates for ANY campus).
 */
class NotificationTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasSystemRole('super_admin');
    }

    public function view(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasSystemRole('super_admin');
    }

    public function update(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasSystemRole('super_admin');
    }

    public function preview(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasSystemRole('super_admin');
    }

    public function testSend(User $user, NotificationEmailTemplate $template): bool
    {
        return $user->hasSystemRole('super_admin');
    }
}
