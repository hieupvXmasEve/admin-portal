<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use Illuminate\Database\Eloquent\Model;

class MarkAllNotificationsAsReadAction
{
    /**
     * Execute the action.
     *
     * @param Model $notifiable
     * @return int
     */
    public function execute(Model $notifiable): int
    {
        return $notifiable->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
