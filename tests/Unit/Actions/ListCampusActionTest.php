<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Campus\ListCampusAction;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request as RequestFacade;

beforeEach(function () {
    $this->action = new ListCampusAction();
    // Create a fake request for the action to use
    RequestFacade::swap(Request::create('/', 'GET', ['page' => 1]));
});

it('runs without errors and returns paginated results', function () {
    // Arrange: Seed multiple records
    Campus::factory()->count(20)->create();

    // Act
    $result = $this->action->execute(['per_page' => 15]);

    // Assert
    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->count())->toBe(15)
        ->and($result->total())->toBe(20)
        ->and($result->perPage())->toBe(15);
});

it('filters by search keyword matching name', function () {
    // Arrange: Seed distinct records
    Campus::factory()->create(['name' => 'Hanoi Campus']);
    Campus::factory()->create(['name' => 'Ho Chi Minh Campus']);
    Campus::factory()->create(['name' => 'Da Nang Campus']);

    // Act
    $result = $this->action->execute(['search' => 'Hanoi']);

    // Assert
    expect($result->count())->toBe(1)
        ->and($result->first()->name)->toBe('Hanoi Campus');
});

it('filters by search keyword matching code', function () {
    // Arrange
    Campus::factory()->create(['code' => 'HN001']);
    Campus::factory()->create(['code' => 'HCM001']);
    Campus::factory()->create(['code' => 'DN001']);

    // Act
    $result = $this->action->execute(['search' => 'HN001']);

    // Assert
    expect($result->count())->toBe(1)
        ->and($result->first()->code)->toBe('HN001');
});

it('filters by search keyword matching address', function () {
    // Arrange
    Campus::factory()->create(['address' => '123 Hanoi Street']);
    Campus::factory()->create(['address' => '456 Ho Chi Minh Street']);
    Campus::factory()->create(['address' => '789 Da Nang Street']);

    // Act
    $result = $this->action->execute(['search' => 'Hanoi']);

    // Assert
    expect($result->count())->toBe(1)
        ->and($result->first()->address)->toContain('Hanoi');
});

it('sorts by name in ascending order', function () {
    // Arrange
    Campus::factory()->create(['name' => 'Campus C']);
    Campus::factory()->create(['name' => 'Campus A']);
    Campus::factory()->create(['name' => 'Campus B']);

    // Act
    $result = $this->action->execute(['sort' => 'name', 'direction' => 'asc']);

    // Assert
    $items = $result->items();
    expect($items[0]->name)->toBe('Campus A')
        ->and($items[1]->name)->toBe('Campus B')
        ->and($items[2]->name)->toBe('Campus C');
});

it('sorts by name in descending order', function () {
    // Arrange
    Campus::factory()->create(['name' => 'Campus C']);
    Campus::factory()->create(['name' => 'Campus A']);
    Campus::factory()->create(['name' => 'Campus B']);

    // Act
    $result = $this->action->execute(['sort' => 'name', 'direction' => 'desc']);

    // Assert
    $items = $result->items();
    expect($items[0]->name)->toBe('Campus C')
        ->and($items[1]->name)->toBe('Campus B')
        ->and($items[2]->name)->toBe('Campus A');
});

it('defaults to sorting by created_at desc when no sort specified', function () {
    // Arrange
    $oldest = Campus::factory()->create(['created_at' => now()->subDays(3)]);
    $middle = Campus::factory()->create(['created_at' => now()->subDays(2)]);
    $newest = Campus::factory()->create(['created_at' => now()->subDays(1)]);

    // Act
    $result = $this->action->execute([]);

    // Assert
    $items = $result->items();
    expect($items[0]->id)->toBe($newest->id)
        ->and($items[1]->id)->toBe($middle->id)
        ->and($items[2]->id)->toBe($oldest->id);
});

it('handles empty data gracefully', function () {
    // Act
    $result = $this->action->execute([]);

    // Assert
    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->total())->toBe(0)
        ->and($result->count())->toBe(0);
});

it('respects per_page parameter', function () {
    // Arrange
    Campus::factory()->count(25)->create();

    // Act
    $result = $this->action->execute(['per_page' => 10]);

    // Assert
    expect($result->perPage())->toBe(10)
        ->and($result->count())->toBe(10)
        ->and($result->total())->toBe(25);
});

it('includes buildings and users count', function () {
    // Arrange
    $campus = Campus::factory()->create();

    // Act
    $result = $this->action->execute([]);

    // Assert
    $first = $result->first();
    expect($first->buildings_count)->toBe(0)
        ->and($first->users_count)->toBe(0);
});
