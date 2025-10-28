<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckMissingEgcInvoices extends Command
{
    protected $signature = 'students:check-missing-egc-invoices {--campus=1}';

    protected $description = 'Check students with EGC course offerings but missing invoices';

    public function handle(): int
    {
        $campusId = (int) $this->option('campus');

        $this->info("Checking for campus_id = {$campusId}");
        $this->newLine();

        // Step 1: Count students with status = 'intake_pre_uni_gc'
        $totalEgcStudents = Student::where('campus_id', $campusId)
            ->where('status', 'intake_pre_uni_gc')
            ->count();

        $this->info("Total students with status 'intake_pre_uni_gc': {$totalEgcStudents}");

        // Step 2: Count student_invoices with status = 'partial' or 'pending'
        $egcStudentIds = Student::where('campus_id', $campusId)
            ->where('status', 'intake_pre_uni_gc')
            ->pluck('id');

        $totalPartialInvoices = StudentInvoice::whereIn('student_id', $egcStudentIds)
            ->where('status', 'partial')
            ->distinct('student_id')
            ->count('student_id');

        $totalPendingInvoices = StudentInvoice::whereIn('student_id', $egcStudentIds)
            ->where('status', 'pending')
            ->distinct('student_id')
            ->count('student_id');

        $this->info("Students with 'partial' invoices: {$totalPartialInvoices}");
        $this->info("Students with 'pending' invoices: {$totalPendingInvoices}");
        $this->newLine();

        // Step 3: Find students enrolled in EGC course offerings without invoices (partial or pending)
        $studentsInEgcCourses = DB::table('students as s')
            ->select([
                's.id',
                's.student_id',
                's.full_name',
                's.email',
                's.status',
                DB::raw('GROUP_CONCAT(DISTINCT u.code) as egc_units'),
                DB::raw('COUNT(DISTINCT co.id) as egc_courses_count'),
            ])
            ->join('course_registrations as cr', 's.id', '=', 'cr.student_id')
            ->join('course_offerings as co', 'cr.course_offering_id', '=', 'co.id')
            ->join('units as u', 'co.unit_id', '=', 'u.id')
            ->leftJoin('student_invoices as si', function ($join) {
                $join->on('s.id', '=', 'si.student_id')
                    ->whereIn('si.status', ['partial', 'pending']);
            })
            ->where('s.campus_id', $campusId)
            ->where('s.status', 'intake_pre_uni_gc')
            ->where('u.unit_type', 'egc')
            ->where('co.course_status', 'in_progress')
            ->whereIn('cr.registration_status', ['pending', 'registered', 'confirmed']) // Exclude completed
            ->whereNull('si.id') // No invoice (partial or pending)
            ->groupBy('s.id', 's.student_id', 's.full_name', 's.email', 's.status')
            ->get();

        if ($studentsInEgcCourses->isEmpty()) {
            $this->info('✓ All students with EGC courses have invoices (partial or pending)!');
            return self::SUCCESS;
        }

        $this->warn("Found {$studentsInEgcCourses->count()} students without invoices (partial/pending):");
        $this->newLine();

        $this->table(
            ['ID', 'Student ID', 'Full Name', 'Email', 'EGC Units', 'Courses Count'],
            $studentsInEgcCourses->map(function ($student) {
                return [
                    $student->id,
                    $student->student_id,
                    $student->full_name,
                    $student->email,
                    $student->egc_units,
                    $student->egc_courses_count,
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('Summary:');
        $this->info("- Total EGC students: {$totalEgcStudents}");
        $this->info("- Students with partial invoices: {$totalPartialInvoices}");
        $this->info("- Students with pending invoices: {$totalPendingInvoices}");
        $this->info("- Students enrolled in EGC courses without invoices: {$studentsInEgcCourses->count()}");

        return self::SUCCESS;
    }
}
