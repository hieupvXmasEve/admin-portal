<?php

namespace App\Models;

use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\FormTarget;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFormAssignment extends Model
{
    use HasFactory;

    protected $table = 'student_form_assignments';

    protected $fillable = [
        'student_id',
        'form_target_id',
        'status', // not_started, completed
        'response_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id')->withTrashed();
    }

    public function formTarget(): BelongsTo
    {
        return $this->belongsTo(FormTarget::class, 'form_target_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'not_started');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
