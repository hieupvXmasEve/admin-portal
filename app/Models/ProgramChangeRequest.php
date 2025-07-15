<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramChangeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'from_program_id',
        'to_program_id',
        'from_specialization_id',
        'to_specialization_id',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'affected_credits',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'affected_credits' => 'array',
    ];

    public static function validationRules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'from_program_id' => ['required', 'exists:programs,id'],
            'to_program_id' => ['required', 'exists:programs,id', 'different:from_program_id'],
            'from_specialization_id' => ['nullable', 'exists:specializations,id'],
            'to_specialization_id' => ['nullable', 'exists:specializations,id'],
            'reason' => ['required', 'string', 'min:10'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
            'approval_notes' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'from_program_id.required' => 'Current program is required',
            'to_program_id.required' => 'Target program is required',
            'to_program_id.different' => 'Target program must be different from current program',
            'reason.required' => 'Reason for change is required',
            'reason.min' => 'Reason must be at least 10 characters',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'from_program_id');
    }

    public function toProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'to_program_id');
    }

    public function fromSpecialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'from_specialization_id');
    }

    public function toSpecialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'to_specialization_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }
}
