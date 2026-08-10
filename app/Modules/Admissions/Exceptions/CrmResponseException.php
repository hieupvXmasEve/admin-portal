<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Exceptions;

/** `/api/ne` responded 2xx but the envelope shape is wrong: missing `data`, or `data` not a list. */
final class CrmResponseException extends CrmSyncException {}
