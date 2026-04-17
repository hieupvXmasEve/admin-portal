<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\CampusFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'dng_code', 'address'];

    public function getDngCode(): ?string
    {
        $dngCode = trim((string) $this->dng_code);

        return $dngCode !== '' ? $dngCode : null;
    }

    public function emailConfigurations(): HasMany
    {
        return $this->hasMany(EmailConfiguration::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'campus_user_roles', 'campus_id', 'user_id')
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
