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
        Schema::create('unit_prerequisite_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('unit_prerequisite_groups')->onDelete('cascade');
            $table->enum('type', [
                'prerequisite',
                'co_requisite',
                'concurrent',
                'anti_requisite',
                'assumed_knowledge',
                'credit_requirement',
                'textual'
            ])->default('prerequisite');
            $table->foreignId('required_unit_id')->nullable()->constrained('units')->onDelete('cascade');
            $table->integer('required_credits')->nullable();
            $table->text('free_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_prerequisite_conditions');
    }
};
