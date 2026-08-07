<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Http\Resources\Student\StudentResource;
use App\Models\Student;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Engagement\FormPortalGateReader;
use App\Shared\Contracts\StudentRegistry\StudentPortalContextReader;

final class EloquentStudentPortalContextReader implements StudentPortalContextReader
{
    public function __construct(
        private readonly FormPortalGateReader $formPortalGateReader,
        private readonly ProgramEnrollmentReader $programEnrollments,
    ) {}

    public function forStudent(int $studentId): array
    {
        $student = Student::query()->findOrFail($studentId);
        $gateStatus = $this->formPortalGateReader->forStudentId($studentId);
        $settings = $student->settings?->settings ?? $this->defaultSettings();
        $activeHoldsCount = $student->academicHolds()->where('status', 'active')->count();
        $lifecycleStatus = $this->programEnrollments
            ->forStudentId($studentId)
            ->legacyCompatibleStatus();

        return [
            'user' => (new StudentResource($student->load(['campus', 'program'])))->resolve(),
            'survey_gate' => [
                'blocked' => $gateStatus->blocked,
                'mandatory_assignments' => array_map(
                    static fn ($assignment): array => $assignment->toArray(),
                    $gateStatus->mandatoryAssignments,
                ),
            ],
            'settings' => $settings,
            'feature_flags' => [
                'room_booking' => true,
                'wallet' => true,
                'clubs' => true,
                'forms' => true,
                'query_system' => true,
                'academic_report' => true,
            ],
            'permissions' => [
                'can_book_room' => true,
                'can_join_club' => true,
                'can_submit_forms' => true,
                'can_register_courses' => ! in_array($lifecycleStatus, Student::BLOCKED_STATUSES, true)
                    && ! $student->hasActiveHolds(),
            ],
            // Legacy unread-count read removed; key kept at 0 so older FE builds destructuring it don't crash.
            'notifications' => [
                'unread_count' => 0,
            ],
            'academic' => [
                'active_holds_count' => $activeHoldsCount,
                'is_blocked' => $student->hasActiveHolds(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function defaultSettings(): array
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
