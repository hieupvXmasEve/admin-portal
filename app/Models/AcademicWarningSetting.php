<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicWarningSetting extends Model
{
    public const DEFAULT_CHANNELS = ['realtime', 'email'];

    protected $fillable = [
        'campus_id',
        'attendance_warning_ratio',
        'channels',
        'academic_warning_title',
        'academic_warning_body',
        'attendance_warning_title',
        'attendance_warning_body',
        'attendance_exceeded_title',
        'attendance_exceeded_body',
        'updated_by_user_id',
    ];

    protected $casts = [
        'attendance_warning_ratio' => 'decimal:2',
        'channels' => 'array',
    ];

    public static function defaults(?int $campusId = null): array
    {
        return [
            'campus_id' => $campusId,
            'attendance_warning_ratio' => 0.50,
            'channels' => self::DEFAULT_CHANNELS,
            'academic_warning_title' => 'Academic standing warning',
            'academic_warning_body' => 'Dear {{student_name}}, your current cumulative GPA is {{cumulative_gpa}}/100, below the required 50. Please contact Academic Support for guidance.',
            'attendance_warning_title' => 'Attendance warning',
            'attendance_warning_body' => 'Dear {{student_name}}, you have missed {{absence_count}}/{{allowed_absences}} allowed sessions for {{course_code}} {{section_code}}. Please attend upcoming classes to remain eligible.',
            'attendance_exceeded_title' => 'Attendance limit exceeded',
            'attendance_exceeded_body' => 'Dear {{student_name}}, you have missed {{absence_count}}/{{allowed_absences}} allowed sessions for {{course_code}} {{section_code}}. This exceeds the attendance limit and may make you ineligible for the final exam.',
        ];
    }

    public static function forCampus(?int $campusId): self
    {
        return self::query()->firstOrCreate(
            ['campus_id' => $campusId],
            self::defaults($campusId),
        );
    }

    /**
     * @return array<int, string>
     */
    public function activeChannels(): array
    {
        $channels = $this->channels ?: self::DEFAULT_CHANNELS;

        return array_values(array_intersect(array_map('strval', $channels), self::DEFAULT_CHANNELS));
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
