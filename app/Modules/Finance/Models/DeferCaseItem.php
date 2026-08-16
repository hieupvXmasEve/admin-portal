<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeferCaseItem extends Model
{
    protected $fillable = [
        'defer_case_id',
        'course_registration_id',
        'fee_policy',
        'preserve_amount',
        'applied_at',
        'applied_semester_id',
    ];

    protected $casts = [
        'preserve_amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    // =====================
    // Constants
    // =====================

    public const POLICY_PRESERVE = 'PRESERVE';

    public const POLICY_FORFEIT = 'FORFEIT';

    // =====================
    // Relationships
    // =====================

    public function deferCase(): BelongsTo
    {
        return $this->belongsTo(DeferCase::class);
    }

    // =====================
    // Accessors
    // =====================

    public function getStudentAttribute(): ?Student
    {
        return $this->deferCase?->student;
    }

    public function getEffectiveFeePolicyAttribute(): string
    {
        return $this->fee_policy ?? $this->deferCase?->fee_policy ?? DeferCase::POLICY_FORFEIT;
    }

    public function getIsPreservedAttribute(): bool
    {
        return $this->effective_fee_policy === self::POLICY_PRESERVE;
    }
}
