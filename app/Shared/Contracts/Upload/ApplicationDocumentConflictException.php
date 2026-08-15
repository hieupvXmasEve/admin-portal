<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

use InvalidArgumentException;

/**
 * Thrown by ApplicationDocumentWriter::upsert() when the write cannot
 * proceed safely — a crm_file_id collision with a different application, or
 * a document link that fails the http(s)-scheme requirement. A distinct
 * type (rather than a bare InvalidArgumentException) so callers can catch
 * exactly this failure mode instead of every InvalidArgumentException in
 * their call graph, which could otherwise include unrelated framework or
 * Carbon exceptions that also extend it.
 *
 * Extends InvalidArgumentException so existing bare `catch
 * (InvalidArgumentException)` blocks (e.g. CrmApplicationSyncService's
 * per-record failure handling) keep working unchanged.
 */
final class ApplicationDocumentConflictException extends InvalidArgumentException {}
