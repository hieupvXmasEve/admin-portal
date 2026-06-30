<?php

declare(strict_types=1);

use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Tools\GetEntityProfileMcpTool;
use App\Mcp\Tools\QueryMetricsMcpTool;
use App\Mcp\Tools\SearchEntitiesMcpTool;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Invariant seam (ADR-0008/0011): every MCP tool is read-only and reaches data ONLY
 * through the dispatcher/resolvers/recorder — no direct model or query use. A future
 * change that introduces a write path or a data-access bypass must fail this test.
 *
 * Discovery is by directory scan, so a newly added tool is covered automatically.
 */

/**
 * @return list<class-string<Tool>>
 */
function mcpToolClasses(): array
{
    // Resolve from the filesystem (not app_path()) so this works when Pest evaluates the
    // dataset closure before the application container is booted.
    $toolDir = dirname(__DIR__, 3).'/app/Mcp/Tools';

    return collect(glob($toolDir.'/*McpTool.php') ?: [])
        ->map(fn (string $path): string => 'App\\Mcp\\Tools\\'.basename($path, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Tool::class))
        ->values()
        ->all();
}

it('discovers all three MCP tools by directory scan', function () {
    expect(mcpToolClasses())->toHaveCount(3)
        ->toContain(
            QueryMetricsMcpTool::class,
            SearchEntitiesMcpTool::class,
            GetEntityProfileMcpTool::class,
        );
});

it('annotates every MCP tool read-only', function (string $toolClass) {
    $attributes = (new ReflectionClass($toolClass))->getAttributes(IsReadOnly::class);

    expect($attributes)->not->toBeEmpty("{$toolClass} is missing the #[IsReadOnly] annotation");

    // The annotation must actually surface as a read-only hint in the tool descriptor
    // the MCP client receives, not just sit unused on the class.
    expect(app($toolClass)->toArray()['annotations'])->toMatchArray(['readOnlyHint' => true]);
})->with(fn () => mcpToolClasses());

it('lets every MCP tool depend only on the dispatcher, resolvers, recorder, catalogs, and auth', function (string $toolClass) {
    $allowed = [
        McpActorResolver::class,
        McpCampusResolver::class,
        ToolDispatcher::class,
        McpAuditRecorder::class,
        MetricCatalog::class,
        EntityCatalog::class,
        StudentProfileSectionCatalog::class,
        AuthFactory::class,
    ];

    $constructor = (new ReflectionClass($toolClass))->getConstructor();

    expect($constructor)->not->toBeNull();

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();

        expect($type)->not->toBeNull("{$toolClass}::\${$parameter->getName()} must be type-hinted")
            ->and($type->getName())->toBeIn($allowed);
    }
})->with(fn () => mcpToolClasses());

arch('MCP tools never touch models, the query builder, or the DB facade directly')
    ->expect('App\Mcp\Tools')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
        'Illuminate\Database\Eloquent\Model',
        'App\Models',
        'App\Modules\AI\Models',
    ])
    // The resolved OAuth actor is passed around as an App\Models\User value object; it is a
    // type, not a data-access path, so it is the single permitted model reference.
    ->ignoring('App\Models\User');
