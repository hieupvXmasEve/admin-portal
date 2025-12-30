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
            $table->decimal('credit_points', 8, 2)->default(0)->after('credit_hours_earned');
            $table->decimal('credit_points_earned', 8, 2)->default(0)->after('credit_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropColumn(['credit_points', 'credit_points_earned']);
        });
    }
};
