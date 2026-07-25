<?php

declare(strict_types=1);

namespace App\Enums;

enum AcademicProgressionEventType: string
{
    case PLACEMENT_INITIALIZED = 'PLACEMENT_INITIALIZED';
    case ENGLISH_LEVEL_CHANGED = 'ENGLISH_LEVEL_CHANGED';
    case COURSE_STAGE_CHANGED = 'COURSE_STAGE_CHANGED';
    case IELTS_RECORDED = 'IELTS_RECORDED';

    public function label(): string
    {
        return match ($this) {
            self::PLACEMENT_INITIALIZED => 'Xếp lớp ban đầu (Placement Initialized)',
            self::ENGLISH_LEVEL_CHANGED => 'Thay đổi level tiếng Anh (English Level Changed)',
            self::COURSE_STAGE_CHANGED => 'Chuyển giai đoạn học (Course Stage Changed)',
            self::IELTS_RECORDED => 'Ghi nhận IELTS (IELTS Recorded)',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PLACEMENT_INITIALIZED => 'Placement Initialized',
            self::ENGLISH_LEVEL_CHANGED => 'English Level Changed',
            self::COURSE_STAGE_CHANGED => 'Course Stage Changed',
            self::IELTS_RECORDED => 'IELTS Recorded',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PLACEMENT_INITIALIZED => 'Initial placement when student starts (stage + level if applicable)',
            self::ENGLISH_LEVEL_CHANGED => 'English level increased (0→5) while in pre_uni stage',
            self::COURSE_STAGE_CHANGED => 'Course stage changed from intake_pre_uni_gc to intake_course',
            self::IELTS_RECORDED => 'IELTS certificate recorded (score + file scan)',
        };
    }

    /**
     * Whether a progression transition of this type needs an authorizing
     * Decision (ADR-0048, revised).
     *
     * No EGC progression event requires a Decision. The requirement for the
     * enrolment transitions lives on the status actions instead — NE enrolment
     * (→ `intake_pre_uni_gc`) and major enrolment (→ `intake_course`) carry it
     * (see {@see StudentActionType::requiresDecision()}). Placement
     * Initialized and Course Stage Changed are a secondary record of those same
     * transitions, so flagging them too would double-count; English-level changes
     * and IELTS records never needed one. See the revision note in ADR-0048.
     */
    public function requiresDecision(): bool
    {
        return false;
    }

    /**
     * String values of the event types that require an authorizing Decision.
     *
     * @return array<int, string>
     */
    public static function requiresDecisionValues(): array
    {
        return array_values(array_map(
            fn (self $type): string => $type->value,
            array_filter(self::cases(), fn (self $type): bool => $type->requiresDecision())
        ));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'labelEn' => $type->labelEn(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }
}
