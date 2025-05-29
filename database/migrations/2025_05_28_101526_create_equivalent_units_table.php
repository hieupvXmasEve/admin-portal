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
        Schema::create('equivalent_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('equivalent_unit_id')->constrained('units')->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->foreignId('valid_from_semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->timestamps();

            $table->unique(['unit_id', 'equivalent_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equivalent_units');
    }
};
