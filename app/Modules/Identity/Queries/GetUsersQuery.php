<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetUsersQuery
{
    public function __construct(
        private ?int $currentCampusId = null
    ) {
        $this->currentCampusId = session('current_campus_id');
    }

    public function handle(array $filters = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters);

        return $query->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    public function getRoles(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function getUserRoleIds(User $user): array
    {
        return $user->campusRoles()
            ->where('campus_id', $this->currentCampusId)
            ->pluck('role_id')
            ->toArray();
    }

    private function buildQuery(array $filters): Builder
    {
        $query = User::query()->orderBy('id', 'desc');

        // Global search (name and email)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role_id'])) {
            $query->whereHas('campusRoles', function ($q) use ($filters) {
                $q->where('role_id', $filters['role_id'])
                    ->where('campus_id', $this->currentCampusId);
            });
        }

        // Filter by user type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Eager load relationships
        $query->with(['department', 'campusRoles' => function ($q) {
            $q->wherePivot('campus_id', $this->currentCampusId);
        }]);

        return $query;
    }
}