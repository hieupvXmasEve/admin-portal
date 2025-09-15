<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'form_version_id',
        'campus_id',
        'scope_type',
        'scope_id',
        'start_at',
        'end_at',
        'submission_limit_per_user',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'scope_type' => 'string',
    ];

    /**
     * Get the form that owns the target.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the form version for the target.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the campus for the target.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Check if the target is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->start_at <= $now && (!$this->end_at || $this->end_at >= $now);
    }
}