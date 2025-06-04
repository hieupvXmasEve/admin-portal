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
        Schema::create('curriculum_unit_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->enum('name', ['core', 'elective', 'major']);
            $table->timestamps();

            // Add unique constraint on name
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_unit_types');
    }
};
