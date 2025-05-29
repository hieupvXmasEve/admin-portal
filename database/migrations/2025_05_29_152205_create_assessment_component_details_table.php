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
            $table->foreignId('component_id')->constrained('assessment_components')->onDelete('cascade');
            $table->string('name', 100);
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
