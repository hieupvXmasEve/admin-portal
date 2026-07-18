<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Models;

use Illuminate\Database\Eloquent\Model;

final class FacultyAccessEligibilityOutbox extends Model
{
    protected $table = 'faculty_access_eligibility_outbox';

    protected $fillable = [
        'deduplication_key', 'lecturer_id', 'user_id', 'token_subject_type',
        'is_eligible', 'reason', 'evaluated_at', 'status', 'dispatched_at', 'last_error',
    ];

    protected function casts(): array
    {
        return ['is_eligible' => 'boolean', 'evaluated_at' => 'datetime', 'dispatched_at' => 'datetime'];
    }
}
