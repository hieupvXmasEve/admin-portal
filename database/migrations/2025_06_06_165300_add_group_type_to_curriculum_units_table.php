<?php

declare(strict_types=1);

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
        Schema::table('curriculum_units', function (Blueprint $table) {
            $table->enum('group_type', ['core', 'major', 'elective'])
                ->default('core')
                ->after('unit_type_id')
                ->comment('Type of curriculum unit: core (mandatory), major (specialization required), or elective (optional)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_units', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }
};
