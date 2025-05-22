<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = ['name', 'code', 'description', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Permission::class, 'parent_id');
    }
}
