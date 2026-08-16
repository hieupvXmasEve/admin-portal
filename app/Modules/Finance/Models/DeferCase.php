<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeferCase extends Model
{
    protected $fillable = [
        'student_action_log_id',
        'student_id',
        'semester_id',
        'applies_until_semester_id',
        'scope_type',
        'fee_policy',
        'applies_once',
        'preserve_amount',
        'effective_at',
        'signed_at',
        'applied_at',
        'applied_semester_id',
        'upload_record_id',
        'changed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'preserve_amount' => 'decimal:2',
        'effective_at' => 'date',
        'signed_at' => 'date',
        'applied_at' => 'datetime',
        'applies_once' => 'boolean',
    ];

    // =====================
    // Constants
    // =====================

    public const SCOPE_FULL = 'FULL';

    public const SCOPE_COURSES = 'COURSES';

    public const POLICY_PRESERVE = 'PRESERVE';

    public const POLICY_FORFEIT = 'FORFEIT';

    public const POLICY_PARTIAL = 'PARTIAL';

    // =====================
    // Relationships
    // =====================

    public function actionLog(): BelongsTo
    {
        return $this->belongsTo(StudentActionLog::class, 'student_action_log_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeferCaseItem::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * Get the defer credit charge created from this case.
     */
    public function creditCharge(): HasOne
    {
        return $this->hasOne(FinanceCharge::class, 'source_id')
            ->where('source_type', self::class)
            ->where('charge_type', FinanceEntitlementType::DeferCredit);
    }

    // =====================
    // Scopes
    // =====================

    public function scopeFullSemester($query)
    {
        return $query->where('scope_type', self::SCOPE_FULL);
    }

    public function scopeCourseLevel($query)
    {
        return $query->where('scope_type', self::SCOPE_COURSES);
    }

    public function scopePreserved($query)
    {
        return $query->whereIn('fee_policy', [self::POLICY_PRESERVE, self::POLICY_PARTIAL]);
    }

    public function scopeForfeited($query)
    {
        return $query->where('fee_policy', self::POLICY_FORFEIT);
    }

    // =====================
    // Accessors
    // =====================

    public function getIsFullSemesterAttribute(): bool
    {
        return $this->scope_type === self::SCOPE_FULL;
    }

    public function getIsCourseLevelAttribute(): bool
    {
        return $this->scope_type === self::SCOPE_COURSES;
    }

    public function getHasPreservedFeeAttribute(): bool
    {
        return in_array($this->fee_policy, [self::POLICY_PRESERVE, self::POLICY_PARTIAL]);
    }

    public function getHasCreditChargeAttribute(): bool
    {
        return $this->creditCharge()->exists();
    }
}
