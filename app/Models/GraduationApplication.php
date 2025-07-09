<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'application_date',
        'intended_graduation_date',
        'status',
        'application_type',
        'ceremony_participation',
        'application_fee_paid',
        'requirements_verified',
        'thesis_submitted',
        'final_transcript_ready',
        'processing_notes',
        'approved_by_user_id',
        'approved_date',
        'rejection_reason',
        'ceremony_date',
        'diploma_mailed_date',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'application_date' => 'date',
        'intended_graduation_date' => 'date',
        'ceremony_participation' => 'boolean',
        'application_fee_paid' => 'boolean',
        'requirements_verified' => 'boolean',
        'thesis_submitted' => 'boolean',
        'final_transcript_ready' => 'boolean',
        'approved_date' => 'date',
        'ceremony_date' => 'date',
        'diploma_mailed_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
