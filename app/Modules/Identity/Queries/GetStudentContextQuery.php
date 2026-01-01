<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Models\Student;
use App\Actions\Form\CheckPortalGateAction;
use App\Http\Resources\Student\StudentResource;
use App\Http\Resources\StudentFormAssignmentResource;

class GetStudentContextQuery
{
    public function __construct(
        protected CheckPortalGateAction $checkPortalGateAction
    ) {}

    public function handle(Student $student): array
    {
        // 1. Check Gate
        $gateStatus = $this->checkPortalGateAction->execute($student);

        // 2. Get Settings
        $settings = $student->settings?->settings ?? $this->getDefaultSettings();

        // 3. Get Notification Summary
        $unreadNotifications = $student->notifications()->unread()->count();

        // 4. Get Academic Standing / Holds
        $activeHoldsCount = $student->academicHolds()->where('status', 'active')->count();

        // 5. Mock Feature Flags (These could be moved to a service or config later)
        $featureFlags = [
            'room_booking' => true,
            'wallet' => true,
            'clubs' => true,
            'forms' => true,
            'query_system' => true,
            'academic_report' => true,
        ];

        // 6. Permissions (Simplified for frontend UI logic)
        $permissions = [
            'can_book_room' => true,
            'can_join_club' => true,
            'can_submit_forms' => true,
            'can_register_courses' => $student->canRegisterForCourses(),
        ];

        return [
            'user' => (new StudentResource($student->load(['campus', 'program'])))->resolve(),
            'survey_gate' => [
                'blocked' => $gateStatus['blocked'],
                'mandatory_assignments' => $gateStatus['mandatory_assignments']->map(function ($assignment) {
                    return (new StudentFormAssignmentResource($assignment))->resolve();
                })->toArray(),
            ],
            'settings' => $settings,
            'feature_flags' => $featureFlags,
            'permissions' => $permissions,
            'notifications' => [
                'unread_count' => $unreadNotifications,
            ],
            'academic' => [
                'active_holds_count' => $activeHoldsCount,
                'is_blocked' => $student->hasActiveHolds(),
            ],
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
