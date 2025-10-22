<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add Canvas integration fields to assessment_components.
     * Canvas Assignment Groups sync to AssessmentComponents with:
     * - is_canvas_synced: Flag to identify Canvas-synced components
     * - canvas_assignment_group_id: Canvas group ID for tracking
     * - canvas_group_weight: Weight from Canvas group
     */
    public function up(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->boolean('is_canvas_synced')->default(false)->after('category');
            $table->string('canvas_assignment_group_id', 50)->nullable()->after('is_canvas_synced');
            $table->decimal('canvas_group_weight', 5, 2)->nullable()->after('canvas_assignment_group_id');
            
            $table->index('is_canvas_synced');
            $table->index('canvas_assignment_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->dropIndex(['is_canvas_synced']);
            $table->dropIndex(['canvas_assignment_group_id']);
            $table->dropColumn([
                'is_canvas_synced',
                'canvas_assignment_group_id',
                'canvas_group_weight',
            ]);
        });
    }
};
