<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(DepartmentMembership::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, DepartmentMembership::class, 'department_id', 'id', 'id', 'user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DepartmentMembership::class)->where('is_active', true);
    }

    public function heads(): HasMany
    {
        return $this->hasMany(DepartmentMembership::class)
            ->where('department_role', 'head')
            ->where('is_active', true);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(DepartmentMembership::class)
            ->where('department_role', 'staff')
            ->where('is_active', true);
    }
}
