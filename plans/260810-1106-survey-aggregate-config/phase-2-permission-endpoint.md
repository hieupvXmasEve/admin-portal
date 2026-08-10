# Phase 2 — Permission (incl. role grant) + gated save endpoint + route hardening

## Context

- Permissions declared in `config/permission.php` (`access.surveys` group L469-477).
  `app/Console/Commands/SyncPermissions.php` (`permissions:sync`) only CREATES permission
  rows — it grants no roles. Gate resolution goes through campus-scoped grants
  (`app/Modules/Identity/Providers/IdentityServiceProvider.php` L63-81,
  `CampusPermissionReader`); there is NO `Gate::before` super-admin bypass.
  `database/seeders/UpdatePermissionsSeeder.php` assigns all permissions to super admin.
- Red-team finding: form write routes in `app/Modules/Engagement/routes/web.php` L84-96
  (`POST /forms/admin`, `PUT /forms/admin/{form}`, `DELETE ...`) carry NO `can:` middleware
  and `UpdateFormRequest` has no `authorize()` — any authenticated user can rewrite form
  structure (which creates a new version). Must be closed or the new permission is decorative.

## Steps

1. `config/permission.php` → `surveys` group: add `'configure_survey_aggregate' => 'configure_survey_aggregate'`.
2. Grant step (explicit, not just sync):
   - `./scripts/dev.sh artisan permissions:sync` (creates row),
   - run `UpdatePermissionsSeeder` (super admin gets it),
   - grant to roles currently holding `edit_survey` (document exact role names found in DB
     at implementation time; campus-scoped grant path — verify with one manual gate check).
   - Deploy order: migrate → sync → grant → code live. Acceptance #3 requires a post-deploy
     usable check, not just row existence.
3. Route hardening (pre-existing gap, in-scope per red team):
   `app/Modules/Engagement/routes/web.php` — add `->middleware('can:create_survey')` to
   form create/store, `can:edit_survey` to edit/update, `can:delete_survey` to destroy
   (keys already exist in config/permission.php L469-477). Run existing Form feature tests
   to catch fixtures lacking these permissions.
4. New FormRequest `app/Modules/Engagement/Http/Requests/Forms/UpdateAggregateConfigRequest.php`:
   - `authorize()`: `$this->user()->can('configure_survey_aggregate')`.
   - Rules: `overall.question_codes` `required|array|min:1`, each `string`;
     `overall.thresholds.positive_min` `required|integer|between:1,5`;
     `overall.thresholds.negative_max` `required|integer|between:1,5`;
     `gt` cross-rule: `positive_min > negative_max` (withValidator).
   - withValidator: resolve the form's **latest published version server-side** (same
     resolution `FormWorkflow` uses); every code must exist in that version AND be
     `rating` type. No `form_version_id` in payload — config is form-level.
5. New controller `app/Modules/Engagement/Http/Web/Admin/SurveyAggregateConfigController.php`:
   - `update(UpdateAggregateConfigRequest $r, Form $form)`: `$form->forceFill(['aggregate_config' => $payload])->save()`; redirect back with flash.
   - `destroy(Form $form)`: authorize same permission; null the column (reset to default).
6. Routes in `app/Modules/Engagement/routes/web.php` (forms/admin group):
   - `Route::put('/{form}/aggregate-config', [...,'update'])->middleware('can:configure_survey_aggregate')->name('aggregate-config.update');`
   - `Route::delete('/{form}/aggregate-config', [...,'destroy'])->middleware('can:configure_survey_aggregate')->name('aggregate-config.destroy');`
7. `FormController@edit`: add props `can_configure_aggregate` (gate check) and current
   `aggregate_config` so the panel renders state.

## Validation

- Extend `tests/Feature/Form/SurveyAggregateConfigTest.php`:
  - no permission → 403 on PUT and DELETE.
  - with permission → config persisted; DELETE nulls it.
  - empty `question_codes` → 422; non-rating code → 422; code from another form → 422;
    `positive_min <= negative_max` → 422.
  - mass-assignment guard: `PUT /forms/admin/{form}` (UpdateFormRequest path) with an
    `aggregate_config` key must NOT change the column.
  - hardened routes: user without `edit_survey` gets 403 on `PUT /forms/admin/{form}`.
  - CSRF: include `_token` (repo gotcha).
- Run: `./scripts/dev.sh artisan test --filter=SurveyAggregateConfig` + existing Form tests.

## Risk / rollback

Route hardening may 403 existing internal users missing `edit_survey` — verify role grants
before deploy. Rollback = remove middleware + routes + config key; column untouched.
