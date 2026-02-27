<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentActionType: string
{
    case STUDENT_ENROLLMENT_NE = 'STUDENT_ENROLLMENT_NE';
    case STUDENT_MAJOR_ENROLLMENT = 'STUDENT_MAJOR_ENROLLMENT';
    case ACADEMIC_DEFER = 'ACADEMIC_DEFER';
    case ACADEMIC_RESUME = 'ACADEMIC_RESUME';
    case ADMISSION_DEFERRAL = 'ADMISSION_DEFERRAL';
    case ACADEMIC_DROPOUT = 'ACADEMIC_DROPOUT';
    case CAMPUS_TRANSFER = 'CAMPUS_TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::STUDENT_ENROLLMENT_NE => 'NE Enrollment',
            self::STUDENT_MAJOR_ENROLLMENT => 'Major Enrollment',
            self::ACADEMIC_DEFER => 'Bảo lưu (Defer)',
            self::ACADEMIC_RESUME => 'Quay lại học (Resume)',
            self::ADMISSION_DEFERRAL => 'Hoãn nhập học (Admission Deferral)',
            self::ACADEMIC_DROPOUT => 'Bỏ học (Dropout)',
            self::CAMPUS_TRANSFER => 'Chuyển campus (Campus Transfer)',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::STUDENT_ENROLLMENT_NE => 'Student Enrollment (NE)',
            self::STUDENT_MAJOR_ENROLLMENT => 'Student Major Enrollment',
            self::ACADEMIC_DEFER => 'Academic Defer',
            self::ACADEMIC_RESUME => 'Academic Resume',
            self::ADMISSION_DEFERRAL => 'Admission Deferral',
            self::ACADEMIC_DROPOUT => 'Academic Dropout',
            self::CAMPUS_TRANSFER => 'Campus Transfer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::STUDENT_ENROLLMENT_NE => 'Student completes NE enrollment and transitions from pending to intake_pre_uni_gc',
            self::STUDENT_MAJOR_ENROLLMENT => 'Student officially enters major study and transitions from intake_pre_uni_gc to intake_course',
            self::ACADEMIC_DEFER => 'Student takes a leave of absence and will return in a future semester',
            self::ACADEMIC_RESUME => 'Student returns from deferral to continue their studies',
            self::ADMISSION_DEFERRAL => 'New student defers their admission or is a no-show',
            self::ACADEMIC_DROPOUT => 'Student permanently leaves the program',
            self::CAMPUS_TRANSFER => 'Student transfers to a different campus',
        };
    }

    /**
     * Get the target status after this action is applied.
     * Returns null if status should not change (e.g., campus transfer).
     */
    public function targetStatus(): ?string
    {
        return match ($this) {
            self::STUDENT_ENROLLMENT_NE => 'intake_pre_uni_gc',
            self::STUDENT_MAJOR_ENROLLMENT => 'intake_course',
            self::ACADEMIC_DEFER => 'deferred',
            self::ACADEMIC_RESUME => 'active',
            self::ADMISSION_DEFERRAL => 'admission_deferred',
            self::ACADEMIC_DROPOUT => 'dropout',
            self::CAMPUS_TRANSFER => null, // Status doesn't change
        };
    }

    /**
     * Get the required fields for this action type.
     *
     * @return array<string>
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::STUDENT_ENROLLMENT_NE => ['from_semester_id'],
            self::STUDENT_MAJOR_ENROLLMENT => ['from_semester_id'],
            self::ACADEMIC_DEFER => ['from_semester_id', 'return_semester_id'],
            self::ACADEMIC_RESUME => ['return_semester_id'],
            self::ADMISSION_DEFERRAL => ['intended_intake_semester_id'],
            self::ACADEMIC_DROPOUT => ['dropout_semester_id'],
            self::CAMPUS_TRANSFER => ['from_campus_id', 'to_campus_id', 'effective_at'],
        };
    }

    /**
     * Check if this action type changes the student status.
     */
    public function changesStatus(): bool
    {
        return $this->targetStatus() !== null;
    }

    /**
     * Check if this action type changes the campus.
     */
    public function changesCampus(): bool
    {
        return $this === self::CAMPUS_TRANSFER;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'labelEn' => $type->labelEn(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }
}
