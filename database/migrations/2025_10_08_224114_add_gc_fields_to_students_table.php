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
        Schema::table('students', function (Blueprint $table) {
            $table->integer('gc_starting_level')->nullable()->after('status');
            $table->integer('gc_current_level')->nullable()->after('gc_starting_level');
            $table->integer('gc_total_levels')->nullable()->default(6)->after('gc_current_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['gc_starting_level', 'gc_current_level', 'gc_total_levels']);
        });
    }
};
