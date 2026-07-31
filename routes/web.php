<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Controlled MCP server (ADR-0009/0011): OAuth discovery (.well-known/*) + dynamic
// client registration (POST oauth/register) at the application root, so external
// agent clients (Claude, ChatGPT) can self-register and discover the Passport
// authorize/token endpoints with zero install. These routes are CSRF-exempt (see
// bootstrap/app.php); the /mcp/swinx tool endpoint itself lives in routes/ai.php.
//
// Wrapped in throttle:mcp-oauth (per-IP) so the public discovery + open registration
// surface is rate-limited; the limiter holds oauth/register (DCR) to a far tighter
// hourly budget than the discovery endpoints — registration-spam is the abuse target.
Route::middleware('throttle:mcp-oauth')->group(function (): void {
    Mcp::oauthRoutes();
});

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Identity routes are now registered via IdentityServiceProvider from app/Modules/Identity/routes/web.php

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
});
// Review code and optimize
// @deprecated Campus routes migrated to App\Modules\Academic\routes\web.php. Remove after 2026-06-01.
// require __DIR__ . '/web/campuses.php';

// @deprecated User routes migrated to App\Modules\Identity\routes\web.php. Remove after 2024-12-31.
// require __DIR__ . '/web/user.php';
// require __DIR__ . '/web/student.php';
require __DIR__.'/web/failed-students.php';
require __DIR__.'/web/systems.php';
require __DIR__.'/web/email-monitoring.php';
// @deprecated Syllabus template routes migrated to Academic Catalog.
// Forms, surveys, and student-support routes are owned by Engagement.
require __DIR__.'/web/scholarships.php';
require __DIR__.'/web/student-scholarships.php';
require __DIR__.'/web/scholarship-adjustments.php';
require __DIR__.'/web/tuition-plans.php';
require __DIR__.'/web/vouchers.php';
// require __DIR__ . '/web/surveys.php';
require __DIR__.'/web/notifications.php';
