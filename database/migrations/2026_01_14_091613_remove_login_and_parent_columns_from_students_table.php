<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove login-related columns and parent_user_id from students table.
 *
 * These columns are no longer needed because:
 * - Students now login via the users table (through user_id FK)
 * - Parent linking is handled via parents + parent_student tables
 *
 * Columns to remove:
 * - password (login via users table)
 * - oauth_provider (login via users table)
 * - oauth_provider_id (login via users table)
 * - last_login_at (tracked on users table)
 * - email_verified_at (tracked on users table)
 * - remember_token (tracked on users table)
 * - parent_user_id (replaced by parents + parent_student tables)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check for index existence before trying to drop it
        $indexes = Schema::getIndexes('students');
        $indexNames = array_column($indexes, 'name');

        $foreignKeys = Schema::getForeignKeys('students');
        $fkNames = array_column($foreignKeys, 'name');

        Schema::table('students', function (Blueprint $table) use ($indexNames, $fkNames) {
            // Drop foreign key if exists (parent_user_id)
            if (Schema::hasColumn('students', 'parent_user_id')) {
                // Drop foreign key constraint first if it exists
                if (in_array('students_parent_user_id_foreign', $fkNames)) {
                    $table->dropForeign(['parent_user_id']);
                }
                $table->dropColumn('parent_user_id');
            }

            // Drop oauth index if it exists
            if (in_array('students_oauth_provider_oauth_provider_id_index', $indexNames)) {
                $table->dropIndex(['oauth_provider', 'oauth_provider_id']);
            }

            // Drop login-related columns
            $columnsToDrop = [
                'password',
                'oauth_provider',
                'oauth_provider_id',
                'last_login_at',
                'email_verified_at',
                'remember_token',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Re-add login columns
            $table->string('password', 255)->nullable()->after('phone');
            $table->enum('oauth_provider', ['google', 'microsoft', 'manual'])->default('google')->after('password');
            $table->string('oauth_provider_id', 255)->nullable()->after('oauth_provider');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->timestamp('email_verified_at')->nullable()->after('last_login_at');
            $table->rememberToken();

            // Re-add parent_user_id
            $table->foreignId('parent_user_id')->nullable()->after('gc_total_levels')
                ->constrained('users')->nullOnDelete();

            // Re-add index
            $table->index(['oauth_provider', 'oauth_provider_id']);
        });
    }
};
