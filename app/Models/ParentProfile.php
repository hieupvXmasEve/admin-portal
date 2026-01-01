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
     * The students that belong to the parent.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot(['relationship', 'is_primary', 'access_level'])
            ->withTimestamps();
    }
}
