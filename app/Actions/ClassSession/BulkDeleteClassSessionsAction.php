<?php

declare(strict_types=1);

namespace App\Actions\ClassSession;

use App\Modules\Academic\Delivery\Support\ClassSessionService;

class BulkDeleteClassSessionsAction
{
    public function __construct(
        protected ClassSessionService $classSessionService
    ) {}

    /**
     * Delete multiple class sessions
     *
     * @param  array<int>  $ids
     * @return int Number of deleted sessions
     */
    public function execute(array $ids): int
    {
        return $this->classSessionService->deleteBulk($ids);
    }
}
