---
phase: 06
title: Tests
status: completed
---

# Phase 06 — Tests

## Overview

Pest feature + unit tests covering campus-scoped config resolution, CRUD, and job dispatch.

## Test Files

Generate:
```bash
./scripts/dev.sh artisan make:test Feature/EmailConfiguration/CampusScopedEmailConfigurationTest --pest
```

## Test Cases

### Model: getActiveForCampus()

```php
it('returns campus-specific active config when available', function () {
    $global = EmailConfiguration::factory()->create(['campus_id' => null, 'is_active' => true]);
    $campus = Campus::factory()->create();
    $campusConfig = EmailConfiguration::factory()->create(['campus_id' => $campus->id, 'is_active' => true]);

    expect(EmailConfiguration::getActiveForCampus($campus->id)->id)->toBe($campusConfig->id);
});

it('falls back to global config when no campus-specific config exists', function () {
    $global = EmailConfiguration::factory()->create(['campus_id' => null, 'is_active' => true]);
    $campus = Campus::factory()->create();

    expect(EmailConfiguration::getActiveForCampus($campus->id)->id)->toBe($global->id);
});

it('returns null when no config exists for campus or globally', function () {
    $campus = Campus::factory()->create();
    expect(EmailConfiguration::getActiveForCampus($campus->id))->toBeNull();
});
```

### Model: setAsActive() campus scope

```php
it('deactivates only configs in same campus scope when setting active', function () {
    $campus = Campus::factory()->create();
    $config1 = EmailConfiguration::factory()->create(['campus_id' => $campus->id, 'is_active' => true]);
    $config2 = EmailConfiguration::factory()->create(['campus_id' => $campus->id, 'is_active' => false]);
    $global = EmailConfiguration::factory()->create(['campus_id' => null, 'is_active' => true]);

    $config2->setAsActive();

    expect($config1->fresh()->is_active)->toBeFalse();
    expect($config2->fresh()->is_active)->toBeTrue();
    expect($global->fresh()->is_active)->toBeTrue(); // global untouched
});
```

### API: campus-scoped index

```php
it('returns campus-specific and global configs for current campus session', function () {
    $campus = Campus::factory()->create();
    $other = Campus::factory()->create();
    $campusConfig = EmailConfiguration::factory()->create(['campus_id' => $campus->id]);
    $globalConfig = EmailConfiguration::factory()->create(['campus_id' => null]);
    $otherConfig = EmailConfiguration::factory()->create(['campus_id' => $other->id]);

    $this->withSession(['current_campus_id' => $campus->id])
        ->actingAs(User::factory()->create())
        ->getJson('/api/v1/admin/email-configurations')
        ->assertOk()
        ->assertJsonFragment(['id' => $campusConfig->id])
        ->assertJsonFragment(['id' => $globalConfig->id])
        ->assertJsonMissing(['id' => $otherConfig->id]);
});
```

### Job: uses campus config

```php
it('sendSingleEmailJob uses campus-specific config when campus_id provided', function () {
    $campus = Campus::factory()->create();
    $campusConfig = EmailConfiguration::factory()->create([
        'campus_id' => $campus->id,
        'is_active' => true,
    ]);
    $globalConfig = EmailConfiguration::factory()->create([
        'campus_id' => null,
        'is_active' => true,
    ]);

    // Verify getActiveForCampus resolves correct config
    expect(EmailConfiguration::getActiveForCampus($campus->id)->id)->toBe($campusConfig->id);
});
```

## Run Tests

```bash
./scripts/dev.sh test --filter=CampusScopedEmailConfiguration
```

## Todo

- [ ] Create `tests/Feature/EmailConfiguration/CampusScopedEmailConfigurationTest.php`
- [ ] Test `getActiveForCampus()` — campus-specific, fallback, null
- [ ] Test `setAsActive()` — campus scope isolation
- [ ] Test API index — campus filtering
- [ ] Test API store — campus_id persisted
- [ ] Run tests and confirm all pass
