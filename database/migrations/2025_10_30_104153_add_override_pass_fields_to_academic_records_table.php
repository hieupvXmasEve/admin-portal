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
        Schema::table('academic_records', function (Blueprint $table) {
            $table->boolean('override_pass')->default(false)->after('satisfies_prerequisite');
            $table->boolean('is_passed')->nullable()->after('override_pass');
            $table->text('override_reason')->nullable()->after('is_passed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropColumn(['override_pass', 'is_passed', 'override_reason']);
        });
    }
};
