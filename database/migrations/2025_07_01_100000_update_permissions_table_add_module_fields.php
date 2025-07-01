<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name')->after('name')->nullable();
            }
            if (!Schema::hasColumn('permissions', 'module')) {
                $table->string('module')->after('description')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'display_name')) {
                $table->dropColumn('display_name');
            }
            if (Schema::hasColumn('permissions', 'module')) {
                $table->dropColumn('module');
            }
            // We don't drop 'description' as it seems to be an original column.
        });
    }
};
