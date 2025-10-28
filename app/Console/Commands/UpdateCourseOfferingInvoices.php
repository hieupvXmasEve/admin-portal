<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\Semester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCourseOfferingInvoices extends Command
{
    protected $signature = 'course:update-invoices {course_offering_id : The ID of the course offering}';

    protected $description = 'Create or update invoice items for all enrolled students in a course offering';

    public function handle(): int
    {
        $courseOfferingId = $this->argument('course_offering_id');

        $courseOffering = CourseOffering::with(['unit', 'semester', 'courseRegistrations.student'])
            ->find($courseOfferingId);

        if (! $courseOffering) {
            $this->error("Course offering with ID {$courseOfferingId} not found.");

            return self::FAILURE;
        }

        $this->info("Processing: {$courseOffering->course_code}");
        $this->info("Unit: {$courseOffering->unit->code} - {$courseOffering->unit->name}");
        $this->newLine();

        $registrations = $courseOffering->courseRegistrations()
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
            ->with('student')
            ->get();

        if ($registrations->isEmpty()) {
            $this->warn('No enrolled students found.');

            return self::SUCCESS;
        }

        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $skippedDetails = [];
        $errorDetails = [];

        $progressBar = $this->output->createProgressBar($registrations->count());

        try {
            DB::beginTransaction();

            foreach ($registrations as $registration) {
                $student = $registration->student;

                try {
                    $existingItem = \App\Models\InvoiceItem::where('reference_id', $registration->id)
                        ->where('reference_type', CourseRegistration::class)
                        ->first();

                    if ($existingItem) {
                        $progressBar->advance();
                        $skippedCount++;
                        $skippedDetails[] = [
                            'student_id' => $student->student_id,
                            'name' => $student->name,
                            'reason' => 'Invoice item already exists',
                            'invoice_number' => $existingItem->invoice->invoice_number ?? 'N/A',
                        ];

                        continue;
                    }

                    $previousAttempts = \App\Models\AcademicRecord::where('student_id', $student->id)
                        ->where('unit_id', $courseOffering->unit_id)
                        ->count();

                    $isRetake = $previousAttempts > 0;

                    $result = $this->createCourseFeeInvoiceItem($student, $courseOffering, $registration, $isRetake);

                    if ($result === false) {
                        $skippedCount++;
                        $skippedDetails[] = [
                            'student_id' => $student->student_id,
                            'name' => $student->name,
                            'reason' => 'No charge required (non-EGC first attempt or zero fee)',
                            'invoice_number' => '-',
                        ];
                    } else {
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to create invoice for {$student->student_id}: " . $e->getMessage());
                    $errorCount++;
                    $errorDetails[] = [
                        'student_id' => $student->student_id,
                        'name' => $student->name,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }

            DB::commit();

            $progressBar->finish();
            $this->newLine(2);

            $this->table(['Result', 'Count'], [
                ['✓ Created', $successCount],
                ['⚠ Skipped', $skippedCount],
                ['✗ Errors', $errorCount],
            ]);

            if (! empty($skippedDetails)) {
                $this->newLine();
                $this->warn('Skipped Details:');
                $this->table(
                    ['Student ID', 'Name', 'Reason', 'Existing Invoice'],
                    array_map(fn($item) => [
                        $item['student_id'],
                        $item['name'],
                        $item['reason'],
                        $item['invoice_number'],
                    ], $skippedDetails)
                );
            }

            if (! empty($errorDetails)) {
                $this->newLine();
                $this->error('Error Details:');
                $this->table(
                    ['Student ID', 'Name', 'Error'],
                    array_map(fn($item) => [
                        $item['student_id'],
                        $item['name'],
                        $item['error'],
                    ], $errorDetails)
                );
            }

            return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function createCourseFeeInvoiceItem(
        Student $student,
        CourseOffering $courseOffering,
        CourseRegistration $registration,
        bool $isRetake
    ): bool {
        $unit = $courseOffering->unit;

        $shouldCharge = false;
        $itemType = null;
        $fee = 0;
        $description = '';

        if ($unit->unit_type === 'egc') {
            if ($isRetake) {
                $shouldCharge = true;
                $itemType = 'retake';
                $fee = $unit->retake_fee ?? 0;
                $description = "Retake Fee: {$unit->code} - {$unit->name}";
            } else {
                $shouldCharge = true;
                $itemType = 'egc';
                $fee = $unit->base_fee ?? 0;
                $description = "EGC Course Fee: {$unit->code} - {$unit->name}";
            }
        } elseif ($isRetake) {
            $shouldCharge = true;
            $itemType = 'retake';
            $fee = $unit->retake_fee ?? 0;
            $description = "Retake Fee: {$unit->code} - {$unit->name}";
        }

        if (! $shouldCharge || $fee <= 0) {
            return false;
        }

        $invoice = $this->findOrCreateStudentInvoice($student, $courseOffering->semester_id);

        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => $itemType,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $fee,
            'total_price' => $fee,
            'paid_amount' => 0.00,
            'reference_id' => $registration->id,
            'reference_type' => CourseRegistration::class,
        ]);

        return true;
    }

    private function findOrCreateStudentInvoice(Student $student, int $semesterId): \App\Models\StudentInvoice
    {
        $invoice = \App\Models\StudentInvoice::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->first();

        if ($invoice) {
            return $invoice;
        }

        $billingCycle = \App\Models\BillingCycle::where('semester_id', $semesterId)->first();

        if (! $billingCycle) {
            $semester = Semester::find($semesterId);
            $billingCycle = \App\Models\BillingCycle::create([
                'semester_id' => $semesterId,
                'name' => 'Default Billing Cycle - ' . $semester->name,
                'start_date' => $semester->start_date,
                'end_date' => $semester->end_date,
                'due_date' => $semester->end_date,
                'status' => 'active',
            ]);
        }

        return \App\Models\StudentInvoice::create([
            'invoice_number' => 'INV-' . ($billingCycle->semester->code ?? 'SEM') . '-' . $student->student_id . '-' . now()->format('YmdHis'),
            'student_id' => $student->id,
            'billing_cycle_id' => $billingCycle->id,
            'semester_id' => $semesterId,
            'subtotal' => 0,
            'discount_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'due_date' => $billingCycle->due_date,
        ]);
    }
}
