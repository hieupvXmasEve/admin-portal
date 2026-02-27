<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'decision_name',
        'decision_number',
        'decision_signer',
        'issued_at',
        'expires_at',
        'upload_record_id',
        'changed_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function uploadRecord(): BelongsTo
    {
        return $this->belongsTo(UploadRecord::class, 'upload_record_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(StudentActionLog::class, 'decision_id');
    }
}
