<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('assessment_components', 'syllabus_id')) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys('assessment_components');
            $exists = false;
            foreach ($foreignKeys as $foreignKey) {
                if (in_array('syllabus_id', $foreignKey->getColumns())) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                Schema::table('assessment_components', function (Blueprint $table) {
                    $table->dropForeign(['syllabus_id']);
                });
            }
        }

        Schema::table('assessment_components', function (Blueprint $table) {
            // Rename column to syllabus_template_id
            if (Schema::hasColumn('assessment_components', 'syllabus_id')) {
                $table->renameColumn('syllabus_id', 'syllabus_template_id');
            }
        });

        Schema::table('assessment_components', function (Blueprint $table) {
            // Add new foreign key to syllabus_templates
            if (Schema::hasColumn('assessment_components', 'syllabus_template_id')) {
                $table->foreign('syllabus_template_id')
                    ->references('id')
                    ->on('syllabus_templates')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            try {
                $table->dropForeign(['syllabus_template_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('assessment_components', 'syllabus_template_id')) {
                $table->renameColumn('syllabus_template_id', 'syllabus_id');
            }
        });

        Schema::table('assessment_components', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_components', 'syllabus_id')) {
                $table->foreign('syllabus_id')
                    ->references('id')
                    ->on('syllabus')
                    ->cascadeOnDelete();
            }
        });
    }
};

