<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class EgcChargeGenerationController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('finance.batch-studio.charges', $this->safePrefill($request, 'egc'));
    }

    public function store(Request $request): HttpResponse
    {
        return response('EGC charges must be generated through Batch Studio.', 410);
    }

    /**
     * @return array<string, mixed>
     */
    private function safePrefill(Request $request, string $feeCategory): array
    {
        return array_filter([
            'fee_category' => $feeCategory,
            'semester_id' => $this->positiveInt($request->query('semester_id')),
            'search' => $this->boundedString($request->query('search'), 100),
            'ignore_student_ids' => $this->boundedString($request->query('ignore_student_ids'), 10000),
            'per_page' => $this->allowedPerPage($request->query('per_page')),
            'page' => $this->positiveInt($request->query('page')),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function allowedPerPage(mixed $value): ?int
    {
        return is_numeric($value) && in_array((int) $value, [20, 50, 100], true) ? (int) $value : null;
    }

    private function boundedString(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
