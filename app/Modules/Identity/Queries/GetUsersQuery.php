<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class GetUsersQuery
{
    public function handle(array $filters, int $campusId, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters, $campusId);

        return $query->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    public function getRoles(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function getUserRoleIds(User $user, int $campusId): array
    {
        return $user->campusRoles()
            ->wherePivot('campus_id', $campusId)
            ->pluck('role_id')
            ->toArray();
    }

    private function buildQuery(array $filters, int $campusId): Builder
    {
        $query = User::query()->orderBy('id', 'desc');

        // Global search (name and email)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (! empty($filters['role_id'])) {
            $query->whereHas('campusRoles', function ($q) use ($filters, $campusId) {
                $q->where('role_id', $filters['role_id'])
                    ->where('campus_id', $campusId);
            });
        }

        // Filter by user type
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Eager load relationships
        $query->with(['department', 'campusRoles' => function ($q) use ($campusId) {
            $q->wherePivot('campus_id', $campusId);
        }]);

        return $query;
    }
}
