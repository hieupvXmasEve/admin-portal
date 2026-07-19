<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface EgcBlockResultReconciler
{
    public function reconcileSemester(int $semesterId): void;
}
