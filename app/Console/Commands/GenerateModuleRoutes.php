<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateModuleRoutes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:module-routes {module : The module name} {controller? : The controller class name}';

    /**
     * The console command description.
     */
    protected $description = 'Generate route file for a module with automatic permission middleware';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = $this->argument('module');
        $controller = $this->argument('controller') ?? Str::studly($module) . 'Controller';

        // Check if module exists in permission config
        $permissions = config("permission.access.{$module}");
        if (!$permissions) {
            $this->error("Module '{$module}' not found in permission configuration.");
            $this->info("Please add the module to config/permission.php first.");
            return 1;
        }

        $routeFile = base_path("routes/{$module}.php");

        if (File::exists($routeFile)) {
            if (!$this->confirm("Route file for '{$module}' already exists. Overwrite?")) {
                return 0;
            }
        }

        $content = $this->generateRouteContent($module, $controller);

        File::put($routeFile, $content);

        $this->info("Route file created: routes/{$module}.php");

        // Check if route is included in web.php
        $webRoutes = File::get(base_path('routes/web.php'));
        $requireStatement = "require __DIR__ . '/{$module}.php';";

        if (!str_contains($webRoutes, $requireStatement)) {
            $this->info("Don't forget to add this line to routes/web.php:");
            $this->line($requireStatement);
        }

        return 0;
    }

    /**
     * Generate the route file content
     */
    private function generateRouteContent(string $module, string $controller): string
    {
        $controllerClass = "App\\Http\\Controllers\\{$controller}";

        return "<?php

use {$controllerClass};
use App\\Helpers\\RoutePermissionHelper;
use Illuminate\\Support\\Facades\\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: '{$module}',
        controller: {$controller}::class,
        module: '{$module}'
    );
});
";
    }
}
