<?php

namespace App\Actions\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Support\Facades\DB;

class SendManualNotificationAction
{
    /**
     * Execute the manual notification sending.
     *
     * @param array $data
     * @return void
     */
    public function execute(array $data): void
    {
        DB::transaction(function () use ($data) {
            $notifiableIds = $data['notifiable_ids'];
            $notifiableType = $data['notifiable_type'];
            $title = $data['title'];
            $message = $data['message'];
            $category = $data['category'] ?? NotificationCategory::SYSTEM->value;
            $type = $data['type'] ?? 'manual_notification';
            $isImportant = $data['is_important'] ?? false;
            $actionUrl = $data['action_url'] ?? null;
            $actionText = $data['action_text'] ?? null;

            $modelClass = match ($notifiableType) {
                'student' => \App\Models\Student::class,
                'user' => \App\Models\User::class,
                'lecturer' => \App\Models\Lecture::class,
                default => throw new \InvalidArgumentException("Invalid notifiable type: {$notifiableType}"),
            };

            foreach ($notifiableIds as $id) {
                $notifiable = $modelClass::find($id);
                if ($notifiable) {
                    $notifiable->notifications()->create([
                        'type' => $type,
                        'category' => $category,
                        'title' => $title,
                        'message' => $message,
                        'data' => [
                            'action_url' => $actionUrl,
                            'action_text' => $actionText,
                        ],
                        'channels' => ['database', 'broadcast'],
                        'is_important' => $isImportant,
                        'expires_at' => now()->addDays(30),
                    ]);
                }
            }
        });
    }
}
