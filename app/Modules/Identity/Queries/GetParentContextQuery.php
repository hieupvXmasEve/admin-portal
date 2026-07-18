<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Models\ParentProfile;
use App\Modules\Identity\Http\Resources\Identity\ParentResource;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;

class GetParentContextQuery
{
    public function __construct(
        private readonly GuardianAccessGrantReader $accessGrantReader,
    ) {}

    public function handle(ParentProfile $parentProfile): array
    {
        $parentProfile->load('user');
        $activeStudentIds = $this->accessGrantReader->activeStudentIdsForUser((int) $parentProfile->user_id);
        $parentProfile->load([
            'students' => fn ($students) => $students->whereKey($activeStudentIds),
            'students.campus',
            'students.program',
        ]);

        // 2. Get Notification Summary (for the parent user)
        $unreadNotifications = $parentProfile->user->notifications()->unread()->count();

        return [
            'user' => (new ParentResource($parentProfile))->resolve(),
            'settings' => $this->getDefaultSettings(),
            'feature_flags' => $this->getFeatureFlags(),
            'permissions' => $this->getPermissions(),
            'notifications' => [
                'unread_count' => $unreadNotifications,
            ],
        ];
    }

    protected function getFeatureFlags(): array
    {
        return [
            'academic_results' => true,
            'attendance_tracking' => true,
            'tuition_fees' => true,
            'notifications' => true,
        ];
    }

    protected function getPermissions(): array
    {
        return [
            'can_view_child_data' => true,
            'can_receive_notifications' => true,
        ];
    }

    protected function getDefaultSettings(): array
    {
        return [
            'ui' => [
                'theme' => 'system',
                'language' => 'vi',
                'compact_mode' => false,
            ],
            'notifications' => [
                'email' => true,
                'push' => false,
            ],
        ];
    }
}
