<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'user_id',
        'department_role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHeads($query)
    {
        return $query->where('department_role', 'head');
    }

    public function scopeStaff($query)
    {
        return $query->where('department_role', 'staff');
    }
}
