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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('form_sections')->cascadeOnDelete();
            $table->string('code', 100);
            $table->text('text');
            $table->enum('type', [
                'short_text', 'long_text', 'single_choice', 'multi_choice',
                'likert', 'rating', 'date', 'number', 'file', 'matrix', 'yes_no'
            ]);
            $table->boolean('is_required')->default(false);
            $table->text('help_text')->nullable();
            $table->integer('order_index')->default(0);
            $table->json('validation_json')->nullable();
            $table->json('visibility_condition_json')->nullable();
            $table->timestamps();
            
            $table->unique(['form_version_id', 'code']);
            $table->index(['form_version_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
