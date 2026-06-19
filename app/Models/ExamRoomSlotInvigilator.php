<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invigilator (lecturer) assigned to a shared {@see ExamRoomSlot} block
 * (ACAD-RET-001 slice 5). Invigilation attaches to the room slot, not a single
 * session, because one slot may host several unit-scoped sessions at once.
 */
class ExamRoomSlotInvigilator extends Model
{
    public const ROLE_LEAD = 'lead';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_BACKUP = 'backup';

    protected $fillable = [
        'exam_room_slot_id',
        'lecture_id',
        'role',
        'assigned_by_user_id',
        'notes',
    ];

    public function roomSlot(): BelongsTo
    {
        return $this->belongsTo(ExamRoomSlot::class, 'exam_room_slot_id');
    }

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}
