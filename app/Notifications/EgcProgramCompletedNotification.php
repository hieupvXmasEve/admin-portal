<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EgcProgramCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $totalLevels,
        public string $newStatus
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $statusLabel = ucwords(str_replace('_', ' ', $this->newStatus));

        return [
            'type' => 'egc_program_completed',
            'category' => NotificationCategory::ACADEMIC->value,
            'title' => '🎓 Congratulations! EGC Program Completed',
            'message' => "You have successfully completed all {$this->totalLevels} levels of the English Global Citizen (EGC) Program! Your status has been updated to '{$statusLabel}'. You can now register for courses in your degree program.",
            'data' => [
                'total_levels' => $this->totalLevels,
                'new_status' => $this->newStatus,
                'status_label' => $statusLabel,
                'icon' => '🎓',
                'action_url' => '/course-offerings',
                'action_text' => 'View Course Offerings',
            ],
            'is_important' => true,
            'channels' => ['database'],
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
