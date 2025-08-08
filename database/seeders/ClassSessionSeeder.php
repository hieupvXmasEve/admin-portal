<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Room;
use App\Services\ClassSessionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ClassSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates class sessions for existing course offerings using ClassSessionService.
     */
    public function run(): void
    {
        $this->command->info('📅 Generating class sessions for existing course offerings...');

        $classSessionService = app(ClassSessionService::class);

        // Get all active course offerings
        $courseOfferings = CourseOffering::with([
            'curriculumUnit.unit',
            'curriculumUnit.syllabus',
            'semester',
        ])
            ->where('is_active', true)
            ->get();

        if ($courseOfferings->isEmpty()) {
            $this->command->warn('⚠️  No active course offerings found. Skipping class session generation.');

            return;
        }

        // Get a default room for sessions (first available room)
        $defaultRoom = Room::where('is_bookable', true)
//            ->where('status', 'active')
            ->first();

        if (! $defaultRoom) {
            $this->command->error('❌ No bookable rooms found. Please ensure rooms exist before generating class sessions.');

            return;
        }

        $this->command->info("Using default room: {$defaultRoom->name} (ID: {$defaultRoom->id})");

        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;

        foreach ($courseOfferings as $courseOffering) {
            try {
                $unitCode = $courseOffering->curriculumUnit->unit->code ?? 'Unknown';
                $this->command->info("Processing {$unitCode} (Course Offering ID: {$courseOffering->id})...");

                // Generate class sessions using the service
                $sessions = $classSessionService->generateClassSessions($courseOffering, $defaultRoom->id);

                if ($sessions->count() > 0) {
                    $successCount++;
                    $this->command->info("✅ Generated {$sessions->count()} class sessions for {$unitCode}");
                } else {
                    $skipCount++;
                    $this->command->warn("⚠️  No sessions generated for {$unitCode}");
                }

            } catch (\Exception $e) {
                $errorCount++;
                $this->command->error("❌ Failed to generate sessions for Course Offering {$courseOffering->id}: {$e->getMessage()}");

                // Log the detailed error for debugging
                Log::error("ClassSessionSeeder error for CourseOffering {$courseOffering->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->command->info('📊 Class session generation summary:');
        $this->command->info("✅ Successfully processed: {$successCount} course offerings");
        $this->command->info("⚠️  Skipped: {$skipCount} course offerings");
        $this->command->info("❌ Errors: {$errorCount} course offerings");

        if ($errorCount > 0) {
            $this->command->warn('⚠️  Some course offerings had errors. Check the logs for details.');
        }

        $this->command->info('🎉 Class session seeding completed!');
    }
}
