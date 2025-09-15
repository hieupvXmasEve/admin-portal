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
        Schema::create('form_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('scope_type', ['section', 'class_session', 'course', 'global']);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->integer('submission_limit_per_user')->default(1);
            $table->timestamps();
            
            $table->index(['form_id', 'campus_id', 'scope_type', 'scope_id', 'start_at'], 'idx_form_targets_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_targets');
    }
};
