<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicHold extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'hold_type',
        'hold_category',
        'title',
        'description',
        'amount',
        'priority',
        'status',
        'placed_date',
        'due_date',
        'resolved_date',
        'placed_by_user_id',
        'resolved_by_user_id',
        'resolution_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_date' => 'date',
        'due_date' => 'date',
        'resolved_date' => 'date',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'hold_type' => ['required', 'in:financial,academic,disciplinary,administrative,health,library'],
            'hold_category' => ['nullable', 'in:registration,graduation,transcript,all'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'in:high,medium,low'],
            'status' => ['nullable', 'in:active,resolved,waived,expired'],
            'placed_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:placed_date'],
            'resolved_date' => ['nullable', 'date', 'after_or_equal:placed_date'],
            'placed_by_user_id' => ['nullable', 'exists:users,id'],
            'resolved_by_user_id' => ['nullable', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'hold_type.required' => 'Hold type is required',
            'title.required' => 'Hold title is required',
            'placed_date.required' => 'Placed date is required',
            'due_date.after_or_equal' => 'Due date must be after or equal to placed date',
            'resolved_date.after_or_equal' => 'Resolved date must be after or equal to placed date',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id')->withTrashed();
    }

    public function placedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_user_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'waived']);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->due_date && $this->due_date->isPast() && $this->status === 'active');
    }

    public function blocksRegistration(): bool
    {
        return $this->isActive() && in_array($this->hold_category, ['registration', 'all']);
    }

    public function blocksGraduation(): bool
    {
        return $this->isActive() && in_array($this->hold_category, ['graduation', 'all']);
    }

    public function blocksTranscript(): bool
    {
        return $this->isActive() && in_array($this->hold_category, ['transcript', 'all']);
    }

    public function resolve(int $resolvedByUserId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_date' => now()->toDateString(),
            'resolved_by_user_id' => $resolvedByUserId,
            'resolution_notes' => $notes,
        ]);
    }

    public function waive(int $resolvedByUserId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'waived',
            'resolved_date' => now()->toDateString(),
            'resolved_by_user_id' => $resolvedByUserId,
            'resolution_notes' => $notes,
        ]);
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeResolved(Builder $query): void
    {
        $query->whereIn('status', ['resolved', 'waived']);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere(function ($subQ) {
                    $subQ->where('status', 'active')
                        ->where('due_date', '<', now());
                });
        });
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('hold_type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): void
    {
        $query->where('hold_category', $category);
    }

    public function scopeByPriority(Builder $query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    public function scopeForStudent(Builder $query, int $studentId): void
    {
        $query->where('student_id', $studentId);
    }
}
