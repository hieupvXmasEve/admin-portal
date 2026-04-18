---
phase: 01
title: Database Migration
status: completed
---

# Phase 01 — Database Migration

## Overview

Add nullable `campus_id` FK to `email_configurations`. Null = global fallback config; non-null = campus-specific config.

## Implementation Steps

1. Generate migration:
   ```bash
   ./scripts/dev.sh artisan make:migration add_campus_id_to_email_configurations_table
   ```

2. Migration content:
   ```php
   Schema::table('email_configurations', function (Blueprint $table) {
       $table->foreignId('campus_id')->nullable()->after('id')
             ->constrained('campuses')->onDelete('cascade');
       $table->index('campus_id');
   });
   ```

3. Run migration:
   ```bash
   ./scripts/dev.sh artisan migrate
   ```

## Notes

- No data backfill needed — existing configs become global (campus_id = null)
- The `is_active` column stays; uniqueness is enforced at app level (one active per campus scope), not DB unique constraint — avoids complexity with NULL handling
- Do NOT add a unique constraint on `(campus_id, is_active)` — MySQL treats NULLs as distinct in unique indexes, which would allow multiple global active rows

## Todo

- [ ] Create migration file
- [ ] Run migration
- [ ] Verify with `./scripts/dev.sh artisan migrate:status`
