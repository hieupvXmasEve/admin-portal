<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Support\BillingExceptionCollector;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use Illuminate\Pagination\LengthAwarePaginator;

class ListBillingExceptionsQuery
{
    public function handle(?int $semesterId, ?string $type, string $path): LengthAwarePaginator
    {
        $campusId = app('campus')?->id;
        $filterType = $type === 'all' ? null : $type;
        $page = (int) request()->get('page', 1);
        $perPage = 20;

        $paginator = app(BillingExceptionCollector::class)
            ->paginate($semesterId, $filterType, $campusId, $page, $perPage, $path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (array $row) => [
                'id' => BillingExceptionIdentifier::encode($row['type'], $row['source_id']),
                'type' => $row['type'],
                'student_id' => $row['student_id'],
                'student_code' => $row['student_code'],
                'student_name' => $row['student_name'],
                'description' => $row['description'],
                'severity' => $row['severity'],
                'context' => $row['context'],
                'created_at' => $row['created_at'],
                'fixable' => $row['fixable'],
            ])
        );
    }
}