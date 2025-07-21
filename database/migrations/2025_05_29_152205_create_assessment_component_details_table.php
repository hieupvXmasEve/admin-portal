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
        Schema::create('assessment_component_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_component_id')->constrained('assessment_components')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('max_points', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_component_details');
    }
};
