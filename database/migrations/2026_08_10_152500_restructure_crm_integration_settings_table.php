<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two changes to the single-row CRM connection config:
 *
 * 1. `base_url` → `login_url` + `data_url`. The CRM's login and
 *    New-Enrollment endpoints are staff-supplied full URLs, not a host that
 *    `CrmClient` concatenates a hardcoded path onto — the CRM's actual
 *    routing is not something this codebase should assume.
 * 2. Persists the bearer token (`token`, `token_type`, `token_obtained_at`,
 *    `encrypted` cast on `token`) so login is an explicit, one-time staff
 *    action and a sync run reuses the stored token instead of logging in
 *    implicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_integration_settings', function (Blueprint $table) {
            $table->string('login_url')->nullable()->after('id');
            $table->string('data_url')->nullable()->after('login_url');
            $table->text('token')->nullable();
            $table->string('token_type')->nullable();
            $table->timestamp('token_obtained_at')->nullable();
        });

        // Best-effort carry-over for any row already configured with base_url.
        DB::table('crm_integration_settings')->whereNotNull('base_url')->get()->each(function (object $row): void {
            $baseUrl = rtrim((string) $row->base_url, '/');
            DB::table('crm_integration_settings')->where('id', $row->id)->update([
                'login_url' => "{$baseUrl}/api/login",
                'data_url' => "{$baseUrl}/api/ne",
            ]);
        });

        Schema::table('crm_integration_settings', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }

    public function down(): void
    {
        Schema::table('crm_integration_settings', function (Blueprint $table) {
            $table->string('base_url')->nullable()->after('id');
        });

        Schema::table('crm_integration_settings', function (Blueprint $table) {
            $table->dropColumn(['login_url', 'data_url', 'token', 'token_type', 'token_obtained_at']);
        });
    }
};
