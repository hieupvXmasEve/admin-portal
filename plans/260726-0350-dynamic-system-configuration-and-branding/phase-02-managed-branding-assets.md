---
phase: 2
title: "Managed Branding Assets"
status: in-progress
priority: P1
effort: "1.5-2 days"
dependencies: [1]
---

# Phase 2: Managed Branding Assets

## Overview

Replace fixed-name public-disk overwrites and editable raw paths with immutable
uploads owned by the Upload module and referenced from system settings.

## Requirements

- Functional: manage full logo, compact/text logo, favicon, and Apple touch
  icon; atomically switch references; retain the previous successful asset for
  rollback.
- Non-functional: configurable local/S3-compatible storage, immutable URLs,
  content validation, bounded dimensions/size, and no same-origin active SVG.

## Architecture

Add an internal public-file `branding` upload context backed by the existing
configurable images disk unless operations requires a dedicated
`BRANDING_STORAGE_DRIVER`. Generic Upload single/multiple/chunk/validation
routes cannot select it, and generic list/delete operations cannot delete its
records.
Platform calls `FileUploadGateway::store()` with the actor and branding-slot
metadata, then stores only the returned upload ID under private setting keys.
The public branding projection resolves upload IDs to URLs through the gateway.

Uploads use generated immutable names. Text updates never accept `logo_*`,
`favicon`, or other raw paths. Store the new object first, transactionally swap
the DB reference, then invalidate cache. A failed DB swap leaves the previous
reference active and marks the new object for compensating cleanup. Upload
itself removes stored bytes if `UploadRecord` creation fails before the gateway
can return an ID.

## Related Code Files

- Modify: `/Users/hunt2412/hieupvdev/project/swinx/config/uploads.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/config/filesystems.php` only if a dedicated branding disk is justified
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/.env.example` only if a new disk variable is introduced
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Shared/Contracts/Upload/FileUploadGateway.php` if compensation requires a narrow delete operation
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Support/UploadFileGateway.php` with the same narrow operation
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Support/UploadManager.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Support/FileValidator.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Http/Requests/Upload/UploadRequest.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Queries/ListUploadsQuery.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload/Policies/UploadRecordPolicy.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Actions/UploadSystemConfigurationFileAction.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Http/Requests/SystemConfiguration/UploadSystemConfigurationFileRequest.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Queries/GetPublicSystemConfigurationQuery.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemConfigurationMigrationTest.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemBrandingUploadTest.php`

## Implementation Steps

1. Define the four server-owned branding slot names and their setting keys.
2. Add the internal `branding` context and reject it from generic Upload
   single/multiple/chunk/validate/list-delete lifecycles.
3. Extend Upload-owned validation with decoded width/height/total-pixel bounds,
   fail-closed decode behavior, and MIME-to-canonical-extension filename mapping.
4. Remove raw logo-path fields from text-update validation. Resolve public
   `logo_full`/`logo_text`/favicon URLs only when their upload IDs are present.
5. Route asset writes through `FileUploadGateway`; include slot, actor, and
   replacement metadata without importing `UploadRecord`.
6. Make Upload compensate stored bytes when record creation fails. Then swap
   the upload ID setting and audit the changed slot in one DB transaction.
   Preserve the previous ID; compensate or flag the new upload if the swap fails.
7. Initialize all four branding upload IDs as `null`; render an explicit empty
   state until the user uploads replacements.
8. Return resolved immutable URL and branding version/updated timestamp.
9. Test local fake storage through the contract. Keep S3 behavior abstracted by
   the disk; do not attempt object migration in this feature.

## Todo

- [x] Add safe branding upload context.
- [x] Prevent generic Upload APIs from creating/deleting branding records.
- [x] Add Upload-owned compensation, dimension limits, and canonical extensions.
- [x] Start all four branding slots empty without importing legacy assets.
- [x] Remove client-controlled storage paths and fixed filename overwrite.
- [x] Persist upload IDs and resolve immutable URLs.
- [ ] Add failure/rollback and malicious-file coverage.

## Success Criteria

- [x] A manager cannot overwrite an arbitrary `/storage/...` object.
- [x] Campus-only managers and generic Upload callers cannot create/delete branding.
- [ ] Wrong extension/MIME/signature, oversized images, and SVG active content are rejected.
- [ ] Successful replacement changes the URL and keeps the previous object available.
- [ ] Storage failure or DB failure leaves the previous branding active.
- [x] Empty initial branding slots render safely until the user uploads assets.
- [ ] Local persistent volume and S3-compatible disks use the same Platform contract.

## Risk Assessment

- Object storage and MySQL are not one transaction. Preserve the old reference,
  compensate the new object on failure, and make cleanup observable.
- Upload may fail after storing bytes but before creating a record; compensation
  belongs inside Upload because Platform has no ID at that point.
- S3 driver changes do not move existing objects. Operations must treat that as
  a separate reviewed migration.
- Indefinite old-object retention leaks storage. Automatic retention cleanup is
  deferred; record enough metadata for a later bounded cleanup.

## Security Considerations

- Do not accept SVG in the first release. Adding it requires an approved
  sanitizer/dependency and dedicated tests.
- Validate decoded content, MIME, extension, dimensions, and size through Upload.
- Require the global `super_admin` system role; UI visibility is not authorization.
