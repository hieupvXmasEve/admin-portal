---
phase: 02
title: Model & Service Layer
status: completed
---

# Phase 02 — Model & Service Layer

## Overview

Update `EmailConfiguration` and `Campus` models for campus-scoped config lookup. Update `SmtpConfigurationService` CRUD to handle campus_id.

## EmailConfiguration Model Changes

**File:** `app/Models/EmailConfiguration.php`

1. Add `campus_id` to `$fillable`
2. Add `campus()` belongsTo relationship
3. Replace `getActive(): ?self` with campus-aware version:

```php
// Replaces current getActive()
public static function getActiveForCampus(?int $campusId): ?self
{
    if ($campusId) {
        // Campus-specific config takes priority
        $config = static::where('is_active', true)->where('campus_id', $campusId)->first();
        if ($config) return $config;
    }
    // Fall back to global config (campus_id = null)
    return static::where('is_active', true)->whereNull('campus_id')->first();
}

// Keep getActive() as alias for BC (used by SendSingleEmailJob fallback)
public static function getActive(): ?self
{
    return static::getActiveForCampus(null);
}
```

4. Update `setAsActive()` — deactivate only within same campus scope:

```php
public function setAsActive(): bool
{
    $query = static::where('id', '!=', $this->id);
    if ($this->campus_id) {
        $query->where('campus_id', $this->campus_id);
    } else {
        $query->whereNull('campus_id');
    }
    $query->update(['is_active' => false]);

    return $this->update(['is_active' => true]);
}
```

## Campus Model Changes

**File:** `app/Models/Campus.php`

Add relationship:
```php
public function emailConfigurations()
{
    return $this->hasMany(EmailConfiguration::class);
}

public function activeEmailConfiguration(): ?EmailConfiguration
{
    return $this->emailConfigurations()->where('is_active', true)->first();
}
```

## SmtpConfigurationService Changes

**File:** `app/Services/SmtpConfigurationService.php`

1. `create()` — accept and pass `campus_id` through (already in `$data` array)
2. `getAll()` — accept optional `?int $campusId` param, filter by campus scope:
   ```php
   public function getAll(?int $campusId = null): Collection
   {
       $query = EmailConfiguration::orderBy('is_active', 'desc')->orderBy('name');
       if ($campusId !== null) {
           $query->where(function($q) use ($campusId) {
               $q->where('campus_id', $campusId)->orWhereNull('campus_id');
           });
       }
       return $query->get();
   }
   ```
3. `getActive()` — update to call `EmailConfiguration::getActiveForCampus()`
4. `delete()` — update uniqueness check to be campus-scoped
5. `validateConfiguration()` — add `campus_id` to rules: `'campus_id' => 'nullable|exists:campuses,id'`

## Todo

- [ ] Update `EmailConfiguration::$fillable` (add `campus_id`)
- [ ] Add `EmailConfiguration::campus()` relationship
- [ ] Replace/update `getActive()` with `getActiveForCampus(?int $campusId)`
- [ ] Update `setAsActive()` for campus scope
- [ ] Add `Campus::emailConfigurations()` and `activeEmailConfiguration()`
- [ ] Update `SmtpConfigurationService::getAll()` with optional campus filter
- [ ] Update `SmtpConfigurationService::delete()` campus-scoped check
- [ ] Update `SmtpConfigurationService::validateConfiguration()` rules
