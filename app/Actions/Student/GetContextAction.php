<?php

namespace App\Actions\Student;

use App\Actions\Form\CheckPortalGateAction;
use App\Models\Student;
use App\Models\StudentSetting;

class GetContextAction
{
    public function __construct(
        protected CheckPortalGateAction $checkPortalGateAction
    ) {}

    public function execute(Student $student): array
    {
        // 1. Check Gate
        $gateStatus = $this->checkPortalGateAction->execute($student);

        // 2. Get Settings
        $settings = $student->settings?->settings ?? $this->getDefaultSettings();

        // 3. Mock Feature Flags (replace with actual service later)
        $featureFlags = [
            'room_booking' => true,
            'wallet' => true,
            'clubs' => false,
            'forms' => true,
            'query_system' => true,
        ];

        // 4. Mock Permissions (lite)
        $permissions = [
            'can_book_room' => true,
            'can_join_club' => true,
            'can_submit_forms' => true,
        ];

        return [
            'student_id' => $student->id,
            'survey_gate' => [
                'blocked' => $gateStatus['blocked'],
                // Use the resource to get the full formatted data, resolving to array for flat structure
                'mandatory_assignments' => $gateStatus['mandatory_assignments']->map(function ($assignment) {
                    return (new \App\Http\Resources\StudentFormAssignmentResource($assignment))->resolve();
                })->toArray(),
            ],
            'settings' => $settings,
            'feature_flags' => $featureFlags,
            'permissions' => $permissions,
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
