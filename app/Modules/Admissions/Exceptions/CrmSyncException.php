<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Exceptions;

use RuntimeException;

/**
 * Base for every CRM integration failure. Messages must never carry
 * credentials, tokens, or the request/response body — only status code and
 * endpoint (Goal 6).
 */
class CrmSyncException extends RuntimeException {}
