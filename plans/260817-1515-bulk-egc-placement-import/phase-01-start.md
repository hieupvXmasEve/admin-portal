---
phase: 1
title: "Intake semester contract"
status: done
priority: P1
effort: "1h"
dependencies: []
---

# Phase 1: Intake semester contract

## Overview

Give the Academic module a clean, contract-based way to read the CRM-configured
intake semester code (`/student-applications/crm-mappings`) without depending
on Admissions internals directly. Mirrors the existing
`Shared\Contracts\Admissions\ApplicationProgramMappingReader` pattern, which is
implemented in Admissions and bound in `AdmissionsServiceProvider`.

<!-- Updated: Validation Session 1 - namespace corrected from
Shared\Contracts\Academic to Shared\Contracts\Admissions after confirming
this repo's convention names the contract folder after the *implementing*
module (owner), not the consumer. -->

## Requirements

- Functional: Academic code can ask "what is the currently configured intake
  semester id?" and get back either an `int` id or `null` (unconfigured).
- Non-functional: no new dependency from Academic → Admissions concrete
  classes; Academic only depends on the `Shared\Contracts\Admissions`
  interface.

## Architecture

`CrmMappingSettings::getIntakeCode()` (Admissions, `app/Modules/Admissions/Support/Crm/CrmMappingSettings.php:19`)
returns the mapped `Semester.code` string (or `null`). Wrap that + a
`Semester::where('code', ...)->value('id')` lookup behind a new contract.

## Related Code Files

- Create: `app/Shared/Contracts/Admissions/IntakeSemesterReader.php`
- Create: `app/Modules/Admissions/Support/EloquentIntakeSemesterReader.php`
- Modify: `app/Modules/Admissions/Providers/AdmissionsServiceProvider.php`

## Implementation Steps

1. Define the interface:
   ```php
   namespace App\Shared\Contracts\Admissions;

   interface IntakeSemesterReader
   {
       /** Current CRM-mapped intake semester id, or null if unconfigured. */
       public function currentIntakeSemesterId(): ?int;
   }
   ```
   Namespace it under `Shared\Contracts\Admissions` — matches this repo's
   convention of naming the contract folder after the *implementing* module
   (owner), not the consumer: `ApplicationProgramMappingReader` sits under
   `Shared\Contracts\Admissions` for the same reason (Admissions owns and
   implements it; Academic is the consumer). Confirmed via validate
   interview — see `## Validation Log` in `plan.md`. Keep the docblock
   short; no other methods needed for this plan.

2. Implement in Admissions:
   ```php
   namespace App\Modules\Admissions\Support;

   final class EloquentIntakeSemesterReader implements IntakeSemesterReader
   {
       public function __construct(private readonly CrmMappingSettings $settings) {}

       public function currentIntakeSemesterId(): ?int
       {
           $code = $this->settings->getIntakeCode();
           if ($code === null) {
               return null;
           }

           return Semester::query()->where('code', $code)->value('id');
       }
   }
   ```

3. Bind in `AdmissionsServiceProvider` next to the existing
   `ApplicationProgramMappingReader` binding:
   `$this->app->bind(IntakeSemesterReader::class, EloquentIntakeSemesterReader::class);`

## Success Criteria

- [x] `app(IntakeSemesterReader::class)->currentIntakeSemesterId()` returns the
      same semester id as `CrmMappingSettings::getIntakeCode()` resolved
      through `Semester`.
- [x] Returns `null` when no intake mapping is configured (no exception).
- [x] No `use App\Modules\Admissions\...` (concrete class) import appears
      anywhere under `app/Modules/Academic/` — only the
      `App\Shared\Contracts\Admissions\IntakeSemesterReader` interface.

## Risk Assessment

Low risk, additive-only. The only failure mode is a typo'd semester code in
CRM settings resolving to `null` — already handled by returning `null`
(Phase 3 turns that into a user-facing "intake semester not configured" error
rather than a silent bad placement).
