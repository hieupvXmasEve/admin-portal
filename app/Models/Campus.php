<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    /** @use HasFactory<\Database\Factories\CampusFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'address'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_campus_roles', 'campus_id', 'user_id')
            ->withPivot('role_id')
            ->withTimestamps();
    }

}
