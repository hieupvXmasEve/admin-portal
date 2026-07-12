<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Exceptions;

use RuntimeException;

final class DngCollectionCutoverBlocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('DNG collection is temporarily unavailable while Finance completes cutover reconciliation.');
    }
}
