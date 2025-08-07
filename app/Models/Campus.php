<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Campus extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\CampusFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'address'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'campus_user_roles')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class);
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }
}
