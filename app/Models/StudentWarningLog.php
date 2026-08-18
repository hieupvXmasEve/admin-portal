<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWarningLog extends Model
{
    public const TYPE_ACADEMIC_STANDING = 'academic_standing_warning';

    public const TYPE_ATTENDANCE_EARLY = 'attendance_early_warning';

    public const TYPE_ATTENDANCE_EXCEEDED = 'attendance_limit_exceeded';

    protected $fillable = [
        'dedupe_key',
        'warning_type',
        'campus_id',
        'student_id',
        'course_offering_id',
        'class_session_id',
        'gpa_calculation_id',
        'absence_count',
        'warning_absences',
        'allowed_absences',
        'total_sessions',
        'threshold_snapshot',
        'channels',
        'message_title',
        'message_body',
        'notification_event_id',
        'status',
        'actor_user_id',
        'sent_at',
    ];

    protected $casts = [
        'threshold_snapshot' => 'array',
        'channels' => 'array',
        'sent_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function gpaCalculation(): BelongsTo
    {
        return $this->belongsTo(GpaCalculation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
