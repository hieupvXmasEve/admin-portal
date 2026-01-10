<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentActionAttachment extends Model
{
    protected $fillable = [
        'student_action_log_id',
        'upload_record_id',
    ];

    public function actionLog(): BelongsTo
    {
        return $this->belongsTo(StudentActionLog::class, 'student_action_log_id');
    }

    public function uploadRecord(): BelongsTo
    {
        return $this->belongsTo(UploadRecord::class, 'upload_record_id');
    }
}
