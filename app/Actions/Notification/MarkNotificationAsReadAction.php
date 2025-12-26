<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;

class MarkNotificationAsReadAction
{
    /**
     * Execute the action.
     *
     * @param Model $notifiable
     * @param string $notificationId
     * @return bool
     */
    public function execute(Model $notifiable, string $notificationId): bool
    {
        $notification = $notifiable->notifications()->findOrFail($notificationId);
        
        return $notification->markAsRead();
    }
}
