<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_component_id')->constrained('assessment_components')->onDelete('cascade');
            $table->foreignId('created_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            $table->string('name', 100); // e.g., "Group A", "Team Alpha", "Project Group 1"
            $table->string('code', 20)->nullable(); // e.g., "GRP1", "ALPHA"
            $table->text('description')->nullable();

            $table->integer('min_members')->default(1);
            $table->integer('max_members')->default(10);
            $table->integer('current_members')->default(0);

            $table->enum('status', [
                'forming',
                'active',
                'completed',
                'disbanded',
                'inactive'
            ])->default('forming');

            $table->boolean('is_locked')->default(false); // Prevent changes to membership
            $table->datetime('locked_at')->nullable();

            $table->text('group_notes')->nullable();
            $table->json('member_roles')->nullable(); // {"leader": "student_id", "secretary": "student_id"}

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['assessment_component_id', 'status']);
            $table->index(['created_by_lecture_id', 'created_at']);
            $table->index(['is_locked', 'status']);

            // Unique constraint for group codes within component
            $table->unique(['assessment_component_id', 'code'], 'unique_component_group_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_groups');
    }
};
