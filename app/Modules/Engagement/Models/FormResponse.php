<?php

namespace App\Modules\Engagement\Models;

use App\Models\Answer;
use App\Models\Campus;
use App\Models\Student;
use App\Models\UploadRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'responses';

    protected $fillable = [
        'form_id',
        'form_version_id',
        'form_target_id',
        'campus_id',
        'target_scope_type',
        'target_scope_id',
        'submitted_by_student_id',
        'anonymized',
        'status',
        'query_status',
        'assigned_to_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'origin',
        'submitted_at',
    ];

    protected $casts = [
        'anonymized' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'status' => 'string',
        'origin' => 'string',
        'target_scope_type' => 'string',
    ];

    /**
     * Get the form that owns the response.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the form version used for the response.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function formTarget(): BelongsTo
    {
        return $this->belongsTo(FormTarget::class);
    }

    /**
     * Get the campus associated with the response.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get the student who submitted the response.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'submitted_by_student_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Get the user assigned to this response (for queries).
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the answers for the response.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'response_id');
    }

    /**
     * Get the attachments for the response.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(UploadRecord::class, 'response_id');
    }

    /**
     * Get the query ticket for this response (if it's a query type).
     */
    public function queryTicket(): HasOne
    {
        return $this->hasOne(QueryTicket::class, 'response_id');
    }

    /**
     * Get the assignment audit history for this response.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(QueryAssignment::class, 'response_id');
    }

    /**
     * Scope to get responses by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get responses for a campus.
     */
    public function scopeForCampus(Builder $query, $campusId): Builder
    {
        return $query->where('campus_id', $campusId);
    }

    /**
     * Scope to get responses pending review.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'submitted')
            ->whereNull('reviewed_by_user_id');
    }

    /**
     * Scope to get non-anonymized responses.
     */
    public function scopeNotAnonymized(Builder $query): Builder
    {
        return $query->where('anonymized', false);
    }

    /**
     * Check if the response can be reviewed by a user.
     */
    public function canBeReviewedBy(User $user): bool
    {
        // Check if user has appropriate role in the campus
        $allowedRoles = ['admin'];

        return $user->campusUserRoles()
            ->where('campus_id', $this->campus_id)
            ->whereHas('role', function ($query) use ($allowedRoles) {
                $query->whereIn('code', $allowedRoles);
            })
            ->exists();
    }

    /**
     * Approve the response.
     */
    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Reject the response.
     */
    public function reject(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Get the display name for the submitter.
     */
    public function getSubmitterNameAttribute(): string
    {
        if ($this->anonymized) {
            return 'Anonymous';
        }

        return $this->student ? $this->student->full_name : 'Unknown';
    }
}
