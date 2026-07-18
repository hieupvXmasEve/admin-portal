<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface StudentLifecycleStatusReader
{
    /**
     * @param  list<int>  $studentIds
     * @return array<int, string>
     */
    public function statusesFor(array $studentIds): array;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, string|null>
     */
    public function academicStatusesFor(array $studentIds): array;
}
