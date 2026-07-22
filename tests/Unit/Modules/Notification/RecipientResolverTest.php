<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\User;
use App\Modules\Notification\Support\RecipientResolver;

beforeEach(function () {
    $this->resolver = new RecipientResolver;
});

describe('department target type', function () {
    it('resolves all active members of a department', function () {
        $dept = Department::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DepartmentMembership::factory()->create([
            'department_id' => $dept->id,
            'user_id' => $user1->id,
            'is_active' => true,
        ]);
        DepartmentMembership::factory()->create([
            'department_id' => $dept->id,
            'user_id' => $user2->id,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolve([
            ['type' => 'department', 'id' => $dept->id],
        ], null);

        expect($result['resolved_recipients'])->toContain(
            ['key' => 'user:'.$user1->id, 'user_id' => $user1->id, 'email' => null],
            ['key' => 'user:'.$user2->id, 'user_id' => $user2->id, 'email' => null],
        )
            ->and($result['unresolved'])->toBeEmpty();
    });

    it('returns no user_ids when department has no active members', function () {
        $dept = Department::factory()->create();
        $user = User::factory()->create();

        DepartmentMembership::factory()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $result = $this->resolver->resolve([
            ['type' => 'department', 'id' => $dept->id],
        ], null);

        expect($result['resolved_recipients'])->toBeEmpty()
            ->and($result['unresolved'])->toBeEmpty();
    });

    it('adds unresolved entry when department not found', function () {
        $result = $this->resolver->resolve([
            ['type' => 'department', 'id' => 99999],
        ], null);

        expect($result['resolved_recipients'])->toBeEmpty()
            ->and($result['unresolved'])->toHaveCount(1)
            ->and($result['unresolved'][0]['reason'])->toBe('department_not_found');
    });

    it('deduplicates user_ids when user is member of resolved department', function () {
        $dept = Department::factory()->create();
        $user = User::factory()->create();

        DepartmentMembership::factory()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $result = $this->resolver->resolve([
            ['type' => 'department', 'id' => $dept->id],
            ['type' => 'department', 'id' => $dept->id],
        ], null);

        expect($result['resolved_recipients'])->toHaveCount(1);
    });
});

describe('external email target type', function () {
    it('normalizes and resolves valid email targets without an internal user', function () {
        $result = $this->resolver->resolve([
            ['type' => 'email', 'email' => 'EXTERNAL@Example.test'],
        ], 123);

        expect($result['resolved_recipients'])->toBe([
            ['key' => 'email:external@example.test', 'user_id' => null, 'email' => 'external@example.test'],
        ])->and($result['unresolved'])->toBeEmpty();
    });

    it('rejects invalid external email targets', function () {
        $result = $this->resolver->resolve([
            ['type' => 'email', 'email' => 'not-an-email'],
        ], null);

        expect($result['resolved_recipients'])->toBeEmpty()
            ->and($result['unresolved'][0]['reason'])->toBe('invalid_email_target');
    });
});
