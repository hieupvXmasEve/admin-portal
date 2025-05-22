<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampusUserRole extends Model
{
    /** @use HasFactory<\Database\Factories\CampusUserRoleFactory> */
    use HasFactory;

    protected $table = 'campus_user_roles';

    protected $fillable = ['user_id', 'campus_id', 'role_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
