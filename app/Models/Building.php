<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    /** @use HasFactory<\Database\Factories\BuildingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'name',
        'code',
        'description',
        'address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Validation rules
    public static function validationRules(): array
    {
        return [
            'campus_id' => ['required', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:buildings,code'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'campus_id.required' => 'Campus is required',
            'campus_id.exists' => 'Selected campus does not exist',
            'name.required' => 'Building name is required',
            'name.max' => 'Building name must not exceed 100 characters',
            'code.required' => 'Building code is required',
            'code.max' => 'Building code must not exceed 20 characters',
            'code.unique' => 'Building code already exists',
        ];
    }

    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    // Note: Room model not yet implemented
    // public function rooms(): HasMany
    // {
    //     return $this->hasMany(Room::class);
    // }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForCampus($query, $campusId)
    {
        return $query->where('campus_id', $campusId);
    }

    // Accessors & Mutators
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    // Helper methods
    public function getTotalRooms(): int
    {
        // return $this->rooms()->count();
        return 0; // Placeholder until Room model is implemented
    }
}
