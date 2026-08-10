<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Exceptions;

/** 4xx/5xx/timeout/connection error while calling `/api/ne`. */
final class CrmRequestException extends CrmSyncException {}
