# Backend Patterns

Laravel Query class and Controller patterns for filter/table pages.

## Query Class Pattern

```php
<?php
declare(strict_types=1);

namespace App\Modules\{Domain}\Queries;

use App\Models\{Model};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class List{Resource}Query
{
    private const SORTABLE_COLUMNS = [
        'name' => 'table.name',
        'created_at' => 'table.created_at',
    ];

    public function handle(array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $sortColumn = self::SORTABLE_COLUMNS[$sortKey] ?? self::SORTABLE_COLUMNS['created_at'];
        $sortDir = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return Model::query()
            ->with(['relation:id,name'])
            ->select('table.*')
            ->when($filters['search'] ?? null, fn(Builder $q, string $s) =>
                $q->where(fn($sub) => $sub->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")))
            ->when($filters['date_from'] ?? null, fn(Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn(Builder $q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['status'] ?? null, fn(Builder $q, $s) => $q->where('status', $s))
            ->when($campusId, fn(Builder $q, int $id) => $q->where('campus_id', $id))
            ->orderBy($sortColumn, $sortDir)->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
```

## Controller Pattern

```php
<?php
declare(strict_types=1);

namespace App\Modules\{Domain}\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\{Domain}\Queries\List{Resource}Query;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class {Resource}Controller extends Controller
{
    public function __construct(private readonly List{Resource}Query $listQuery) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $allowedSorts = ['name', 'created_at'];
        $sort = in_array($validated['sort'] ?? 'created_at', $allowedSorts, true)
            ? $validated['sort'] : 'created_at';
        $direction = strtolower((string)($validated['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Admin/{Resource}/Index', [
            'items' => $this->listQuery->handle($validated, session('current_campus_id')),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'per_page' => (int) ($validated['per_page'] ?? 15),
                'page' => (int) ($validated['page'] ?? 1),
                'sort' => $sort, 'direction' => $direction,
            ],
        ]);
    }
}
```

## Validation Rules Reference

| Filter | Rules |
|--------|-------|
| search | `['nullable', 'string', 'max:255']` |
| date | `['nullable', 'date']` |
| status | `['nullable', 'string', 'in:a,b,c']` |
| per_page | `['nullable', 'integer', 'min:1', 'max:100']` |
| page | `['nullable', 'integer', 'min:1']` |
| sort | `['nullable', 'string', 'max:50']` |
| direction | `['nullable', 'string', 'in:asc,desc']` |

## With Subquery Counts

```php
$linkedCountSub = RelatedModel::query()
    ->selectRaw('COUNT(*)')
    ->whereColumn('foreign_id', 'main_table.id');

return Model::query()
    ->select('main_table.*')
    ->selectSub($linkedCountSub, 'linked_count')
    // ...rest of query
```
