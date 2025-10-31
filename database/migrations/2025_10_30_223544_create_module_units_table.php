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
        Schema::create('module_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->enum('grading_type', ['grade', 'pass_fail'])
                ->default('grade')
                ->comment('How this unit is graded in this module: grade (counts in average) or pass_fail (only affects status)');
            $table->decimal('weight', 5, 2)->nullable()->comment('Weight for average calculation');
            $table->unsignedInteger('order')->default(0)->comment('Display order');
            $table->timestamps();

            $table->unique(['module_id', 'unit_id']);
            $table->index(['module_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_units');
    }
};
