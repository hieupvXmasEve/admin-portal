<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentDirectoryReader
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters, int $campusId): LengthAwarePaginator;
}
