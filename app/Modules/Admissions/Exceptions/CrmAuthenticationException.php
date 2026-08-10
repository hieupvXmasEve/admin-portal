<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Exceptions;

/** CRM login failed: non-2xx, `status != success`, or missing `data.token`. */
final class CrmAuthenticationException extends CrmSyncException {}
