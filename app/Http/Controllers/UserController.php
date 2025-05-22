<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request, User $user)
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'filter.name' => 'string|max:255',
            'filter.email' => 'string|max:255',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;

        $query = $user->newQuery()->orderBy('id', 'desc');

        // Global search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Column filters
        if (!empty($validated['filter'])) {
            foreach ($validated['filter'] as $column => $value) {
                if (!empty($value) && in_array($column, ['name', 'email'])) {
                    $query->where($column, 'like', "%{$value}%");
                }
            }
        }

        $users = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('users/List', [
            'users' => Inertia::deepMerge($users),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'name' => $validated['filter']['name'] ?? null,
                'email' => $validated['filter']['email'] ?? null,
            ],
        ]);
    }
}
