<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email_snapshot',
        'status',
    ];

    /**
     * Get the user that owns the parent profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The students the parent has an active Guardian access grant for.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_access_grants', 'parent_id', 'student_id')
            ->wherePivot('status', 'active')
            ->withPivot(['status', 'access_level'])
            ->withTimestamps();
    }
}
