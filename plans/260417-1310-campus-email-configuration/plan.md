---
title: 'Campus-Scoped Email Configuration'
description: 'Add campus_id to email_configurations so each campus can have its own SMTP config. Sending code picks the campus-specific config, falling back to global.'
status: completed
priority: P1
effort: 1-2d
branch: dev
tags: [email, campus, smtp, laravel12, vue3]
created: 2026-04-17
blockedBy: []
blocks: []
---

# Plan Overview

**Goal:** Allow each campus to configure its own SMTP mail account. Email dispatch resolves the correct config per campus (campus-specific → global fallback).

## Phases

| Phase | File | Status | Notes |
|-------|------|--------|-------|
| 01 | `phase-01-migration.md` | completed | Add `campus_id` FK to `email_configurations` |
| 02 | `phase-02-model-service.md` | completed | Campus-scoped model methods + SmtpConfigurationService |
| 03 | `phase-03-job-dispatch.md` | completed | Pass campus_id through Job → EmailService chain |
| 04 | `phase-04-api-controller.md` | completed | Filter API by campus, update CRUD |
| 05 | `phase-05-frontend.md` | completed | Campus selector in UI, filtered config list |
| 06 | `phase-06-tests.md` | completed | Pest feature + unit tests |

## Key Files Touched

- `database/migrations/` — new migration
- `app/Models/EmailConfiguration.php`
- `app/Models/Campus.php`
- `app/Services/SmtpConfigurationService.php`
- `app/Services/EmailService.php`
- `app/Jobs/SendSingleEmailJob.php`
- `app/Jobs/SendBulkEmailJob.php`
- `app/Http/Controllers/Api/V1/Admin/EmailConfigurationController.php`
- `app/Http/Controllers/Web/EmailConfigurationController.php`
- `app/Modules/Notification/Channels/EmailChannelAdapter.php`
- `resources/js/pages/Admin/EmailConfiguration/Index.vue`
- `resources/js/pages/Admin/EmailConfiguration/components/EditSmtpConfigurationModal.vue`
