<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One unit/course exam held inside an {@see ExamRoomSlot} (ACAD-RET-001 slice 5).
 *
 * Always scoped to exactly one unit. Approved {@see ExamResitAttempt} rows are
 * assigned to a session for the student to sit; completion then writes the resit
 * result back to the final academic record.
 */
class ExamResitSession extends AuditableModel
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'exam_room_slot_id',
        'unit_id',
        'semester_id',
        'campus_id',
        'syllabus_template_id',
        'status',
        'expected_candidates',
        'actual_candidates',
        'instructions',
        'materials_allowed',
        'scheduled_by_user_id',
        'completed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'expected_candidates' => 'integer',
        'actual_candidates' => 'integer',
        'materials_allowed' => 'array',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function roomSlot(): BelongsTo
    {
        return $this->belongsTo(ExamRoomSlot::class, 'exam_room_slot_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function syllabusTemplate(): BelongsTo
    {
        return $this->belongsTo(SyllabusTemplate::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamResitAttempt::class);
    }

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    protected function getIdentifierForLog(): string
    {
        return "ExamResitSession #{$this->getKey()}";
    }
}
