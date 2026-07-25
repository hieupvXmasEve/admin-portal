---
title: Upload and Storage Operations
status: current
type: runbook
scope: Upload validation and storage operations
last_verified: "2026-07-25"
owner: Upload Module
audience:
  - platform operators
  - developers
  - support engineers
---

# Upload and Storage Operations

## Configuration authority

Upload contexts are defined in `config/uploads.php`. Filesystem disks, roots,
URLs, visibility, and symbolic links are defined in
`config/filesystems.php`. Treat those files as the live inventory; do not copy
their context limits or MIME lists into another document.

The module is exposed under `api/uploads` by
`app/Modules/Upload/Providers/UploadServiceProvider.php`. Route and throttle
ownership is in `app/Modules/Upload/routes/api.php`.

Core owners:

- `app/Modules/Upload/Support/UploadPlatform.php`
- `app/Modules/Upload/Support/UploadManager.php`
- `app/Modules/Upload/Support/FileValidator.php`
- `app/Modules/Upload/Support/ChunkedUploadManager.php`
- `app/Modules/Upload/Support/UploadUrlGenerator.php`
- `app/Models/UploadRecord.php`

## Context selection

Every upload must select a configured context. The context determines size,
allowed MIME types and extensions, destination directory, public/private
intent, thumbnail behavior, and disk.

Do not accept a disk or directory directly from a request. Add or change a
context in `config/uploads.php`, then use `UploadPlatform` or the shared
`FileUploadGateway` contract.

Public configuration and context endpoints expose the client-safe subset of
these rules. Authenticated upload, list, delete, and chunk operations apply
their own throttle and policy checks.

## Storage drivers

Dedicated disks can use local or S3-compatible storage through their
environment-backed driver:

- `IMAGE_STORAGE_DRIVER`
- `AVATAR_STORAGE_DRIVER`
- `ASSIGNMENT_STORAGE_DRIVER`
- `ACTION_ATTACHMENT_STORAGE_DRIVER`
- `FORM_ATTACHMENT_STORAGE_DRIVER`

S3-compatible disks use the standard `AWS_*` values in
`config/filesystems.php`. After changing a driver:

```bash
./scripts/dev.sh artisan config:clear
```

Do not switch an existing context's driver and assume old objects move. A
driver change affects new reads/writes through that disk; object migration and
rollback need a separately reviewed data move.

For local public disks, create the configured links with:

```bash
./scripts/dev.sh artisan storage:link
```

Review `config/filesystems.php` before exposing a local path. The current link
map includes an assignments path even though the assignments disk declares
private visibility. Filesystem visibility and an HTTP access-control policy are
not the same control.

## Validation and malware scanning

`FileValidator` checks the selected context, file size, MIME type, extension,
file signature, filename, dangerous content patterns, and optional advanced
checks.

Virus scanning is disabled by default. If enabling ClamAV:

1. install `clamscan` in every runtime that handles uploads;
2. configure `VIRUS_SCANNING_ENABLED`, engine, timeout, quarantine, and
   scan-failure behavior;
3. verify a clean file and the EICAR test file in a non-production environment;
4. confirm unavailable-scanner behavior meets the environment's fail-closed
   requirement.

The custom virus engine is not implemented. Polyglot and steganography
detection currently log suspicious files rather than blocking them. Do not
claim those flags provide a hard rejection boundary without changing and
testing the executable validator.

## Chunked uploads

Chunk configuration is under `uploads.chunked`. A client must:

1. initialize a session with file metadata;
2. upload zero-based chunks with the expected size;
3. inspect session status as needed;
4. allow the server to assemble the file only after all chunks are present;
5. cancel abandoned sessions when possible.

Sessions are actor-bound and expire. Chunk content is stored on the configured
chunk disk and session state is cached. Production must use a cache shared by
all application instances handling the same upload.

## Cleanup

Preview cleanup first:

```bash
./scripts/dev.sh artisan uploads:cleanup --dry-run
```

Review the command options and then run synchronously or dispatch to the queue
under an approved maintenance window:

```bash
./scripts/dev.sh artisan help uploads:cleanup
```

The command covers orphaned files, expired records, and abandoned chunk
sessions. It is not currently scheduled in `routes/console.php`; production
needs an explicit external schedule or maintenance procedure.

## Diagnosis

### Configuration endpoint and backend disagree

Clear configuration cache and inspect `GetUploadConfigurationQuery` plus the
selected context. Do not add a frontend-only exception.

### A private file is publicly reachable

Check the context's `public` flag, disk visibility, URL generation, web-server
root, and symbolic links. Use the signed `/api/uploads/serve/{id}` path for
private records and verify its authorization/expiry behavior.

### Chunk upload cannot resume

Check shared cache connectivity, chunk-disk persistence, actor identity,
session expiry, total chunk count, and chunk size. A different actor must not
be able to inspect or complete the session.

### S3 uploads succeed but URLs fail

Check the disk's `url`, endpoint, bucket policy, object visibility, and any CDN
origin. A successful object write does not prove the generated public URL is
valid.

## Focused validation

```bash
./scripts/dev.sh artisan test --compact \
  tests/Feature/Upload/UploadPlatformTest.php
```

Add focused policy, validation, or storage-fake coverage for the context being
changed.
