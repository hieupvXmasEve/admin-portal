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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->onDelete('cascade');
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('grading_type', ['grade', 'pass_fail'])->default('grade');
            $table->decimal('total_credits', 5, 2)->default(0);
            $table->foreignId('prerequisite_module_id')->nullable()
                ->constrained('modules')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campus_id', 'code']);
            $table->index(['campus_id', 'grading_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
