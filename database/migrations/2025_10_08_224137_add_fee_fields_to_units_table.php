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
        Schema::table('units', function (Blueprint $table) {
            $table->integer('level')->default(0)->after('credit_points');
            $table->decimal('base_fee', 15, 2)->nullable()->after('level');
            $table->decimal('retake_fee', 15, 2)->nullable()->after('base_fee');
            $table->enum('unit_type', [
                'general',      // Môn chung
                'egc',          // English for General Communication
                'semi',         // Semiconductor
                'ai',           // Artificial Intelligence
                'mkt',          // Marketing
                'ba',           // Business Administration
                'cs',           // Computer Science
                'ee',           // Electrical Engineering
                'me',           // Mechanical Engineering
                'fin',           // Finance
            ])->default('general')->after('retake_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['level', 'base_fee', 'retake_fee', 'unit_type']);
        });
    }
};
