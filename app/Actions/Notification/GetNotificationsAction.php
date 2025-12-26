<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class GetNotificationsAction
{
    /**
     * Execute the action.
     *
     * @param Model $notifiable
     * @param int $limit
     * @return LengthAwarePaginator
     */
    public function execute(Model $notifiable, int $limit = 20): LengthAwarePaginator
    {
        return $notifiable->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }
}
